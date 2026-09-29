<?php

namespace Database\Seeders;

use App\Enums\CardStatus;
use App\Enums\MembershipRole;
use App\Enums\OrganizationStatus;
use App\Enums\ProgramType;
use App\Models\Card;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Superadmin;
use App\Models\User;
use App\Services\CardCodeGenerator;
use App\Services\CardLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demo data for local development: three businesses, their people, and
 * cards with about 60 days of ledger history.
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * Every login uses the password DemoSeeder::PASSWORD:
 *
 * - armando@yoiful.test: superadmin, owner of Café La Esquina, manager at
 *   Panadería Dulce Hogar
 * - owner2@yoiful.test: owner of Panadería Dulce Hogar (card usage above 80%)
 * - employee@yoiful.test: employee at Panadería Dulce Hogar
 * - suspended@yoiful.test: owner of Bicis del Norte, which is suspended
 *
 * Idempotency: users are found or created by email. Each business is the
 * owner's oldest owned organization, so the organization that registration
 * made for armando@yoiful.test is renamed rather than duplicated; missing
 * businesses are created. Memberships and the superadmin row are found or
 * created. Cards are only seeded for a business that has no cards yet, so a
 * second run leaves cards and transactions untouched. Existing passwords are
 * never reset.
 *
 * Every balance change goes through CardLedger, with the clock moved back
 * to the time of each operation, so balances always match the transaction
 * history. Cards are frozen or cancelled after their transactions, and the
 * suspended business is suspended after its activity.
 *
 * @phpstan-type CardOp array{0: 'load'|'spend'|'adjust', 1: string, 2: int, 3?: string}
 * @phpstan-type CardSpec array{email?: string, opened: int, ops?: list<CardOp>, status?: CardStatus, showcase?: string}
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'yoiful-test-123';

    public const DEMO_EMAIL = 'armando@yoiful.test';

    public const OWNER2_EMAIL = 'owner2@yoiful.test';

    public const EMPLOYEE_EMAIL = 'employee@yoiful.test';

    public const SUSPENDED_EMAIL = 'suspended@yoiful.test';

    private Carbon $now;

    /**
     * Public URLs to print at the end.
     *
     * @var list<array{string, string, string}>
     */
    private array $showcase = [];

    public function __construct(
        private CardLedger $ledger,
        private CardCodeGenerator $generator,
    ) {}

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder refuses to run in production.');
        }

        $this->now = Carbon::now();

        try {
            $demo = $this->user(self::DEMO_EMAIL, 'Armando Sosa');
            $owner2 = $this->user(self::OWNER2_EMAIL, 'Lucía Hernández');
            $employee = $this->user(self::EMPLOYEE_EMAIL, 'Jorge Ramírez');
            $suspendedOwner = $this->user(self::SUSPENDED_EMAIL, 'Rodrigo Treviño');

            Superadmin::query()->firstOrCreate(['user_id' => $demo->id], ['granted_at' => $this->now]);

            $cafe = $this->business($demo, [
                'name' => 'Café La Esquina',
                'primary_color' => '#7B4B2A',
                'card_limit' => 50,
            ]);

            $panaderia = $this->business($owner2, [
                'name' => 'Panadería Dulce Hogar',
                'primary_color' => '#D97706',
                'card_limit' => 14,
            ]);
            $this->member($panaderia, $demo, MembershipRole::Manager);
            $this->member($panaderia, $employee, MembershipRole::Employee);

            $bicis = $this->business($suspendedOwner, [
                'name' => 'Bicis del Norte',
                'primary_color' => '#0F766E',
                'card_limit' => 30,
            ]);

            $this->cards($cafe, [$demo], $this->cafeCards());
            $this->cards($panaderia, [$owner2, $employee, $demo], $this->panaderiaCards());
            $this->cards($bicis, [$suspendedOwner], $this->bicisCards());

            $bicis->update(['status' => OrganizationStatus::Suspended]);
        } finally {
            Carbon::setTestNow();
        }

        $this->report();
    }

    private function user(string $email, string $name): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => self::PASSWORD],
        );

        if ($user->name !== $name) {
            $user->update(['name' => $name]);
        }

        return $user;
    }

    /**
     * Find the owner's oldest owned organization, or create one, and give it
     * the demo name and settings. Makes sure it has a Gift Card program.
     *
     * @param  array{name: string, primary_color: string, card_limit: int}  $attributes
     */
    private function business(User $owner, array $attributes): Organization
    {
        $membership = $owner->memberships()
            ->where('role', MembershipRole::Owner)
            ->oldest()
            ->oldest('id')
            ->first();

        $settings = [
            ...$attributes,
            'currency' => 'MXN',
            'timezone' => 'America/Mexico_City',
        ];

        if ($membership === null) {
            Carbon::setTestNow($this->now->copy()->subDays(62));

            $organization = Organization::query()->create([
                ...$settings,
                'slug' => Organization::uniqueSlug($attributes['name']),
                'status' => OrganizationStatus::Active,
            ]);

            Carbon::setTestNow();

            $this->member($organization, $owner, MembershipRole::Owner);
        } else {
            /** @var Organization $organization */
            $organization = $membership->organization;

            $organization->fill($settings);

            $slug = Str::slug($attributes['name']);

            if ($organization->slug !== $slug && ! Organization::query()->where('slug', $slug)->exists()) {
                $organization->slug = $slug;
            }

            $organization->save();
        }

        if (! $organization->programs()->exists()) {
            $organization->programs()->create([
                'name' => __('Gift Card'),
                'type' => ProgramType::Prepaid,
            ]);
        }

        return $organization;
    }

    private function member(Organization $organization, User $user, MembershipRole $role): Membership
    {
        return Membership::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            ['role' => $role],
        );
    }

    /**
     * Create the business's cards and their history, unless it has cards
     * already. Operations are performed by the given people in turn.
     *
     * @param  list<User>  $people
     * @param  list<CardSpec>  $specs
     */
    private function cards(Organization $organization, array $people, array $specs): void
    {
        if ($organization->cards()->exists()) {
            return;
        }

        /** @var Program $program */
        $program = $organization->defaultProgram();

        foreach ($specs as $index => $spec) {
            $this->travel($spec['opened'], $index);

            $card = $program->cards()->create([
                'code' => $this->generator->code(),
                'qr_token' => $this->generator->qrToken(),
                'balance' => '0.00',
                'status' => CardStatus::Active,
                'email' => $spec['email'] ?? null,
            ]);

            foreach ($spec['ops'] ?? [] as $step => [$type, $amount, $daysAgo]) {
                $this->travel($daysAgo, $index + $step);
                $person = $people[($index + $step) % count($people)];
                $note = $spec['ops'][$step][3] ?? null;

                match ($type) {
                    'load' => $this->ledger->load($card, $amount, $person, $note),
                    'spend' => $this->ledger->spend($card, $amount, $person, $note),
                    'adjust' => $this->ledger->adjust($card, $amount, $person, $note),
                };
            }

            if (isset($spec['status'])) {
                $card->update(['status' => $spec['status']]);
            }

            if (isset($spec['showcase'])) {
                $this->showcase[] = [$organization->name, $spec['showcase'], $card->qrPayload()];
            }
        }

        Carbon::setTestNow();
    }

    /**
     * Move the clock to a business-hours time, in Mexico City, some days ago.
     */
    private function travel(int $daysAgo, int $salt): void
    {
        $local = $this->now->copy()
            ->setTimezone('America/Mexico_City')
            ->subDays($daysAgo)
            ->setTime(9 + ($salt * 5) % 11, ($salt * 17) % 60);

        $moment = $local->utc();

        Carbon::setTestNow($moment->greaterThan($this->now) ? $this->now->copy() : $moment);
    }

    private function report(): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->info('Demo data ready. Password for every login: '.self::PASSWORD);
        $this->command->table(['Login', 'Access'], [
            [self::DEMO_EMAIL, 'superadmin, owner of Café La Esquina, manager at Panadería Dulce Hogar'],
            [self::OWNER2_EMAIL, 'owner of Panadería Dulce Hogar (card usage above 80%)'],
            [self::EMPLOYEE_EMAIL, 'employee at Panadería Dulce Hogar'],
            [self::SUSPENDED_EMAIL, 'owner of Bicis del Norte (suspended)'],
        ]);

        if ($this->showcase !== []) {
            $this->command->table(['Business', 'Card', 'Public URL'], $this->showcase);
        } else {
            $showcase = Card::query()
                ->whereIn('program_id', Program::query()->select('id'))
                ->where('status', CardStatus::Active)
                ->orderByDesc('balance')
                ->limit(3)
                ->get()
                ->map(fn (Card $card) => [$card->code, $card->balance, $card->qrPayload()])
                ->all();

            $this->command->table(['Card', 'Balance', 'Public URL'], $showcase);
        }
    }

    /**
     * @return list<CardSpec>
     */
    private function cafeCards(): array
    {
        return [
            ['email' => 'mariana.lopez@example.com', 'opened' => 58, 'showcase' => 'large balance', 'ops' => [
                ['load', '5000.00', 58, 'Regalo corporativo'],
                ['spend', '1250.00', 44],
                ['spend', '890.00', 26],
                ['spend', '430.00', 9],
                ['spend', '215.00', 1],
            ]],
            ['opened' => 55, 'ops' => [
                ['load', '2000.00', 55],
                ['spend', '350.00', 50],
                ['spend', '180.50', 41],
                ['load', '1000.00', 30],
                ['spend', '420.00', 22],
                ['spend', '95.00', 10],
                ['spend', '60.00', 3],
            ]],
            ['opened' => 48, 'ops' => [
                ['load', '1500.00', 48],
                ['spend', '250.00', 40],
                ['spend', '310.00', 25],
                ['spend', '125.75', 12],
            ]],
            ['email' => 'carlos.mendoza@example.com', 'opened' => 45, 'showcase' => 'depleted', 'ops' => [
                ['load', '500.00', 45],
                ['spend', '120.00', 38],
                ['spend', '380.00', 20],
            ]],
            ['opened' => 35, 'ops' => [
                ['load', '300.00', 35],
                ['spend', '85.00', 28],
                ['spend', '42.50', 15],
                ['adjust', '-20.00', 14, 'Corrección por cobro duplicado'],
            ]],
            ['opened' => 33, 'ops' => [
                ['load', '250.00', 33],
                ['spend', '250.00', 18],
            ]],
            ['opened' => 29, 'status' => CardStatus::Frozen, 'ops' => [
                ['load', '1000.00', 29],
                ['spend', '200.00', 21],
            ]],
            ['opened' => 27, 'status' => CardStatus::Cancelled, 'ops' => [
                ['load', '400.00', 27],
                ['spend', '150.00', 19],
            ]],
            ['opened' => 20, 'ops' => [
                ['load', '150.00', 20],
                ['spend', '35.00', 8],
                ['spend', '48.00', 2],
            ]],
            ['email' => 'sofia.ramirez@example.com', 'opened' => 14, 'ops' => [
                ['load', '800.00', 14],
                ['spend', '120.00', 6],
                ['adjust', '50.00', 5, 'Bonificación por cumpleaños'],
            ]],
            ['opened' => 7, 'ops' => [
                ['load', '200.00', 7],
            ]],
            ['opened' => 4],
            ['email' => 'diego.castillo@example.com', 'opened' => 1],
        ];
    }

    /**
     * Twelve cards against a limit of 14: 85% usage.
     *
     * @return list<CardSpec>
     */
    private function panaderiaCards(): array
    {
        return [
            ['email' => 'ana.torres@example.com', 'opened' => 60, 'ops' => [
                ['load', '1200.00', 60],
                ['spend', '85.00', 52],
                ['spend', '140.00', 37],
                ['spend', '64.50', 16],
            ]],
            ['opened' => 54, 'ops' => [
                ['load', '300.00', 54],
                ['spend', '120.00', 45],
                ['spend', '180.00', 31],
            ]],
            ['opened' => 47, 'ops' => [
                ['load', '500.00', 47],
                ['spend', '75.00', 39],
                ['spend', '42.00', 23],
                ['spend', '38.50', 4],
            ]],
            ['opened' => 42, 'status' => CardStatus::Frozen, 'ops' => [
                ['load', '600.00', 42],
                ['spend', '90.00', 30],
            ]],
            ['opened' => 36, 'ops' => [
                ['load', '100.00', 36],
                ['spend', '100.00', 11],
            ]],
            ['email' => 'pedro.navarro@example.com', 'opened' => 32, 'ops' => [
                ['load', '750.00', 32],
                ['spend', '55.00', 24],
                ['adjust', '25.00', 17, 'Compensación por pedido incompleto'],
                ['spend', '130.00', 5],
            ]],
            ['opened' => 26, 'status' => CardStatus::Cancelled, 'ops' => [
                ['load', '200.00', 26],
                ['adjust', '-200.00', 25, 'Tarjeta extraviada, saldo transferido'],
            ]],
            ['opened' => 21, 'ops' => [
                ['load', '350.00', 21],
                ['spend', '48.00', 13],
                ['spend', '72.00', 2],
            ]],
            ['opened' => 15, 'ops' => [
                ['load', '2500.00', 15, 'Pedido de posadas'],
                ['spend', '640.00', 9],
                ['spend', '385.00', 3],
            ]],
            ['opened' => 10, 'ops' => [
                ['load', '150.00', 10],
                ['spend', '32.00', 6],
            ]],
            ['opened' => 5],
            ['opened' => 2],
        ];
    }

    /**
     * @return list<CardSpec>
     */
    private function bicisCards(): array
    {
        return [
            ['email' => 'fernanda.garza@example.com', 'opened' => 59, 'showcase' => 'suspended business', 'ops' => [
                ['load', '3000.00', 59],
                ['spend', '1450.00', 49],
                ['spend', '320.00', 34],
            ]],
            ['opened' => 53, 'ops' => [
                ['load', '1500.00', 53],
                ['spend', '1500.00', 40],
            ]],
            ['opened' => 46, 'ops' => [
                ['load', '800.00', 46],
                ['spend', '250.00', 33],
                ['spend', '99.90', 19],
            ]],
            ['opened' => 38, 'status' => CardStatus::Frozen, 'ops' => [
                ['load', '500.00', 38],
            ]],
            ['opened' => 30, 'ops' => [
                ['load', '1200.00', 30],
                ['spend', '480.00', 22],
                ['adjust', '-30.00', 20, 'Ajuste por error de captura'],
            ]],
            ['opened' => 24, 'status' => CardStatus::Cancelled, 'ops' => [
                ['load', '250.00', 24],
                ['spend', '250.00', 12],
            ]],
            ['opened' => 17, 'ops' => [
                ['load', '600.00', 17],
                ['spend', '185.00', 8],
            ]],
            ['opened' => 9],
        ];
    }
}
