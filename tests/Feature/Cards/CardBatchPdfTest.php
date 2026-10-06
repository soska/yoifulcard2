<?php

use App\Enums\CardBatchPdfAction;
use App\Enums\CardStatus;
use App\Enums\CardTemplate;
use App\Enums\MembershipRole;
use App\Enums\TemplateAudience;
use App\Exceptions\CardBatchException;
use App\Jobs\GenerateCardBatchPdf;
use App\Mail\CardBatchPdfReadyMail;
use App\Models\Card;
use App\Models\CardBatch;
use App\Models\CardBatchPdf;
use App\Models\CardBatchPdfLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\CardBatchIssuer;
use App\Services\CardBatchPdfRenderer;
use App\Services\CardBatchPrinter;
use App\Services\CardLedger;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Printing card batches as PDF (ARM-358). A batch PDF holds the token of
| every card it prints, so it is made on the queue for one person, kept on
| the private disk until it is downloaded once or expires, and every one
| made and every download is logged. The queue is sync in tests, so asking
| for a PDF also makes it.
*/

beforeEach(function () {
    Storage::fake(CardBatchPdf::DISK);
    Storage::fake('public');
    Mail::fake();
});

/**
 * An owner of a business that can preissue cards, and a batch of `$count`
 * inactive cards.
 *
 * @param  array<string, mixed>  $organization
 * @return array{0: User, 1: Organization, 2: CardBatch}
 */
function printableBatch(int $count = 3, array $organization = []): array
{
    [$user, $organization] = cardOwner(['can_preissue' => true, ...$organization]);
    $batch = app(CardBatchIssuer::class)->issue($organization, $count, $user, byAdmin: false);

    return [$user, $organization, $batch];
}

/**
 * The page sizes (MediaBox, in points) and page count of a PDF dompdf made.
 *
 * @return array{sizes: list<string>, pages: int}
 */
function pdfShape(string $pdf): array
{
    preg_match_all('#/MediaBox \[([^\]]+)\]#', $pdf, $boxes);
    preg_match_all('#/Type /Page\b(?!s)#', $pdf, $pages);

    return ['sizes' => array_values(array_unique($boxes[1])), 'pages' => count($pages[0])];
}

test('templates are defined in code with an audience', function () {
    expect(CardTemplate::for(TemplateAudience::Business))->toBe([CardTemplate::SheetLetter, CardTemplate::SheetA4])
        ->and(CardTemplate::for(TemplateAudience::Admin))->toBe(CardTemplate::cases())
        ->and(CardTemplate::PrintShop->audience())->toBe(TemplateAudience::Admin)
        ->and(CardTemplate::PrintShop->availableTo(TemplateAudience::Business))->toBeFalse()
        ->and(CardTemplate::SheetA4->availableTo(TemplateAudience::Admin))->toBeTrue()
        ->and(CardTemplate::SheetLetter->cardsPerPage())->toBe(10)
        ->and(CardTemplate::PrintShop->pageSize())->toBe([91.6, 60.0]);

    foreach (CardTemplate::cases() as $template) {
        expect(view()->exists($template->view()))->toBeTrue();
    }
});

test('a business prints only with business templates', function () {
    [$user, , $batch] = printableBatch();

    $this->actingAs($user)
        ->from(route('batches.show', $batch))
        ->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::PrintShop->value])
        ->assertRedirect(route('batches.show', $batch))
        ->assertSessionHasErrors(['template' => 'That template is not available here.']);

    $this->post(route('batches.pdfs.store', $batch), ['template' => 'poster'])->assertSessionHasErrors('template');
    $this->post(route('batches.pdfs.store', $batch), [])->assertSessionHasErrors('template');

    expect(CardBatchPdf::count())->toBe(0);

    $this->get(route('batches.show', $batch))
        ->assertInertia(fn (Assert $page) => $page
            ->where('print.templates', ['sheet_letter', 'sheet_a4'])
            ->where('print.pdf', null));

    // The admin area prints with every template.
    $this->actingAs(superadmin())
        ->post(route('admin.batches.pdfs.store', $batch), ['template' => CardTemplate::PrintShop->value])
        ->assertSessionHasNoErrors();

    $this->get(route('admin.batches.show', $batch))
        ->assertInertia(fn (Assert $page) => $page
            ->where('print.templates', ['sheet_letter', 'sheet_a4', 'print_shop'])
            ->where('print.pdf.template', 'print_shop')
            ->where('print.pdf.status', 'ready'));
});

