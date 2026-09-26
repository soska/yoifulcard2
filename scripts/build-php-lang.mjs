#!/usr/bin/env node
/**
 * Turns the shared duckalization catalog back into the `lang/<locale>.json`
 * files Laravel reads. Adapted from Multiplano.
 *
 * Laravel's JSON translations are keyed by the English source string, and
 * duckalization's `en.meta.json` maps every content-derived id to that same
 * English string. So, for every id that came from the PHP manifest
 * (`php artisan i18n:manifest`):
 *
 *   lang/es.json  =  { meta[id].message: es[id] }
 *
 * No hashing in PHP and no second source of truth.
 *
 * Only manifest ids are written. Laravel never looks up the UI's messages,
 * and a lang/es.json holding every button label would hide the answer to
 * "what does the server actually say?".
 *
 * Missing translations are OMITTED, never written as "", so Laravel renders
 * the English key instead of a blank. A partial catalog is safe to ship.
 *
 * Usage: node scripts/build-php-lang.mjs [--check]
 *   (npm run i18n:lang, and npm run i18n:check runs it with --check)
 */
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const catalogDir = join(root, 'resources/js/locales');
const langDir = join(root, 'lang');

/** The generated module that declares Laravel's strings to the extractor. */
const MANIFEST = 'php-messages.generated.ts';

const check = process.argv.includes('--check');

const meta = JSON.parse(readFileSync(join(catalogDir, 'en.meta.json'), 'utf8'));
const config = JSON.parse(
    readFileSync(join(root, 'duckalization.config.json'), 'utf8'),
);

/** Every id Laravel renders, with the English that identifies it there. */
const phpMessages = Object.entries(meta)
    .filter(([, entry]) => entry.refs.some((ref) => ref.includes(MANIFEST)))
    .map(([id, entry]) => [id, entry.message]);

if (phpMessages.length === 0) {
    console.error(
        `No messages found from ${MANIFEST}. Run \`npm run i18n:extract\` first.`,
    );
    process.exit(1);
}

let stale = false;

for (const locale of config.targetLocales ?? []) {
    const catalogPath = join(catalogDir, `${locale}.json`);

    if (!existsSync(catalogPath)) {
        console.log(`${locale}: no catalog yet, skipping.`);
        continue;
    }

    const catalog = JSON.parse(readFileSync(catalogPath, 'utf8'));
    const translations = {};
    let missing = 0;

    for (const [id, english] of phpMessages) {
        const translated = catalog[id];

        if (typeof translated !== 'string' || translated === '') {
            // Omitted, not emptied: Laravel falls back to the English key.
            // The manifest only declares plain strings, never plural objects.
            missing += 1;
            continue;
        }

        translations[english] = translated;
    }

    // Sorted by key so a diff is readable.
    const sorted = Object.fromEntries(
        Object.entries(translations).sort(([a], [b]) => (a < b ? -1 : 1)),
    );
    // Four-space indent, matching the formatter (vite.config.ts fmt.tabWidth),
    // so `npm run check` and `npm run i18n:check` never rewrite each other.
    const rendered = `${JSON.stringify(sorted, null, 4)}\n`;
    const langPath = join(langDir, `${locale}.json`);
    const current = existsSync(langPath)
        ? readFileSync(langPath, 'utf8')
        : null;

    if (check) {
        if (current !== rendered) {
            console.error(
                `lang/${locale}.json is out of date. Run \`npm run i18n:lang\`.`,
            );
            stale = true;
        }
        continue;
    }

    mkdirSync(langDir, { recursive: true });
    writeFileSync(langPath, rendered);

    const wrote = Object.keys(sorted).length;
    console.log(
        `${locale}: ${wrote}/${phpMessages.length} translated → lang/${locale}.json` +
            (missing > 0 ? ` (${missing} still English)` : ''),
    );
}

process.exit(stale ? 1 : 0);
