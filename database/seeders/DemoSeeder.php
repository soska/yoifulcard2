<?php

namespace Database\Seeders;

use App\Enums\CardStatus;
use App\Enums\EarnMode;
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
 * Demo data for local development: five businesses, their people, and
 * cards with about 60 days of ledger history. Two of them use rewards.
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
 * - cometa@yoiful.test: owner of Café Cometa (rewards: points per amount
 *   spent); armando is a manager there and employee@ an employee
 * - yoga@yoiful.test: owner of Loto Yoga Studio (rewards: a point per
 *   visit); armando is a manager there
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
 * The rewards businesses set their program's rewards rule and catalog before
 * their cards are seeded, so charges earn points as they happen. Points
 * entries go through CardLedger too: `earn` (arg: the purchase amount, or
 * null for a visit), `redeem` (arg: the reward's name), `points` (arg: a
 * signed whole number, with a note) and `expire`. Café Cometa expires points
 * after a month idle and has one card idle past that: run
 * `php artisan rewards:expire-points` to see it expire.
 *
 * @phpstan-type CardOp array{0: 'load'|'spend'|'adjust'|'earn'|'redeem'|'points'|'expire', 1: string|null, 2: int, 3?: string}
 * @phpstan-type CardSpec array{email?: string, opened: int, ops?: list<CardOp>, status?: CardStatus, showcase?: string}
 * @phpstan-type RewardsSetup array{program: array<string, mixed>, rewards: list<array{name: string, cost: int, active?: bool}>}
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'yoiful-test-123';

    public const DEMO_EMAIL = 'armando@yoiful.test';

    public const OWNER2_EMAIL = 'owner2@yoiful.test';

    public const EMPLOYEE_EMAIL = 'employee@yoiful.test';

    public const SUSPENDED_EMAIL = 'suspended@yoiful.test';

    public const COMETA_EMAIL = 'cometa@yoiful.test';

    public const YOGA_EMAIL = 'yoga@yoiful.test';

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
            $cometaOwner = $this->user(self::COMETA_EMAIL, 'Valeria Ortega');
            $yogaOwner = $this->user(self::YOGA_EMAIL, 'Camila Reyes');

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

            $cometa = $this->business($cometaOwner, [
                'name' => 'Café Cometa',
                'primary_color' => '#1D4ED8',
                'card_limit' => 40,
            ]);
            $this->member($cometa, $demo, MembershipRole::Manager);
            $this->member($cometa, $employee, MembershipRole::Employee);

            $yoga = $this->business($yogaOwner, [
                'name' => 'Loto Yoga Studio',
                'primary_color' => '#7C3AED',
                'card_limit' => 30,
            ]);
            $this->member($yoga, $demo, MembershipRole::Manager);

            $this->cards($cafe, [$demo], $this->cafeCards());
            $this->cards($panaderia, [$owner2, $employee, $demo], $this->panaderiaCards());
            $this->cards($bicis, [$suspendedOwner], $this->bicisCards());
            $this->cards($cometa, [$cometaOwner, $employee, $demo], $this->cometaCards(), $this->cometaSetup());
            $this->cards($yoga, [$yogaOwner, $demo], $this->yogaCards(), $this->yogaSetup());

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
     * @param  RewardsSetup|array{}  $setup  A rewards business's program rule and reward catalog, set before its cards are seeded.
     */
    private function cards(Organization $organization, array $people, array $specs, array $setup = []): void
    {
        if ($organization->cards()->exists()) {
            return;
        }

        /** @var Program $program */
        $program = $organization->defaultProgram();

        if ($setup !== []) {
            $program->update($setup['program']);

            foreach ($setup['rewards'] as $reward) {
                $program->rewards()->create([
                    'name' => $reward['name'],
                    'points_cost' => $reward['cost'],
                    'is_active' => $reward['active'] ?? true,
                ]);
            }
        }

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
                    'load' => $this->ledger->load($card, (string) $amount, $person, $note),
                    'spend' => $this->ledger->spend($card, (string) $amount, $person, $note),
                    'adjust' => $this->ledger->adjust($card, (string) $amount, $person, $note),
                    'earn' => $this->ledger->earnForPurchase($card, $amount, $person, $note),
                    'redeem' => $this->ledger->redeemReward($card, $program->rewards()->where('name', $amount)->firstOrFail(), $person, $note),
                    'points' => $this->ledger->adjustPoints($card, (int) $amount, $person, $note),
                    'expire' => $this->ledger->expireInactive($card),
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
            [self::COMETA_EMAIL, 'owner of Café Cometa (rewards: points per amount spent; armando manages it, employee@ works there)'],
            [self::YOGA_EMAIL, 'owner of Loto Yoga Studio (rewards: a point per visit; armando manages it)'],
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

    /**
     * Café Cometa: points per amount spent, one point per 10 pesos. Points
     * expire after a month idle, so most cards have been used in the last
     * four weeks, one is idle past that (try `rewards:expire-points`), and one
     * already went through an expiry.
     *
     * @return RewardsSetup
     */
    private function cometaSetup(): array
    {
        return [
            'program' => [
                'name' => 'Tarjeta Cometa',
                'rewards_enabled' => true,
                'earn_mode' => EarnMode::Spend,
                'earn_rate' => '0.10',
                'points_expiry_months' => 1,
            ],
            'rewards' => [
                ['name' => 'Café americano gratis', 'cost' => 30],
                ['name' => 'Pastel de la casa', 'cost' => 60],
                ['name' => 'Bolsa de café en grano', 'cost' => 120],
                ['name' => 'Taza de edición limitada', 'cost' => 40, 'active' => false],
            ],
        ];
    }

    /**
     * @return list<CardSpec>
     */
    private function cometaCards(): array
    {
        return [
            // Prepaid balance and points together: charges earn as they happen.
            ['email' => 'renata.vega@example.com', 'opened' => 56, 'showcase' => 'balance and points', 'ops' => [
                ['load', '1500.00', 56],
                ['spend', '180.00', 50],
                ['spend', '240.00', 44],
                ['redeem', 'Café americano gratis', 40],
                ['spend', '320.00', 33],
                ['spend', '150.00', 20],
                ['points', '10', 15, 'Bonificación por reseña'],
                ['spend', '210.00', 9],
                ['earn', '95.00', 4],
            ]],
            // Pays cash, never loaded money: a rewards-only card.
            ['opened' => 52, 'showcase' => 'points only, enough for a pastel', 'ops' => [
                ['earn', '120.00', 52],
                ['earn', '85.50', 47],
                ['earn', '240.00', 30],
                ['redeem', 'Café americano gratis', 21],
                ['earn', '300.00', 12],
                ['earn', '180.00', 3],
            ]],
            // Idle past the expiry: `rewards:expire-points` takes its 40 points.
            ['email' => 'luis.aguilar@example.com', 'opened' => 60, 'showcase' => 'idle, points due to expire', 'ops' => [
                ['earn', '400.00', 60],
            ]],
            // Went idle once and lost its points, then came back.
            ['opened' => 58, 'ops' => [
                ['earn', '200.00', 58],
                ['expire', null, 25],
                ['earn', '150.00', 6],
            ]],
            ['opened' => 40, 'status' => CardStatus::Frozen, 'ops' => [
                ['load', '300.00', 40],
                ['spend', '100.00', 30],
                ['spend', '50.00', 22],
            ]],
            ['opened' => 25, 'ops' => [
                ['load', '200.00', 25],
                ['spend', '45.00', 18],
                ['spend', '60.00', 7],
            ]],
            ['email' => 'paola.ibarra@example.com', 'opened' => 3],
            ['opened' => 1, 'ops' => [
                ['load', '100.00', 1],
            ]],
        ];
    }

    /**
     * Loto Yoga Studio: a point per visit, whether the visit is recorded on
     * its own or comes with a charge to a class pack.
     *
     * @return RewardsSetup
     */
    private function yogaSetup(): array
    {
        return [
            'program' => [
                'name' => 'Pase Loto',
                'rewards_enabled' => true,
                'earn_mode' => EarnMode::Visits,
                'points_expiry_months' => 3,
            ],
            'rewards' => [
                ['name' => 'Clase gratis', 'cost' => 10],
                ['name' => 'Playera Loto', 'cost' => 25],
                ['name' => 'Taller de fin de semana', 'cost' => 50],
            ],
        ];
    }

    /**
     * A visit recorded at the counter on each of the given days ago.
     *
     * @param  list<int>  $days
     * @return list<CardOp>
     */
    private function visits(array $days): array
    {
        return array_map(fn (int $day) => ['earn', null, $day], $days);
    }

    /**
     * @return list<CardSpec>
     */
    private function yogaCards(): array
    {
        return [
            // A class pack: ten charges of one class each, one point apiece.
            // The pack is used up (depleted) with ten points to spend.
            ['email' => 'ximena.salgado@example.com', 'opened' => 58, 'showcase' => 'class pack used up, ready to redeem', 'ops' => [
                ['load', '1200.00', 58, 'Paquete de 10 clases'],
                ['spend', '120.00', 54],
                ['spend', '120.00', 48],
                ['spend', '120.00', 42],
                ['spend', '120.00', 36],
                ['spend', '120.00', 30],
                ['spend', '120.00', 24],
                ['spend', '120.00', 18],
                ['spend', '120.00', 12],
                ['spend', '120.00', 8],
                ['spend', '120.00', 4],
            ]],
            ['opened' => 58, 'showcase' => 'regular student', 'ops' => [
                ...$this->visits([58, 55, 52, 49, 46, 43, 40, 37, 34, 31]),
                ['redeem', 'Clase gratis', 30],
                ...$this->visits([28, 25, 22, 19, 16, 13, 10, 7, 4, 1]),
            ]],
            ['email' => 'daniela.cruz@example.com', 'opened' => 45, 'ops' => $this->visits([45, 30, 18, 6])],
            ['opened' => 40, 'ops' => [
                ['points', '25', 40, 'Puntos de bienvenida por inscripción anual'],
                ...$this->visits([36, 33, 30, 27, 24, 21, 18, 15, 12, 9, 6]),
                ['redeem', 'Playera Loto', 5],
                ...$this->visits([3]),
            ]],
            ['opened' => 50, 'showcase' => 'one visit from a free class', 'ops' => $this->visits([50, 44, 38, 32, 26, 20, 14, 8, 2])],
            ['opened' => 40, 'status' => CardStatus::Frozen, 'ops' => $this->visits([40, 34, 28])],
            ['opened' => 3],
            ['email' => 'marcela.duran@example.com', 'opened' => 1],
        ];
    }
}
