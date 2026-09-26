<?php

use App\Enums\MembershipRole;
use App\Enums\OrganizationStatus;
use App\Enums\TransactionType;
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
        ->and($first['organizations'])->toBe(3)
        ->and(User::query()->where('email', DemoSeeder::DEMO_EMAIL)->count())->toBe(1);

    foreach (Card::all() as $card) {
        $running = '0.00';

        $transactions = $card->transactions()->oldest()->oldest('id')->get();

        foreach ($transactions as $transaction) {
            $signed = $transaction->type === TransactionType::Spend
                ? bcmul((string) $transaction->amount, '-1', 2)
                : (string) $transaction->amount;
            $running = bcadd($running, $signed, 2);

            expect((string) $transaction->balance_after)->toBe($running);
        }

        expect((string) $card->balance)->toBe($running);
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
        ->and(Organization::count())->toBe(3)
        ->and(User::query()->where('email', DemoSeeder::DEMO_EMAIL)->sole()->name)->toBe('Armando Sosa');
});

test('demo seeder refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    app(DemoSeeder::class)->run();
})->throws(RuntimeException::class, 'DemoSeeder refuses to run in production.');
