<?php

use App\Enums\CardStatus;
use App\Enums\MembershipRole;
use App\Exceptions\CardBatchException;
use App\Models\Card;
use App\Models\CardBatch;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use App\Services\CardBatchIssuer;
use App\Services\CardCodeGenerator;
use App\Services\CardLedger;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Card batches (ARM-357): preissued inactive cards made in bulk for printing.
| The cap on unactivated stock is checked under the organization lock; the
| concurrent case is in tests/Feature/Ledger/LedgerConcurrencyTest.php.
*/

/**
 * An owner of a business that can preissue cards.
 *
 * @param  array<string, mixed>  $organization
 * @return array{0: User, 1: Organization, 2: Program}
 */
function preissuer(array $organization = []): array
{
    return cardOwner(['can_preissue' => true, ...$organization]);
}

test('owner creates a batch of inactive cards', function () {
    [$user, $organization, $program] = preissuer();

    $response = $this->actingAs($user)->post(route('batches.store'), ['count' => 5]);

    $batch = CardBatch::sole();

    $response->assertRedirect(route('batches.show', $batch))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'code' => 'batch.created',
            'params' => ['count' => '5', 'beyond' => '0'],
        ]);

    expect($batch->organization_id)->toBe($organization->id)
        ->and($batch->program_id)->toBe($program->id)
        ->and($batch->count)->toBe(5)
        ->and($batch->created_by)->toBe($user->id)
        ->and($batch->issued_by_admin)->toBeFalse()
        ->and($batch->notes)->toBeNull()
        ->and($batch->template)->toBeNull()
        ->and($batch->voided_at)->toBeNull();

    $cards = $batch->cards()->get();
    expect($cards)->toHaveCount(5)
        ->and($cards->every(fn (Card $card) => $card->status === CardStatus::Inactive && $card->balance === '0.00'))->toBeTrue()
        ->and($cards->every(fn (Card $card) => str_starts_with($card->code, CardCodeGenerator::CODE_PREFIX)))->toBeTrue()
        ->and($cards->pluck('code')->unique())->toHaveCount(5)
        ->and($organization->cardUsage())->toMatchArray(['used' => 0, 'stock' => 5]);

    // Cards created one at a time keep batch_id null.
    $this->post(route('cards.store'), ['initial_balance' => '0']);
    expect(Card::query()->whereNull('batch_id')->count())->toBe(1);
});

test('a batch that would pass the preissue limit is refused', function () {
    [$user, $organization, $program] = preissuer(['preissue_limit' => 10]);
    Card::factory()->count(4)->for($program)->inactive()->create();
    // Active and cancelled cards are not stock.
    Card::factory()->count(3)->for($program)->create();
    Card::factory()->for($program)->create(['status' => CardStatus::Cancelled]);

    $this->actingAs($user)
        ->from(route('batches.index'))
        ->post(route('batches.store'), ['count' => 7])
        ->assertRedirect(route('batches.index'))
        ->assertSessionHasErrors(['count' => 'This business can hold at most 10 cards in stock. There is room for 6 more.']);

    expect(CardBatch::count())->toBe(0)
        ->and(Card::count())->toBe(8);

    // Exactly up to the limit is fine.
    $this->post(route('batches.store'), ['count' => 6])->assertSessionHasNoErrors();
    expect($organization->cardUsage()['stock'])->toBe(10);

    // Full: not even one more.
    $this->post(route('batches.store'), ['count' => 1])
        ->assertSessionHasErrors(['count' => 'This business can hold at most 10 cards in stock. There is room for 0 more.']);
});

test('the preissue limit: null is unlimited and 0 allows no stock', function () {
    [$user, $organization] = preissuer(['preissue_limit' => null]);
    $issuer = app(CardBatchIssuer::class);

    $issuer->issue($organization, CardBatchIssuer::MAX_BATCH_SIZE, $user, byAdmin: false);
    expect($organization->cardUsage()['stock'])->toBe(CardBatchIssuer::MAX_BATCH_SIZE);

    $organization->update(['preissue_limit' => 0]);
    Card::query()->update(['status' => CardStatus::Cancelled]);

    expect(fn () => $issuer->issue($organization, 1, $user, byAdmin: false))
        ->toThrow(CardBatchException::class, 'This business can hold at most 0 cards in stock. There is room for 0 more.');
});

