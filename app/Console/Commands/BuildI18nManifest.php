<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Declares Laravel's translatable strings to duckalization, so both halves of
 * the app share ONE catalog, one brief and one review.
 *
 * duckalization's extractor parses TypeScript only. This command writes a
 * generated TypeScript module that does nothing but NAME the strings PHP
 * renders (validation refusals, ledger errors, the CSV header, the offline
 * page). `duckalize extract` scans it like any other source file, so
 * `translate status`, `brief` and `review` cover the server's copy too.
 * `scripts/build-php-lang.mjs` is the other half: it turns the translated
 * catalog back into `lang/<locale>.json` for Laravel.
 *
 * The rules that keep the output safe (taken from Multiplano):
 *
 * - ONLY SINGLE-QUOTED LITERALS. `__($status)` is a runtime value; there is no
 *   text at the call site to translate. A double-quoted literal may
 *   interpolate, so what it renders is not what it reads.
 * - ONLY MESSAGES, NEVER LANG KEYS. `__('auth.failed')` is a lookup into a
 *   framework lang file (lang/es/*.php), not a message.
 * - TOKENIZED, NOT REGEXED. PHP's tokenizer tells code from comments, so a
 *   `__('…')` written in a docblock (like this one) is never declared.
 * - THE PHP LOCATION RIDES ALONG as a comment above each call, so the brief
 *   tells a translator where the sentence really appears.
 *
 * Blade views are compiled to PHP first and then tokenized the same way.
 *
 * The generated file is never imported, so it never reaches a bundle, and
 * `phpMessages()` is never called (a bare `__()` at module scope would run at
 * import time; see resources/js/i18n.ts).
 */
class BuildI18nManifest extends Command
{
    protected $signature = 'i18n:manifest {--check : Fail if the manifest is out of date rather than writing it}';

    protected $description = 'Declare Laravel’s translatable strings to duckalization’s extractor';

    private const OUTPUT = 'resources/js/locales/php-messages.generated.ts';

    /** Where PHP renders user-facing text. */
    private const SCAN = ['app', 'bootstrap', 'resources/views'];

    /**
     * `__('a.dotted.key')` is a lookup into a framework lang file, not a
     * message. Real copy has spaces and punctuation; keys do not.
     */
    private const LANG_KEY = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/';

    public function handle(): int
    {
        $messages = $this->collect();
        $rendered = $this->render($messages);
        $path = base_path(self::OUTPUT);

        if ($this->option('check')) {
            $current = is_file($path) ? file_get_contents($path) : null;

            if ($current !== $rendered) {
                $this->error(self::OUTPUT.' is out of date. Run `php artisan i18n:manifest`.');

                return self::FAILURE;
            }

            $this->info(count($messages).' PHP messages declared; manifest is current.');

            return self::SUCCESS;
        }

        file_put_contents($path, $rendered);
        $this->info(count($messages).' PHP messages → '.self::OUTPUT);

        return self::SUCCESS;
    }

    /**
     * Every literal message, with the first place it appears. Sorted by file
     * name so the first place is the same on every filesystem.
     *
     * @return array<string, string> message => "file:line"
     */
    private function collect(): array
    {
        $found = [];

        $finder = (new Finder)
            ->files()
            ->in(array_map(static fn (string $dir): string => base_path($dir), self::SCAN))
            ->name('*.php')
            ->sortByName();

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $relative = str_replace(base_path().'/', '', $file->getRealPath());
            $source = (string) file_get_contents($file->getRealPath());

            if (str_ends_with($relative, '.blade.php')) {
                $source = Blade::compileString($source);
            }

            foreach ($this->messagesIn($source) as [$message, $line]) {
                $found[$message] ??= $relative.':'.$line;
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * The literal messages in one file's source, with their line numbers.
     * Matches `__('…')` where `__` is a bare function call, not `->__()` or
     * `::__()`, the same rule duckalization's extractor applies to TypeScript.
     *
     * @return list<array{string, int}>
     */
    private function messagesIn(string $source): array
    {
        $tokens = token_get_all($source);
        $messages = [];
        $previous = null;

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || $token[0] !== T_STRING || $token[1] !== '__') {
                if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $previous = $token;

                continue;
            }

            if (is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                $previous = $token;

                continue;
            }

            $previous = $token;

            $open = $this->nextMeaningful($tokens, $index + 1);

            if ($open === null || $tokens[$open] !== '(') {
                continue;
            }

            $argument = $this->nextMeaningful($tokens, $open + 1);

            if ($argument === null) {
                continue;
            }

            $literal = $tokens[$argument];

            if (! is_array($literal) || $literal[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            if (! str_starts_with($literal[1], "'")) {
                continue;
            }

            // Single-quoted PHP only knows \' and \\.
            $message = str_replace(["\\'", '\\\\'], ["'", '\\'], substr($literal[1], 1, -1));

            if ($message === '' || preg_match(self::LANG_KEY, $message) === 1) {
                continue;
            }

            $messages[] = [$message, $literal[2]];
        }

        return $messages;
    }

    /**
     * @param  list<array{int, string, int}|string>  $tokens
     */
    private function nextMeaningful(array $tokens, int $from): ?int
    {
        for ($i = $from; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    /**
     * @param  array<string, string>  $messages
     */
    private function render(array $messages): string
    {
        $calls = [];

        foreach ($messages as $message => $ref) {
            // Single quotes in TS too, so a message with an apostrophe
            // round-trips with the same escaping rule on both sides.
            $literal = str_replace(['\\', "'"], ['\\\\', "\\'"], $message);
            $calls[] = "        // {$ref}\n        __('{$literal}'),";
        }

        $body = implode("\n", $calls);
        $count = count($messages);

        return <<<TS
        // AUTO-GENERATED by `php artisan i18n:manifest`. Do not edit.
        //
        // Laravel's translatable strings, declared to duckalization's extractor
        // so PHP and TypeScript share ONE catalog, one brief and one review. The
        // extractor parses TypeScript only; this is how the {$count} messages
        // Laravel renders (validation refusals, ledger errors, the CSV header,
        // the offline page) get counted, briefed and translated with the UI.
        //
        // The comment above each call is the PHP source location, which is what
        // a translator sees in the brief.
        //
        // NOTHING IMPORTS THIS FILE, so it never reaches a bundle. phpMessages()
        // is never called: the extractor needs to SEE the calls, not run them.
        //
        // Placeholders here are Laravel's `:name`, not duckalization's `{name}`.
        // They are literal text to the catalog and must be kept verbatim.
        //
        // To regenerate: php artisan i18n:manifest
        import { __ } from '@/i18n';

        export function phpMessages(): string[] {
            return [
        {$body}
            ];
        }

        TS;
    }
}
