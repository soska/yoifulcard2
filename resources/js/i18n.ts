import { createDuck } from '@duckalization/react';

/**
 * The app's single duckalization instance (method:
 * ~/personal/chronicles/localization.md, reference: Multiplano's i18n.ts).
 *
 * The English text written at the call site IS the source of truth; there are
 * no keys. `__('Save')` renders "Save" in English and looks up a
 * content-derived hash in every other locale. Rewording the English orphans
 * the old translation on purpose, so stale copy shows up as untranslated.
 *
 * One module-scope instance is correct ONLY because Inertia SSR is off
 * (config/inertia.php, `inertia({ ssr: false })` in vite.config.ts). With SSR
 * each request would need its own duck.
 *
 * ## Why the module-scope `__`, not `useDuck()`
 *
 * No locale change is ever observed by a rendered tree:
 *
 *   - The server resolves the locale (App\Http\Middleware\ResolveLocale) and
 *     sends it as the shared `locale` prop.
 *   - Its catalog is loaded BEFORE the first paint (bootLocale() below, awaited
 *     in app.tsx). Nothing here is subscribed, so a catalog arriving after
 *     paint would change the duck and repaint nothing: Multiplano shipped that
 *     bug and rendered English to Spanish readers indefinitely.
 *   - Changing language is a full document reload (the switcher posts to
 *     /locale, which answers with Inertia::location), because server copy
 *     (validation messages, ledger errors) only re-renders on a fresh request.
 *
 * ## Two `__()` helpers
 *
 * This one (TS) interpolates `{name}`. Laravel's (PHP) interpolates `:name`.
 * The wrong syntax renders the token literally and never errors.
 *
 * ## No `__()` at module scope
 *
 * A module-scope call runs at import time, before the catalog loads, and
 * freezes English into the bundle. Lookup tables are functions (see
 * lib/labels.ts and lib/flash.ts).
 */
export const duck = createDuck({
    sourceLocale: 'en',
});

/**
 * Destructured on purpose: the extractor only sees bare `__(...)` calls, so
 * `duck.__('x')` would never be extracted. Import this one.
 */
export const { __ } = duck;

/**
 * The Intl locale of the language the server resolved (`en-US`, `es-MX`),
 * from the shared `locale.intl` prop. Set by bootLocale().
 */
let intlLocale: string | null = null;

/**
 * The locale to hand to every `Intl` constructor (dates, money, numbers).
 *
 * `undefined` would mean "the browser's locale", which is not the app's
 * language: an English browser reading the app in Spanish would get US dates
 * under Spanish text. Call it at render time, never capture it in a
 * module-scope formatter.
 */
export function activeLocale(): string {
    return intlLocale ?? duck.getLocale();
}

/**
 * Every catalog we can switch to, lazily. `import.meta.glob` so each locale is
 * its own chunk and English downloads nothing. Not `en.json` (the source text
 * is inline), not `*.meta.json` and not `*.review.json` (tooling only), and
 * not the glossary.
 */
const catalogs = import.meta.glob<Record<string, unknown>>(
    [
        './locales/*.json',
        '!./locales/en.json',
        '!./locales/*.meta.json',
        '!./locales/*.review.json',
        '!./locales/glossary.json',
    ],
    { import: 'default' },
);

/**
 * Point the app at the locale the SERVER resolved (`locale.current`, and
 * `locale.intl` for formatting).
 *
 * AWAIT THIS BEFORE THE FIRST RENDER (app.tsx paints in its `.then`). It never
 * rejects: a locale with no catalog, or a failed load, leaves the duck on the
 * source locale and the app paints in English rather than not at all.
 */
export async function bootLocale(locale: string, intl?: string): Promise<void> {
    intlLocale = intl ?? null;

    if (locale === duck.getLocale()) {
        return;
    }

    const load = catalogs[`./locales/${locale}.json`];

    if (load === undefined) {
        if (import.meta.env.DEV) {
            console.warn(
                `[i18n] no catalog for "${locale}"; rendering source text`,
            );
        }

        return;
    }

    try {
        const catalog = await load();

        duck.load(locale, catalog as Parameters<typeof duck.load>[1]);
        duck.setLocale(locale);
    } catch (error) {
        if (import.meta.env.DEV) {
            console.warn(
                `[i18n] could not load the "${locale}" catalog`,
                error,
            );
        }
    }
}