test('the batch size is limited', function () {
    [$user, $organization] = preissuer();
    $issuer = app(CardBatchIssuer::class);

    $this->actingAs($user);

    foreach ([CardBatchIssuer::MAX_BATCH_SIZE + 1, 0, -1, 1.5, 'many', ''] as $count) {
        $this->post(route('batches.store'), ['count' => $count])->assertSessionHasErrors('count');
    }

    expect(fn () => $issuer->issue($organization, CardBatchIssuer::MAX_BATCH_SIZE + 1, $user, byAdmin: false))
        ->toThrow(CardBatchException::class, 'A batch can have at most 1000 cards.')
        ->and(fn () => $issuer->issue($organization, 0, $user, byAdmin: false))
        ->toThrow(CardBatchException::class, 'A batch needs at least one card.')
        ->and(CardBatch::count())->toBe(0)
        ->and(Card::count())->toBe(0);
});

test('a batch is not blocked by the card limit, but warns when stock has no room', function () {
    [$user, $organization, $program] = preissuer(['card_limit' => 3]);
    Card::factory()->count(2)->for($program)->create();

    // Room for 1 more; 5 in stock means 4 can't be activated yet.
    $this->actingAs($user)
        ->post(route('batches.store'), ['count' => 5])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'code' => 'batch.created',
            'params' => ['count' => '5', 'beyond' => '4'],
        ]);

    expect($organization->cardUsage())->toMatchArray(['used' => 2, 'stock' => 5, 'atLimit' => false])
        ->and(CardBatchIssuer::stockBeyondRoom($organization))->toBe(4);

    $organization->update(['card_limit' => null]);
    expect(CardBatchIssuer::stockBeyondRoom($organization))->toBe(0);
});

test('a business without an active program cannot create a batch', function () {
    [$user, , $program] = preissuer();
    $program->update(['is_active' => false]);

    $this->actingAs($user)
        ->post(route('batches.store'), ['count' => 2])
        ->assertSessionHasErrors(['program' => 'This business has no active program to issue cards from.']);

    expect(Card::count())->toBe(0);
});

test('only owners and managers of a business that can preissue create batches', function () {
    [$owner, $organization] = cardOwner(['can_preissue' => false]);
    $manager = User::factory()->create();
    $employee = User::factory()->create();
    $organization->memberships()->create(['user_id' => $manager->id, 'role' => MembershipRole::Manager]);
    $organization->memberships()->create(['user_id' => $employee->id, 'role' => MembershipRole::Employee]);

    // can_preissue is off: nobody in the business sees or creates batches.
    foreach ([$owner, $manager, $employee] as $user) {
        $this->actingAs($user)->get(route('batches.index'))->assertForbidden();
        $this->post(route('batches.store'), ['count' => 1])->assertForbidden();
    }

    $organization->update(['can_preissue' => true]);

    $this->actingAs($employee)->get(route('batches.index'))->assertForbidden();
    $this->post(route('batches.store'), ['count' => 1])->assertForbidden();

    foreach ([$owner, $manager] as $user) {
        $this->actingAs($user)->get(route('batches.index'))->assertOk();
        $this->post(route('batches.store'), ['count' => 1])->assertSessionHasNoErrors();
    }

    expect(CardBatch::count())->toBe(2);

    // Suspended: the list stays readable, creating is refused.
    $organization->update(['status' => 'suspended']);
    $this->actingAs($owner)
        ->get(route('batches.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canCreate', false));
    $this->post(route('batches.store'), ['count' => 1])->assertSessionHasErrors('organization');

    expect(CardBatch::count())->toBe(2);
});

test('the shared props tell the sidebar whether the business can preissue', function () {
    [$user, $organization] = cardOwner();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('currentOrganization.can_preissue', false));

    $organization->update(['can_preissue' => true]);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('currentOrganization.can_preissue', true));
});

