<?php

/*
| A separate PHP process that charges a card through CardLedger, for the
| ledger concurrency test. It boots the app on its own database connection.
|
| Usage: php tests/Support/ledger-spend.php <card-id> <user-id> <amount>
| Prints one line of JSON: {"ok": true, "balance_after": "..."} or
| {"ok": false, "error": "<ledger translation key>"}.
*/

use App\Exceptions\LedgerException;
use App\Models\Card;
use App\Models\User;
use App\Services\CardLedger;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $cardId, $userId, $amount] = $argv;

try {
    $transaction = app(CardLedger::class)->spend(Card::findOrFail($cardId), $amount, User::findOrFail($userId));

    echo json_encode(['ok' => true, 'balance_after' => $transaction->balance_after]), PHP_EOL;
} catch (LedgerException $exception) {
    echo json_encode(['ok' => false, 'error' => $exception->key]), PHP_EOL;
}