test('the sheet PDF has 10 cards per Letter page', function () {
    [$user, , $batch] = printableBatch(12);

    $this->actingAs($user)
        ->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetLetter->value])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'code' => 'batch.pdf_requested', 'params' => []]);

    $pdf = CardBatchPdf::sole();
    $file = Storage::disk(CardBatchPdf::DISK)->get($pdf->path);

    expect($pdf->status())->toBe('ready')
        ->and($pdf->path)->toBe($pdf->filePath())
        ->and($pdf->cards)->toBe(12)
        ->and($pdf->pages)->toBe(2)
        ->and($pdf->size)->toBe(strlen($file))
        ->and($pdf->by_admin)->toBeFalse()
        ->and(str_starts_with($file, '%PDF-'))->toBeTrue()
        ->and(pdfShape($file))->toBe(['sizes' => ['0.000 0.000 612.000 792.000'], 'pages' => 2])
        ->and($batch->fresh()->template)->toBe('sheet_letter');

    Mail::assertSent(CardBatchPdfReadyMail::class, fn (CardBatchPdfReadyMail $mail) => $mail->hasTo($user->email) && $mail->pdf->is($pdf));

    // The email links to the batch page; the PDF is never attached.
    $mail = new CardBatchPdfReadyMail($pdf);
    $mail->assertSeeInHtml(route('batches.show', $batch));
    expect($mail->attachments)->toBe([]);

    // A4 has the same cards per page.
    $this->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetA4->value]);
    $file = Storage::disk(CardBatchPdf::DISK)->get(CardBatchPdf::sole()->path);
    expect(pdfShape($file))->toBe(['sizes' => ['0.000 0.000 595.276 841.890'], 'pages' => 2]);
});

test('the print shop PDF has one card per page with bleed, and backs when the program has terms', function () {
    [, $organization, $batch] = printableBatch(3);
    $printer = app(CardBatchPrinter::class);
    $admin = superadmin();

    $printer->request($batch, CardTemplate::PrintShop, $admin, TemplateAudience::Admin);
    $file = Storage::disk(CardBatchPdf::DISK)->get(CardBatchPdf::sole()->path);

    // 85.6 x 54 mm plus 3 mm of bleed on every side.
    expect(pdfShape($file))->toBe(['sizes' => ['0.000 0.000 259.654 170.079'], 'pages' => 3])
        ->and(CardBatchPdfRenderer::points(91.6))->toBe(259.654);

    $batch->program->update(['terms_url' => 'https://example.com/terms']);
    $printer->request($batch->fresh(), CardTemplate::PrintShop, $admin, TemplateAudience::Admin);

    $pdf = CardBatchPdf::sole();
    expect($pdf->pages)->toBe(6)
        ->and($pdf->by_admin)->toBeTrue()
        ->and(pdfShape(Storage::disk(CardBatchPdf::DISK)->get($pdf->path))['pages'])->toBe(6);
});

