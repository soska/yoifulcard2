<?php

use App\Enums\EarnMode;
use App\Enums\MembershipRole;
use App\Enums\OrganizationStatus;
use App\Enums\TransactionType;
use App\Enums\TransactionUnit;
use App\Models\Card;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DemoSeeder;

test('demo seeder builds consistent data and is idempotent', function () {
    $this->seed(DemoSeeder::class);

    $counts = fn () => [
        'users' => User::count(),
        'organizations' => Organization::count(),
        'memberships' => Membership::count(),
        'cards' => Card::count(),
        'transactions' => Transaction::count(),
    ];
    $first = $counts();

    $this->seed(DemoSeeder::class);

    expect($counts())->toBe($first)
        ->and($first['organizations'])->toBe(5)
        ->and(User::query()->where('email', DemoSeeder::DEMO_EMAIL)->count())->toBe(1);

    foreach (Card::all() as $card) {
        $running = '0.00';
        $points = '0.00';

        $transactions = $card->transactions()->oldest()->oldest('id')->get();

        foreach ($transactions as $transaction) {
            if ($transaction->unit === TransactionUnit::Points) {
                $signed = in_array($transaction->type, [TransactionType::Redeem, TransactionType::Expire], true)
                    ? bcmul((string) $transaction->amount, '-1', 2)
                    : (string) $transaction->amount;
                $points = bcadd($points, $signed, 2);

                expect((string) $transaction->balance_after)->toBe($points);

                continue;
            }

            $signed = $transaction->type === TransactionType::Spend
                ? bcmul((string) $transaction->amount, '-1', 2)
                : (string) $transaction->amount;
            $running = bcadd($running, $signed, 2);

            expect((string) $transaction->balance_after)->toBe($running);
        }

        expect((string) $card->balance)->toBe($running)
            ->and($card->points)->toBe((int) $points);
    }

    $organizations = Organization::with('memberships.user')->get()->keyBy('name');

    $roles = fn (string $name) => $organizations[$name]->memberships
        ->mapWithKeys(fn (Membership $m) => [$m->user->email => $m->role])
        ->all();

    expect($organizations['Café La Esquina']->status)->toBe(OrganizationStatus::Active)
        ->and($organizations['Panadería Dulce Hogar']->status)->toBe(OrganizationStatus::Active)
        ->and($organizations['Bicis del Norte']->status)->toBe(OrganizationStatus::Suspended)
        ->and($roles('Café La Esquina'))->toBe([DemoSeeder::DEMO_EMAIL => MembershipRole::Owner])
        ->and($roles('Panadería Dulce Hogar'))->toEqualCanonicalizing([
            DemoSeeder::OWNER2_EMAIL => MembershipRole::Owner,
            DemoSeeder::DEMO_EMAIL => MembershipRole::Manager,
            DemoSeeder::EMPLOYEE_EMAIL => MembershipRole::Employee,
        ])
        ->and($roles('Bicis del Norte'))->toBe([DemoSeeder::SUSPENDED_EMAIL => MembershipRole::Owner])
        ->and($organizations['Café Cometa']->status)->toBe(OrganizationStatus::Active)
        ->and($organizations['Loto Yoga Studio']->status)->toBe(OrganizationStatus::Active)
        ->and($roles('Café Cometa'))->toEqualCanonicalizing([
            DemoSeeder::COMETA_EMAIL => MembershipRole::Owner,
            DemoSeeder::DEMO_EMAIL => MembershipRole::Manager,
            DemoSeeder::EMPLOYEE_EMAIL => MembershipRole::Employee,
        ])
        ->and($roles('Loto Yoga Studio'))->toEqualCanonicalizing([
            DemoSeeder::YOGA_EMAIL => MembershipRole::Owner,
            DemoSeeder::DEMO_EMAIL => MembershipRole::Manager,
        ])
        ->and($organizations['Panadería Dulce Hogar']->cardUsage()['percent'])->toBeGreaterThanOrEqual(80)
        ->and(User::query()->where('email', DemoSeeder::DEMO_EMAIL)->firstOrFail()->isSuperadmin())->toBeTrue();

    foreach ($organizations as $organization) {
        expect($organization->cards()->count())->toBeGreaterThanOrEqual(8)
            ->and($organization->currency)->toBe('MXN')
            ->and($organization->timezone)->toBe('America/Mexico_City');
    }
});