test('a superadmin can create a batch for a business that cannot preissue', function () {
    [, $organization, $program] = cardOwner(['can_preissue' => false, 'preissue_limit' => 100]);
    $admin = superadmin();

    $response = $this->actingAs($admin)
        ->post(route('admin.organizations.batches.store', $organization), [
            'count' => 25,
            'notes' => '  Printed 25 cards, charged $500  ',
        ]);

    $batch = CardBatch::sole();

    $response->assertRedirect(route('admin.batches.show', $batch))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.code', 'admin.batch_created');

    expect($batch->organization_id)->toBe($organization->id)
        ->and($batch->program_id)->toBe($program->id)
        ->and($batch->issued_by_admin)->toBeTrue()
        ->and($batch->created_by)->toBe($admin->id)
        ->and($batch->notes)->toBe('Printed 25 cards, charged $500')
        ->and($batch->cards()->where('status', CardStatus::Inactive)->count())->toBe(25)
        ->and($organization->refresh()->can_preissue)->toBeFalse();

    // Admins are held to the same preissue limit and batch size.
    $this->post(route('admin.organizations.batches.store', $organization), ['count' => 76])
        ->assertSessionHasErrors(['count' => 'This business can hold at most 100 cards in stock. There is room for 75 more.']);
    $this->post(route('admin.organizations.batches.store', $organization), ['count' => CardBatchIssuer::MAX_BATCH_SIZE + 1])
        ->assertSessionHasErrors('count');

    // And a suspended business takes no new stock.
    $organization->update(['status' => 'suspended']);
    $this->post(route('admin.organizations.batches.store', $organization), ['count' => 1])
        ->assertSessionHasErrors(['organization' => 'This business is suspended. Contact support.']);

    expect(CardBatch::count())->toBe(1);
});

test('the admin organization page lists batches and saves can_preissue', function () {
    [, $organization] = cardOwner(['card_limit' => 100]);
    $admin = superadmin();
    $batch = app(CardBatchIssuer::class)->issue($organization, 3, $admin, byAdmin: true, notes: 'Charged $90');
    app(CardLedger::class)->activate($batch->cards()->first(), '50', $admin);

    $this->actingAs($admin)
        ->get(route('admin.organizations.show', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->where('organization.can_preissue', false)
            ->where('maxBatchSize', CardBatchIssuer::MAX_BATCH_SIZE)
            ->has('batches', 1)
            ->where('batches.0.id', $batch->id)
            ->where('batches.0.count', 3)
            ->where('batches.0.activated', 1)
            ->where('batches.0.stock', 2)
            ->where('batches.0.created_by', $admin->name)
            ->where('batches.0.issued_by_admin', true)
            ->where('batches.0.notes', 'Charged $90'));

    $this->patch(route('admin.organizations.update', $organization), ['card_limit' => '100', 'can_preissue' => '1'])
        ->assertSessionHasNoErrors();
    expect($organization->refresh()->can_preissue)->toBeTrue();

    // An unchecked box sends nothing and turns it off.
    $this->patch(route('admin.organizations.update', $organization), ['card_limit' => '100'])
        ->assertSessionHasNoErrors();
    expect($organization->refresh()->can_preissue)->toBeFalse();

    $this->get(route('admin.batches.show', $batch))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/batches/show')
            ->where('organization.id', $organization->id)
            ->where('batch.notes', 'Charged $90')
            ->has('cards.data', 3));
});