test('only the cards still in stock are printed', function () {
    [$user, , $batch] = printableBatch(4);
    $cards = $batch->cards()->orderBy('code')->get();

    app(CardLedger::class)->activate($cards[0], '100.00', $user);
    $cards[1]->update(['status' => CardStatus::Cancelled]);

    $this->actingAs($user)->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetLetter->value]);

    expect(CardBatchPdf::sole()->cards)->toBe(2);

    // Nothing left to print.
    Card::query()->update(['status' => CardStatus::Cancelled]);

    $this->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetLetter->value])
        ->assertSessionHasErrors(['batch' => 'This batch has no cards in stock to print.']);

    $batch->update(['voided_at' => now()]);

    expect(fn () => app(CardBatchPrinter::class)->request($batch, CardTemplate::SheetLetter, $user, TemplateAudience::Business))
        ->toThrow(CardBatchException::class, 'This batch is already voided.');
});

test('the PDF is made on the queue and replaces the previous one', function () {
    Queue::fake();
    [$user, , $batch] = printableBatch();

    $this->actingAs($user)->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetLetter->value]);
    $first = CardBatchPdf::sole();

    expect($first->status())->toBe('pending')
        ->and($first->path)->toBeNull();
    Queue::assertPushed(GenerateCardBatchPdf::class, fn (GenerateCardBatchPdf $job) => $job->pdfId === $first->id);

    $this->get(route('batches.show', $batch))
        ->assertInertia(fn (Assert $page) => $page->where('print.pdf.status', 'pending'));

    $this->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetA4->value]);

    $second = CardBatchPdf::sole();
    expect($second->id)->not->toBe($first->id);

    // The job for the replaced PDF finds nothing to do.
    (new GenerateCardBatchPdf($first->id))->handle(app(CardBatchPdfRenderer::class));
    expect(Storage::disk(CardBatchPdf::DISK)->allFiles())->toBe([])
        ->and(CardBatchPdfLog::count())->toBe(0);

    (new GenerateCardBatchPdf($second->id))->handle(app(CardBatchPdfRenderer::class));
    expect($second->fresh()->status())->toBe('ready');
});

test('a failed job marks the PDF failed', function () {
    Queue::fake();
    [$user, , $batch] = printableBatch();

    $pdf = app(CardBatchPrinter::class)->request($batch, CardTemplate::SheetLetter, $user, TemplateAudience::Business);
    (new GenerateCardBatchPdf($pdf->id))->failed(new RuntimeException('Out of memory'));

    expect($pdf->fresh()->status())->toBe('failed');

    $this->actingAs($user)->get(route('batches.show', $batch))
        ->assertInertia(fn (Assert $page) => $page->where('print.pdf.status', 'failed'));
});

test('the person who asked downloads the PDF once, privately', function () {
    [$user, , $batch] = printableBatch();
    $this->actingAs($user)->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetLetter->value]);
    $pdf = CardBatchPdf::sole();
    $path = $pdf->path;

    $response = $this->get(route('batches.pdfs.download', [$batch, $pdf]))->assertOk();

    expect(str_starts_with($response->streamedContent(), '%PDF-'))->toBeTrue()
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->headers->get('Content-Disposition'))->not->toContain($batch->cards()->value('code'))
        ->and(CardBatchPdf::count())->toBe(0);

    Storage::disk(CardBatchPdf::DISK)->assertMissing($path);

    // Once.
    $this->get(route('batches.pdfs.download', [$batch, $pdf->id]))
        ->assertRedirect(route('batches.show', $batch))
        ->assertSessionHasErrors('batch');
});