test('demo seeder renames the demo account\'s existing business', function () {
    $this->post(route('register.store'), [
        'name' => 'Armando Test',
        'email' => DemoSeeder::DEMO_EMAIL,
        'password' => DemoSeeder::PASSWORD,
        'password_confirmation' => DemoSeeder::PASSWORD,
    ]);
    $organization = Organization::query()->sole();

    $this->seed(DemoSeeder::class);

    expect($organization->refresh()->name)->toBe('Café La Esquina')
        ->and(Organization::count())->toBe(5)
        ->and(User::query()->where('email', DemoSeeder::DEMO_EMAIL)->sole()->name)->toBe('Armando Sosa');
});

test('demo seeder sets up the two rewards businesses', function () {
    $this->seed(DemoSeeder::class);

    $cometa = Organization::query()->where('name', 'Café Cometa')->firstOrFail()->defaultProgram();
    $yoga = Organization::query()->where('name', 'Loto Yoga Studio')->firstOrFail()->defaultProgram();

    expect($cometa->rewards_enabled)->toBeTrue()
        ->and($cometa->earn_mode)->toBe(EarnMode::Spend)
        ->and($cometa->earn_rate)->toBe('0.10')
        ->and($cometa->points_expiry_months)->toBe(1)
        ->and($cometa->rewards()->active()->count())->toBe(3)
        ->and($cometa->rewards()->where('is_active', false)->count())->toBe(1)
        ->and($yoga->rewards_enabled)->toBeTrue()
        ->and($yoga->earn_mode)->toBe(EarnMode::Visits)
        ->and($yoga->points_expiry_months)->toBe(3)
        ->and($yoga->rewards()->active()->count())->toBe(3);

    // The other businesses stay prepaid-only.
    foreach (['Café La Esquina', 'Panadería Dulce Hogar', 'Bicis del Norte'] as $name) {
        expect(Organization::query()->where('name', $name)->firstOrFail()->defaultProgram()->rewards_enabled)->toBeFalse();
    }

    foreach ([$cometa, $yoga] as $program) {
        $cards = $program->cards;
        $types = Transaction::query()->whereIn('card_id', $cards->modelKeys())->pluck('type')->unique();

        expect($cards->where('points', '>', 0)->count())->toBeGreaterThanOrEqual(4)
            // Rewards-only cards that never held money, and cards that did.
            ->and($cards->filter(fn (Card $card) => $card->transactions()->where('unit', 'money')->doesntExist() && $card->points > 0)->count())->toBeGreaterThanOrEqual(1)
            ->and($cards->filter(fn (Card $card) => $card->transactions()->where('unit', 'money')->exists() && $card->points > 0)->count())->toBeGreaterThanOrEqual(1)
            ->and($types->contains(TransactionType::Earn))->toBeTrue()
            ->and($types->contains(TransactionType::Redeem))->toBeTrue();
    }

    // The cafe has been through an expiry, run by the system.
    $expired = Transaction::query()->where('type', TransactionType::Expire)->get();

    expect($expired)->toHaveCount(1)
        ->and($expired->first()->performed_by)->toBeNull()
        ->and($expired->first()->card->program_id)->toBe($cometa->id);
});

test('the cafe has one card idle past its expiry for the demo', function () {
    $this->seed(DemoSeeder::class);

    $this->artisan('rewards:expire-points')
        ->expectsOutput('Expired the points of 1 card.')
        ->assertSuccessful();

    $expired = Transaction::query()->where('type', TransactionType::Expire)->latest('created_at')->firstOrFail();

    expect($expired->amount)->toBe('40.00')
        ->and($expired->card->fresh()->points)->toBe(0);
});

test('demo seeder refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    app(DemoSeeder::class)->run();
})->throws(RuntimeException::class, 'DemoSeeder refuses to run in production.');
