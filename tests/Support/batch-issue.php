<?php

/*
| A separate PHP process that creates a card batch through CardBatchIssuer,
| for the concurrency test. It boots the app on its own database connection.
|
| Usage: php tests/Support/batch-issue.php <organization-id> <user-id> <count>
| Prints one line of JSON: {"ok": true, "count": N} or
| {"ok": false, "error": "<issuer message>"}.
*/

use App\Exceptions\CardBatchException;
use App\Models\Organization;
use App\Models\User;
use App\Services\CardBatchIssuer;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $organizationId, $userId, $count] = $argv;

try {
    $batch = app(CardBatchIssuer::class)->issue(Organization::findOrFail($organizationId), (int) $count, User::findOrFail($userId), byAdmin: false);

    echo json_encode(['ok' => true, 'count' => $batch->count]), PHP_EOL;
} catch (CardBatchException $exception) {
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]), PHP_EOL;
}