test('nobody else downloads the PDF', function () {
    [$owner, $organization, $batch] = printableBatch();
    $manager = User::factory()->create();
    $employee = User::factory()->create();
    $organization->memberships()->create(['user_id' => $manager->id, 'role' => MembershipRole::Manager]);
    $organization->memberships()->create(['user_id' => $employee->id, 'role' => MembershipRole::Employee]);
    [$stranger] = cardOwner(['can_preissue' => true]);

    $this->actingAs($owner)->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetLetter->value]);
    $pdf = CardBatchPdf::sole();
    $url = route('batches.pdfs.download', [$batch, $pdf]);

    // Another manager of the business: not theirs.
    $this->actingAs($manager)->get($url)
        ->assertRedirect(route('batches.show', $batch))
        ->assertSessionHasErrors(['batch' => 'This PDF is no longer available. Make a new one.']);

    $this->actingAs($employee)->get($url)->assertForbidden();
    $this->actingAs($stranger)->get($url)->assertForbidden();

    // A superadmin can't take it through the admin area either.
    $this->actingAs(superadmin())->get(route('admin.batches.pdfs.download', [$batch, $pdf]))
        ->assertRedirect(route('admin.batches.show', $batch));

    // Not through another batch's URL.
    $other = app(CardBatchIssuer::class)->issue($organization, 1, $owner, byAdmin: false);
    $this->actingAs($owner)->get(route('batches.pdfs.download', [$other, $pdf]))
        ->assertRedirect(route('batches.show', $other));

    // Without can_preissue the business has no batch pages at all.
    $organization->update(['can_preissue' => false]);
    $this->actingAs($owner)->get($url)->assertForbidden();
    $this->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetLetter->value])->assertForbidden();

    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));

    expect(CardBatchPdf::count())->toBe(1)
        ->and(CardBatchPdfLog::where('action', CardBatchPdfAction::Downloaded)->count())->toBe(0);
    Storage::disk(CardBatchPdf::DISK)->assertExists($pdf->path);
});

test('a PDF asked for in the admin area is downloaded there', function () {
    [, , $batch] = printableBatch();
    $admin = superadmin();
    // A superadmin who is also an owner of the business.
    $batch->organization->memberships()->create(['user_id' => $admin->id, 'role' => MembershipRole::Owner]);

    $this->actingAs($admin)->post(route('admin.batches.pdfs.store', $batch), ['template' => CardTemplate::PrintShop->value]);
    $pdf = CardBatchPdf::sole();

    $this->get(route('batches.pdfs.download', [$batch, $pdf]))->assertRedirect(route('batches.show', $batch));
    $this->get(route('batches.show', $batch))->assertInertia(fn (Assert $page) => $page->where('print.pdf', null));

    $this->get(route('admin.batches.pdfs.download', [$batch, $pdf]))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('a suspended business can download but not print', function () {
    [$user, $organization, $batch] = printableBatch();
    $this->actingAs($user)->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetLetter->value]);
    $pdf = CardBatchPdf::sole();

    $organization->update(['status' => 'suspended']);

    $this->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetLetter->value])->assertRedirect();
    expect(CardBatchPdf::sole()->is($pdf))->toBeTrue();

    $this->get(route('batches.pdfs.download', [$batch, $pdf]))->assertOk();
});

test('expired PDFs are refused and pruned with their files', function () {
    [$user, , $batch] = printableBatch();
    $this->actingAs($user)->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetLetter->value]);
    $pdf = CardBatchPdf::sole();

    expect($pdf->expires_at->diffInHours($pdf->ready_at))->toEqual(-CardBatchPdf::EXPIRES_AFTER_HOURS);

    $this->travel(CardBatchPdf::EXPIRES_AFTER_HOURS)->hours();
    $this->travel(1)->minutes();

    $this->get(route('batches.pdfs.download', [$batch, $pdf]))
        ->assertRedirect(route('batches.show', $batch))
        ->assertSessionHasErrors('batch');
    $this->get(route('batches.show', $batch))->assertInertia(fn (Assert $page) => $page->where('print.pdf', null));

    // A newer PDF of another batch is kept.
    [$other, , $otherBatch] = printableBatch(1);
    app(CardBatchPrinter::class)->request($otherBatch, CardTemplate::SheetA4, $other, TemplateAudience::Business);
    $kept = CardBatchPdf::query()->whereKeyNot($pdf->id)->sole();

    $this->artisan('model:prune', ['--model' => CardBatchPdf::class])->assertSuccessful();

    expect(CardBatchPdf::query()->pluck('id')->all())->toBe([$kept->id]);
    Storage::disk(CardBatchPdf::DISK)->assertMissing($pdf->path);
    Storage::disk(CardBatchPdf::DISK)->assertExists($kept->path);
});