test('the batches list and batch page show counts, creator, and cards', function () {
    [$user, $organization] = preissuer();
    $issuer = app(CardBatchIssuer::class);
    $first = $issuer->issue($organization, 3, $user, byAdmin: false);
    $this->travel(1)->minutes();
    $second = $issuer->issue($organization, 2, superadmin(), byAdmin: true, notes: 'Internal');
    app(CardLedger::class)->activate($first->cards()->first(), '100', $user);

    $this->actingAs($user)
        ->get(route('batches.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('batches/index')
            ->where('canCreate', true)
            ->where('maxBatchSize', CardBatchIssuer::MAX_BATCH_SIZE)
            ->where('usage.stock', 4)
            ->has('batches.data', 2)
            ->where('batches.data.0.id', $second->id)
            ->where('batches.data.0.issued_by_admin', true)
            ->missing('batches.data.0.notes')
            ->where('batches.data.1.id', $first->id)
            ->where('batches.data.1.count', 3)
            ->where('batches.data.1.activated', 1)
            ->where('batches.data.1.stock', 2)
            ->where('batches.data.1.created_by', $user->name)
            ->where('batches.data.1.created_at', $first->created_at->toIso8601String()));

    $this->get(route('batches.show', $first))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('batches/show')
            ->where('batch.id', $first->id)
            ->where('batch.activated', 1)
            ->where('canVoid', true)
            ->has('cards.data', 3)
            ->where('cards.data.0.batch_id', $first->id));

    // The card list filters by batch.
    $this->get(route('cards.index', ['batch' => $second->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.batch', $second->id)
            ->where('batch.id', $second->id)
            ->has('cards.data', 2));

    // An unknown or foreign batch is ignored.
    [$other, $otherOrganization] = preissuer();
    $foreign = $issuer->issue($otherOrganization, 1, $other, byAdmin: false);

    $this->get(route('cards.index', ['batch' => $foreign->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.batch', null)
            ->where('batch', null)
            ->has('cards.data', 5));

    $this->get(route('batches.show', $foreign))->assertForbidden();
});

test('voiding a batch cancels only the cards still inactive', function () {
    [$user, $organization] = preissuer(['preissue_limit' => 4]);
    $batch = app(CardBatchIssuer::class)->issue($organization, 4, $user, byAdmin: false);
    [$sold, $frozen] = $batch->cards()->orderBy('code')->get();
    app(CardLedger::class)->activate($sold, '100', $user);
    app(CardLedger::class)->activate($frozen, '100', $user);
    $frozen->update(['status' => CardStatus::Frozen]);

    $this->actingAs($user)
        ->from(route('batches.show', $batch))
        ->post(route('batches.void', $batch))
        ->assertRedirect(route('batches.show', $batch))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'code' => 'batch.voided',
            'params' => ['count' => '2'],
        ]);

    expect($batch->refresh()->voided_at)->not->toBeNull()
        ->and($sold->fresh()->status)->toBe(CardStatus::Active)
        ->and($sold->fresh()->balance)->toBe('100.00')
        ->and($frozen->fresh()->status)->toBe(CardStatus::Frozen)
        ->and($batch->cards()->where('status', CardStatus::Cancelled)->count())->toBe(2)
        ->and($batch->cards()->where('status', CardStatus::Inactive)->count())->toBe(0);

    // Voided cards stop counting toward the preissue limit.
    expect($organization->cardUsage()['stock'])->toBe(0);
    $this->post(route('batches.store'), ['count' => 4])->assertSessionHasNoErrors();

    // A voided card can't be activated; the batch can't be voided twice.
    $voided = $batch->cards()->where('status', CardStatus::Cancelled)->first();
    $this->post(route('cards.activate', $voided), ['amount' => '10'])
        ->assertSessionHasErrors(['card' => 'This card is cancelled.']);

    $this->from(route('batches.show', $batch))
        ->post(route('batches.void', $batch))
        ->assertSessionHasErrors(['batch' => 'This batch is already voided.']);
});

test('voiding a batch follows the batch permissions', function () {
    [$owner, $organization] = preissuer();
    $employee = User::factory()->create();
    $organization->memberships()->create(['user_id' => $employee->id, 'role' => MembershipRole::Employee]);
    $batch = app(CardBatchIssuer::class)->issue($organization, 2, $owner, byAdmin: false);
    [$outsider] = preissuer();

    $this->actingAs($employee)->post(route('batches.void', $batch))->assertForbidden();
    $this->actingAs($outsider)->post(route('batches.void', $batch))->assertForbidden();

    $organization->update(['can_preissue' => false]);
    $this->actingAs($owner)->post(route('batches.void', $batch))->assertForbidden();

    expect($batch->refresh()->voided_at)->toBeNull();

    // A superadmin can void it for the business.
    $this->actingAs(superadmin())
        ->post(route('admin.batches.void', $batch))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.code', 'batch.voided');

    expect($batch->refresh()->voided_at)->not->toBeNull()
        ->and($batch->cards()->where('status', CardStatus::Cancelled)->count())->toBe(2);
});

test('owners and managers can void a single unactivated card from its page', function () {
    // Works whether or not the business creates batches itself.
    [$owner, $organization, $program] = cardOwner(['can_preissue' => false]);
    $employee = User::factory()->create();
    $organization->memberships()->create(['user_id' => $employee->id, 'role' => MembershipRole::Employee]);
    $batch = app(CardBatchIssuer::class)->issue($organization, 2, superadmin(), byAdmin: true);
    $lost = $batch->cards()->first();
    $active = Card::factory()->for($program)->create();

    $this->actingAs($owner)
        ->get(route('cards.show', $lost))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canVoid', true)
            ->where('canViewBatch', false)
            ->where('card.batch_id', $batch->id));

    $this->actingAs($employee)
        ->get(route('cards.show', $lost))
        ->assertInertia(fn (Assert $page) => $page->where('canVoid', false));
    $this->post(route('cards.void', $lost))->assertForbidden();

    $this->actingAs($owner)
        ->from(route('cards.show', $lost))
        ->post(route('cards.void', $lost))
        ->assertRedirect(route('cards.show', $lost))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'code' => 'card.voided',
            'params' => ['code' => $lost->code],
        ]);

    expect($lost->fresh()->status)->toBe(CardStatus::Cancelled)
        ->and($organization->cardUsage()['stock'])->toBe(1)
        ->and($batch->refresh()->voided_at)->toBeNull();

    // Only an inactive card can be voided.
    foreach ([$lost, $active] as $card) {
        $this->from(route('cards.show', $card))
            ->post(route('cards.void', $card))
            ->assertSessionHasErrors(['status' => 'Only a card that is not activated yet can be voided.']);
    }

    expect($active->fresh()->status)->toBe(CardStatus::Active);

    $organization->update(['can_preissue' => true]);
    $this->get(route('cards.show', $active))
        ->assertInertia(fn (Assert $page) => $page->where('canVoid', false)->where('canViewBatch', false));
    $this->get(route('cards.show', $lost))
        ->assertInertia(fn (Assert $page) => $page->where('canVoid', false)->where('canViewBatch', true));
});

