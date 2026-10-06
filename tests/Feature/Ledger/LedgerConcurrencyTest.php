<?php

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/*
| This test needs rows that other processes can see, so it cannot live inside
| the RefreshDatabase transaction. It writes and commits its fixtures through
| a second connection to yoiful_testing, runs two real PHP processes that
| each open their own connection, and deletes its rows at the end.
*/

const LEDGER_CONCURRENCY_CONNECTION = 'ledger_concurrency';

/**
 * A second connection to the test database that commits on its own.
 */
function concurrencyConnection(): Connection
{
    config(['database.connections.'.LEDGER_CONCURRENCY_CONNECTION => config('database.connections.pgsql')]);

    return DB::connection(LEDGER_CONCURRENCY_CONNECTION);
}

/**
 * Start `php tests/Support/ledger-spend.php` (or another script there that
 * takes the same arguments) in its own process.
 *
 * @return array{process: resource, stdout: resource, stderr: resource}
 */
function startSpendProcess(string $cardId, int $userId, string $amount, string $script = 'ledger-spend.php'): array
{
    $pgsql = config('database.connections.pgsql');

    $env = array_merge(getenv(), [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_URL' => '',
        'DB_HOST' => (string) $pgsql['host'],
        'DB_PORT' => (string) $pgsql['port'],
        'DB_DATABASE' => (string) $pgsql['database'],
        'DB_USERNAME' => (string) $pgsql['username'],
        'DB_PASSWORD' => (string) $pgsql['password'],
    ]);

    $process = proc_open(
        [PHP_BINARY, base_path('tests/Support/'.$script), $cardId, (string) $userId, $amount],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        base_path(),
        $env,
    );

    expect($process)->toBeResource();

    return ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
}

/**
 * Wait for a process to exit and decode the JSON line it printed.
 *
 * @param  array{process: resource, stdout: resource, stderr: resource}  $child
 * @return array<string, mixed>
 */
function finishSpendProcess(array $child, float $timeout = 30.0): array
{
    $deadline = microtime(true) + $timeout;

    while (proc_get_status($child['process'])['running']) {
        if (microtime(true) > $deadline) {
            proc_terminate($child['process'], 9);

            throw new RuntimeException('A spend process did not finish in time.');
        }

        usleep(20_000);
    }

    $stdout = stream_get_contents($child['stdout']);
    $stderr = stream_get_contents($child['stderr']);
    proc_close($child['process']);

    $result = json_decode(trim((string) $stdout), true);

    if (! is_array($result)) {
        throw new RuntimeException("A spend process failed.\nstdout: {$stdout}\nstderr: {$stderr}");
    }

    return $result;
}

/**
 * How many sessions on the test database are waiting for a row lock.
 */
function sessionsWaitingForLocks(Connection $db): int
{
    // Statistics views are snapshotted per transaction; take a fresh look.
    $db->select('select pg_stat_clear_snapshot()');

    return (int) $db->scalar(
        "select count(*) from pg_stat_activity where datname = current_database() and wait_event_type = 'Lock'"
    );
}

test('two concurrent spends cannot overdraw', function () {
    $db = concurrencyConnection();

    // Committed fixtures: a card with 100.00 and a user to charge it.
    $user = User::factory()->connection(LEDGER_CONCURRENCY_CONNECTION)->create();
    $organization = Organization::factory()->connection(LEDGER_CONCURRENCY_CONNECTION)->create();
    $program = Program::factory()->connection(LEDGER_CONCURRENCY_CONNECTION)->for($organization)->create();
    $card = Card::factory()->connection(LEDGER_CONCURRENCY_CONNECTION)->create([
        'program_id' => $program->id,
        'balance' => '100.00',
    ]);

    $children = [];

    try {
        // Hold the card row so both processes are inside CardLedger, waiting
        // on lockForUpdate(), before either one reads the balance.
        $db->beginTransaction();
        $db->table('cards')->where('id', $card->id)->lockForUpdate()->first();

        $children[] = startSpendProcess($card->id, $user->id, '70.00');
        $children[] = startSpendProcess($card->id, $user->id, '70.00');

        $deadline = microtime(true) + 20;
        while (sessionsWaitingForLocks($db) < 2) {
            if (microtime(true) > $deadline) {
                $states = array_map(fn (array $child) => proc_get_status($child['process'])['running']
                    ? 'running'
                    : 'exited: '.stream_get_contents($child['stdout']).stream_get_contents($child['stderr']), $children);

                throw new RuntimeException('The spend processes never reached the card lock: '.implode(' | ', $states));
            }

            usleep(20_000);
        }

        // Both are blocked on the same row. Let them race.
        $db->commit();

        $results = array_map(fn (array $child) => finishSpendProcess($child), $children);
        $children = [];

        $succeeded = array_values(array_filter($results, fn (array $result) => $result['ok'] === true));
        $refused = array_values(array_filter($results, fn (array $result) => $result['ok'] === false));

        expect($succeeded)->toHaveCount(1)
            ->and($succeeded[0]['balance_after'])->toBe('30.00')
            ->and($refused)->toHaveCount(1)
            ->and($refused[0]['error'])->toBe('Insufficient balance.');

        // The committed state, read through the independent connection.
        expect($db->table('cards')->where('id', $card->id)->value('balance'))->toBe('30.00');

        $rows = $db->table('transactions')->where('card_id', $card->id)->get();
        expect($rows)->toHaveCount(1)
            ->and($rows[0]->type)->toBe('spend')
            ->and($rows[0]->amount)->toBe('70.00')
            ->and($rows[0]->balance_after)->toBe('30.00');
    } finally {
        if ($db->transactionLevel() > 0) {
            $db->rollBack();
        }

        foreach ($children as $child) {
            proc_terminate($child['process'], 9);
            proc_close($child['process']);
        }

        $db->table('transactions')->where('card_id', $card->id)->delete();
        $db->table('cards')->where('id', $card->id)->delete();
        $db->table('programs')->where('id', $program->id)->delete();
        $db->table('organizations')->where('id', $organization->id)->delete();
        $db->table('users')->where('id', $user->id)->delete();
        $db->disconnect();
    }
});