test('each generation and download is logged', function () {
    [$user, , $batch] = printableBatch(2);
    $admin = superadmin();

    $this->actingAs($user)->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetLetter->value]);
    $this->get(route('batches.pdfs.download', [$batch, CardBatchPdf::sole()]))->assertOk();

    $this->actingAs($admin)->post(route('admin.batches.pdfs.store', $batch), ['template' => CardTemplate::PrintShop->value]);

    $logs = CardBatchPdfLog::query()->orderBy('id')->get();

    expect($logs->map(fn (CardBatchPdfLog $log) => [
        $log->action, $log->user_id, $log->card_batch_id, $log->template, $log->cards, $log->by_admin,
    ])->all())->toBe([
        [CardBatchPdfAction::Generated, $user->id, $batch->id, CardTemplate::SheetLetter, 2, false],
        [CardBatchPdfAction::Downloaded, $user->id, $batch->id, CardTemplate::SheetLetter, 2, false],
        [CardBatchPdfAction::Generated, $admin->id, $batch->id, CardTemplate::PrintShop, 2, true],
    ])->and($logs->every(fn (CardBatchPdfLog $log) => $log->created_at !== null))->toBeTrue();

    // The business page shows the log, without naming Yoiful staff.
    $this->actingAs($user)->get(route('batches.show', $batch))
        ->assertInertia(fn (Assert $page) => $page
            ->has('print.logs', 3)
            ->where('print.logs.0.action', 'generated')
            ->where('print.logs.0.user', null)
            ->where('print.logs.0.by_admin', true)
            ->where('print.logs.1.action', 'downloaded')
            ->where('print.logs.1.user', $user->name));

    $this->actingAs($admin)->get(route('admin.batches.show', $batch))
        ->assertInertia(fn (Assert $page) => $page->where('print.logs.0.user', $admin->name));
});

test('the card text follows the language of the request', function () {
    [$user, , $batch] = printableBatch(1);
    $user->forceFill(['locale' => 'es'])->save();

    $this->actingAs($user)->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetLetter->value]);

    $pdf = CardBatchPdf::sole();
    expect($pdf->locale)->toBe('es');

    Mail::assertSent(CardBatchPdfReadyMail::class, fn (CardBatchPdfReadyMail $mail) => $mail->locale === 'es');

    // The browser's language when the user never chose one.
    $user->forceFill(['locale' => null])->save();
    $this->withHeader('Accept-Language', 'es-MX')->post(route('batches.pdfs.store', $batch), ['template' => CardTemplate::SheetLetter->value]);

    expect(CardBatchPdf::sole()->locale)->toBe('es');
});

test('the logo is embedded only when this app stored it', function () {
    [$user, $organization, $batch] = printableBatch(1);
    Storage::disk('public')->put('logos/'.$organization->id.'/logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

    $render = fn () => app(CardBatchPdfRenderer::class)->render($batch->fresh(), CardTemplate::SheetLetter, $batch->cards()->get())['pdf'];

    $organization->update(['logo_url' => Storage::disk('public')->url('logos/'.$organization->id.'/logo.png')]);
    expect($render())->toContain('/Subtype /Image');

    // A logo hosted elsewhere is never fetched.
    $organization->update(['logo_url' => 'https://example.com/logo.png']);
    expect($render())->not->toContain('/Subtype /Image');
});

test('ink on the brand color is black or white, whichever reads better', function () {
    expect(CardBatchPdfRenderer::inkOn('#000000'))->toBe('#ffffff')
        ->and(CardBatchPdfRenderer::inkOn('#fff'))->toBe('#111111')
        ->and(CardBatchPdfRenderer::inkOn('#f5c400'))->toBe('#111111')
        ->and(CardBatchPdfRenderer::inkOn('#1d4ed8'))->toBe('#ffffff');
});
