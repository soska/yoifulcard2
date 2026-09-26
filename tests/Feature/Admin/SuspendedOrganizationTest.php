<?php

use App\Enums\MembershipRole;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('members of a suspended organization see the banner and can still read data', function () {
    $employee = User::factory()->create();
    [$owner, $organization, $program] = cardOwner();
    $organization->memberships()->create(['user_id' => $employee->id, 'role' => MembershipRole::Employee]);
    $card = Card::factory()->for($program)->create(['balance' => '40.00']);
    Transaction::factory()->for($card)->create(['performed_by' => $owner->id]);

    // A superadmin suspends the business.
    $this->actingAs(superadmin())
        ->post(route('admin.organizations.suspend', $organization))
        ->assertSessionHasNoErrors();

    $pages = [
        route('dashboard') => 'dashboard',
        route('cards.index') => 'cards/index',
        route('cards.show', $card) => 'cards/show',
        route('transactions.index') => 'transactions/index',
        route('analytics') => 'analytics/index',
        route('settings.edit') => 'settings/business',
        route('profile.edit') => 'settings/profile',
        route('scan') => 'reader/scan',
        route('scan.cards.show', $card) => 'reader/card',
    ];

    foreach ([$owner, $employee] as $member) {
        foreach ($pages as $url => $component) {
            // The layouts render the banner from the shared prop's status.
            $this->actingAs($member)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where('currentOrganization.id', $organization->id)
                    ->where('currentOrganization.status', 'suspended'));
        }
    }

    // Reading still works: the list has the card, and the CSV exports.
    $this->actingAs($owner)
        ->get(route('cards.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('cards.data', 1)
            ->where('cards.data.0.balance', '40.00'));

    $this->actingAs($owner)
        ->get(route('transactions.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    // Writes are refused whatever the UI shows.
    $this->actingAs($owner)
        ->post(route('cards.spend', $card), ['amount' => '5.00'])
        ->assertSessionHasErrors('organization');

    $this->actingAs($owner)
        ->post(route('cards.store'), ['initial_balance' => '0'])
        ->assertSessionHasErrors('organization');

    expect($card->refresh()->balance)->toBe('40.00')
        ->and($organization->cards()->count())->toBe(1);

    // Reactivated, the banner's source status is active again.
    $this->actingAs(superadmin())
        ->post(route('admin.organizations.reactivate', $organization));

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('currentOrganization.status', 'active'));
});

test('the dashboard, settings and reader layouts render the suspended banner', function (string $layout) {
    $source = file_get_contents(resource_path("js/layouts/{$layout}"));

    expect($source)
        ->toContain("import { SuspendedBanner } from '@/components/organization/suspended-banner';")
        ->toContain('<SuspendedBanner');
})->with([
    'app/app-sidebar-layout.tsx',
    'app/app-header-layout.tsx',
    'reader-layout.tsx',
]);

test('the suspended banner has the required text and cannot be dismissed', function () {
    $source = file_get_contents(resource_path('js/components/organization/suspended-banner.tsx'));

    expect($source)
        ->toContain('This business is suspended. Contact support.')
        ->toContain("currentOrganization.status === 'active'")
        ->not->toContain('onClose')
        ->not->toContain('useState');
});

test('the public card page still shows the balance of a suspended organization', function () {
    $organization = Organization::factory()->create();
    $program = Program::factory()->for($organization)->create();
    $card = Card::factory()->for($program)->create(['balance' => '15.00']);

    $this->actingAs(superadmin())->post(route('admin.organizations.suspend', $organization));

    $this->get(route('public-card.show', $card->qr_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('card.balance', '15.00')
            ->missing('organization.status'));
});