test('two concurrent activations cannot both take the last slot', function () {
    $db = concurrencyConnection();

    // Committed fixtures: a business with room for one more card and two
    // inactive cards to activate.
    $user = User::factory()->connection(LEDGER_CONCURRENCY_CONNECTION)->create();
    $organization = Organization::factory()->connection(LEDGER_CONCURRENCY_CONNECTION)->create(['card_limit' => 2]);
    $program = Program::factory()->connection(LEDGER_CONCURRENCY_CONNECTION)->for($organization)->create();
    $active = Card::factory()->connection(LEDGER_CONCURRENCY_CONNECTION)->create(['program_id' => $program->id]);
    $first = Card::factory()->connection(LEDGER_CONCURRENCY_CONNECTION)->inactive()->create(['program_id' => $program->id]);
    $second = Card::factory()->connection(LEDGER_CONCURRENCY_CONNECTION)->inactive()->create(['program_id' => $program->id]);
    $cardIds = [$active->id, $first->id, $second->id];

    $children = [];

    try {
        // Hold the organization row so both processes are inside
        // CardLedger::activate, waiting on its lock, before either counts.
        $db->beginTransaction();
        $db->table('organizations')->where('id', $organization->id)->lockForUpdate()->first();

        $children[] = startSpendProcess($first->id, $user->id, '50.00', 'ledger-activate.php');
        $children[] = startSpendProcess($second->id, $user->id, '50.00', 'ledger-activate.php');

        $deadline = microtime(true) + 20;
        while (sessionsWaitingForLocks($db) < 2) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('The activate processes never reached the organization lock.');
            }

            usleep(20_000);
        }

        $db->commit();

        $results = array_map(fn (array $child) => finishSpendProcess($child), $children);
        $children = [];

        $succeeded = array_values(array_filter($results, fn (array $result) => $result['ok'] === true));
        $refused = array_values(array_filter($results, fn (array $result) => $result['ok'] === false));

        expect($succeeded)->toHaveCount(1)
            ->and($succeeded[0]['balance_after'])->toBe('50.00')
            ->and($refused)->toHaveCount(1)
            ->and($refused[0]['error'])->toBe('You have reached your plan limit of 2 cards. Contact support to raise the limit.');

        $statuses = $db->table('cards')->whereIn('id', [$first->id, $second->id])->pluck('status')->sort()->values()->all();
        expect($statuses)->toBe([CardStatus::Active->value, CardStatus::Inactive->value])
            ->and($db->table('transactions')->whereIn('card_id', $cardIds)->count())->toBe(1);
    } finally {
        if ($db->transactionLevel() > 0) {
            $db->rollBack();
        }

        foreach ($children as $child) {
            proc_terminate($child['process'], 9);
            proc_close($child['process']);
        }

        $db->table('transactions')->whereIn('card_id', $cardIds)->delete();
        $db->table('cards')->whereIn('id', $cardIds)->delete();
        $db->table('programs')->where('id', $program->id)->delete();
        $db->table('organizations')->where('id', $organization->id)->delete();
        $db->table('users')->where('id', $user->id)->delete();
        $db->disconnect();
    }
});
