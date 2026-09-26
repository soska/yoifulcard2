<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Card;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Rows made here skip the ledger, so they do not change the card balance.
 * Use them for listing and export tests only.
 *
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(100, 10000);

        return [
            'card_id' => Card::factory(),
            'type' => TransactionType::Load,
            'amount' => bcdiv((string) $amount, '100', 2),
            'balance_after' => bcdiv((string) $amount, '100', 2),
            'note' => null,
            'performed_by' => User::factory(),
        ];
    }

    public function spend(): static
    {
        return $this->state(fn () => ['type' => TransactionType::Spend]);
    }

    public function adjustment(string $note = 'Correction'): static
    {
        return $this->state(fn () => ['type' => TransactionType::Adjustment, 'note' => $note]);
    }
}
