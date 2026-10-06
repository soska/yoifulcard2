<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Exceptions\CardBatchException;
use App\Models\Card;
use App\Models\CardBatch;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Preissues inactive cards in batches, for printing, and voids them.
 *
 * Unactivated stock is capped by the organization's preissue limit (null is
 * unlimited, 0 is none). Each write locks the organization row first and
 * counts under that lock, so two batches cannot both take the last of the
 * cap. Lock order follows CardLimit: the organization row, then card rows.
 *
 * Stock doesn't count toward the card limit until it is activated, so a
 * batch is not refused for the card limit; stockBeyondRoom() says how many
 * cards in stock would be refused at activation.
 */
class CardBatchIssuer
{
    /**
     * The most cards one batch can have.
     */
    public const MAX_BATCH_SIZE = 1000;

    public function __construct(private readonly CardCodeGenerator $generator) {}

    /**
     * Create a batch of `$count` inactive cards in the organization's default
     * program. `$byAdmin` marks a batch a superadmin made on the business's
     * behalf; `$notes` are for superadmins (for example, what we charged for
     * printing).
     *
     * @throws CardBatchException
     */
    public function issue(Organization $organization, int $count, User $user, bool $byAdmin, ?string $notes = null): CardBatch
    {
        if ($count < 1) {
            throw CardBatchException::countNotPositive();
        }

        if ($count > self::MAX_BATCH_SIZE) {
            throw CardBatchException::tooMany(self::MAX_BATCH_SIZE);
        }

        $notes = $notes === null || trim($notes) === '' ? null : trim($notes);

        return DB::transaction(function () use ($organization, $count, $user, $byAdmin, $notes): CardBatch {
            $organization = CardLimit::lock($organization->id);

            if (! $organization->isWritable()) {
                throw CardBatchException::organizationNotWritable();
            }

            $program = $organization->defaultProgram();

            if ($program === null) {
                throw CardBatchException::noProgram();
            }

            $limit = $organization->preissue_limit;

            if ($limit !== null) {
                $stock = self::stock($organization);

                if ($stock + $count > $limit) {
                    throw CardBatchException::stockLimitReached($limit, max($limit - $stock, 0));
                }
            }

            $batch = CardBatch::create([
                'organization_id' => $organization->id,
                'program_id' => $program->id,
                'count' => $count,
                'created_by' => $user->getKey(),
                'issued_by_admin' => $byAdmin,
                'notes' => $notes,
            ]);

            for ($i = 0; $i < $count; $i++) {
                $program->cards()->create([
                    'batch_id' => $batch->id,
                    'code' => $this->generator->code(),
                    'qr_token' => $this->generator->qrToken(),
                    'balance' => '0.00',
                    'status' => CardStatus::Inactive,
                ]);
            }

            return $batch;
        });
    }

    /**
     * Void a batch: every card in it that is still inactive is cancelled, and
     * the batch gets `voided_at`. Cards already activated are not touched.
     * Returns how many cards were cancelled.
     *
     * @throws CardBatchException
     */
    public function void(CardBatch $batch): int
    {
        return DB::transaction(function () use ($batch): int {
            // The organization row first (CardLimit's lock order), so a
            // void and an activation of the same card are serialized.
            $organization = CardLimit::lock($batch->organization_id);

            if (! $organization->isWritable()) {
                throw CardBatchException::organizationNotWritable();
            }

            /** @var CardBatch $locked */
            $locked = CardBatch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw CardBatchException::batchVoided();
            }

            $cancelled = $locked->cards()
                ->where('status', CardStatus::Inactive)
                ->update(['status' => CardStatus::Cancelled, 'updated_at' => now()]);

            $locked->update(['voided_at' => now()]);

            $batch->setRawAttributes($locked->getAttributes(), true);

            return $cancelled;
        });
    }

    /**
     * Void one card that is not activated yet (lost or stolen stock): it
     * becomes cancelled and stops counting toward the preissue limit.
     *
     * @throws CardBatchException
     */
    public function voidCard(Card $card): void
    {
        DB::transaction(function () use ($card): void {
            $organization = CardLimit::lock(
                (string) Program::query()->whereKey($card->program_id)->value('organization_id'),
            );

            if (! $organization->isWritable()) {
                throw CardBatchException::organizationNotWritable();
            }

            /** @var Card $locked */
            $locked = Card::query()->whereKey($card->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== CardStatus::Inactive) {
                throw CardBatchException::cardNotInactive();
            }

            $locked->update(['status' => CardStatus::Cancelled]);

            $card->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * How many cards in stock the plan has no room to activate: stock beyond
     * what is left under the card limit. 0 when the plan is unlimited or the
     * stock fits. Activating those cards would be refused, so the screens
     * warn about it; it never blocks a batch.
     */
    public static function stockBeyondRoom(Organization $organization): int
    {
        $usage = $organization->cardUsage();

        if ($usage['limit'] === null) {
            return 0;
        }

        return max($usage['stock'] - max($usage['limit'] - $usage['used'], 0), 0);
    }

    /**
     * Unactivated cards the organization holds. Voided cards are cancelled,
     * so they are not stock.
     */
    private static function stock(Organization $organization): int
    {
        return Card::query()->forOrganization($organization)->where('status', CardStatus::Inactive)->count();
    }
}