test('voided stock never takes a slot under the card limit', function () {
    [$user, $organization, $program] = preissuer(['card_limit' => 3]);
    Card::factory()->for($program)->create(['status' => CardStatus::Cancelled]);
    $batch = app(CardBatchIssuer::class)->issue($organization, 5, $user, byAdmin: false);
    [$sold, $lost] = $batch->cards()->orderBy('code')->get();
    app(CardLedger::class)->activate($sold, '100', $user);

    // A card cancelled after it was in use still counts; the sold card too.
    expect($organization->cardUsage())->toMatchArray(['used' => 2, 'stock' => 4]);

    $issuer = app(CardBatchIssuer::class);
    $issuer->voidCard($lost);
    expect($organization->cardUsage())->toMatchArray(['used' => 2, 'stock' => 3]);

    $issuer->void($batch);
    expect($organization->cardUsage())->toMatchArray(['used' => 2, 'stock' => 0, 'atLimit' => false])
        ->and(CardBatchIssuer::stockBeyondRoom($organization))->toBe(0);

    // A sold card that is cancelled later keeps its slot.
    $sold->update(['status' => CardStatus::Cancelled]);
    expect($organization->cardUsage()['used'])->toBe(2);

    // The last slot is still free for a new card.
    $this->actingAs($user)
        ->post(route('cards.store'), ['initial_balance' => '0', 'email' => ''])
        ->assertSessionHasNoErrors();

    expect($organization->cardUsage())->toMatchArray(['used' => 3, 'atLimit' => true]);

    $this->actingAs(superadmin())
        ->get(route('admin.organizations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('organizations.data.0.cards_count', 3));
});
