<?php

namespace App\Console\Commands;

use App\Models\Card;
use App\Services\CardLedger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Expires the points of cards that have been idle longer than their program's
 * rule. The query only finds candidates; CardLedger::expireInactive checks
 * each one again under the card's lock and writes the `expire` entry, so a
 * card used since, or one expired by an earlier run, is left alone and
 * running this twice expires nothing twice.
 */
#[Signature('rewards:expire-points')]
#[Description('Expire the points of cards idle longer than their program allows')]
class ExpirePoints extends Command
{
    public function handle(CardLedger $ledger): int
    {
        $expired = 0;

        Card::query()
            ->select('cards.*')
            ->join('programs', 'programs.id', '=', 'cards.program_id')
            ->where('programs.rewards_enabled', true)
            ->whereNotNull('programs.points_expiry_months')
            ->where('cards.points', '>', 0)
            ->whereRaw("coalesce(cards.last_used_at, cards.created_at) < now() - (programs.points_expiry_months * interval '1 month')")
            ->orderBy('cards.id')
            ->chunk(200, function ($cards) use ($ledger, &$expired): void {
                foreach ($cards as $card) {
                    /** @var Card $card */
                    if ($ledger->expireInactive($card) !== null) {
                        $expired++;
                    }
                }
            });

        $this->info("Expired the points of {$expired} ".Str::plural('card', $expired).'.');

        return self::SUCCESS;
    }
}
