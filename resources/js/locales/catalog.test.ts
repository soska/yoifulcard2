import { createDuck } from '@duckalization/react';
import { expect, it } from 'vitest';
import en from '@/locales/en.json';
import esCatalog from '@/locales/es.json';

/*
 * The Spanish catalog, checked against the source catalog (adapted from
 * Multiplano's resources/js/locales/catalog.test.ts).
 *
 * These are not tests of the TRANSLATIONS: no unit test can say "saldo" is
 * the right word for "balance"; `duckalize translate lint` and a human
 * reviewer own that. They test the ways a catalog breaks without anyone
 * noticing until a Spanish reader hits it: a message that is missing, a
 * message that renders with a hole in it, and a plural that renders the
 * wrong form.
 *
 * Run `npm run i18n:extract` before this: it checks what extraction produced.
 */

type Message = string | Record<string, string>;

const source = en as Record<string, Message>;
const es = esCatalog as Record<string, Message>;

/** `{name}`: duckalization's own interpolation. Checked by `apply` too. */
const CURLY = /\{([A-Za-z0-9_]+)\}/g;

/**
 * `:name`: LARAVEL's interpolation, in the messages the PHP bridge adds to
 * the catalog (Phase 9.3). duckalization treats these as plain text, so only
 * this test would catch a translated `:email`.
 */
const COLON = /:([a-z][a-zA-Z0-9_]*)/g;

const forms = (message: Message): string[] =>
    typeof message === 'string' ? [message] : Object.values(message);

const placeholders = (message: Message, pattern: RegExp): Set<string> =>
    new Set(forms(message).join(' ').match(pattern) ?? []);

const samePlaceholders = (id: string, pattern: RegExp): boolean => {
    const from = placeholders(source[id]!, pattern);
    const to = placeholders(es[id]!, pattern);

    return from.size === to.size && [...from].every((token) => to.has(token));
};

it('has a source catalog to check', () => {
    // Sanity: an empty en.json would make every other test pass vacuously.
    expect(Object.keys(source).length).toBeGreaterThan(100);
});

it('translates every message the source catalog has', () => {
    const missing = Object.keys(source).filter((id) => !(id in es));

    expect(missing).toEqual([]);
});

it('has no entry the source catalog does not', () => {
    // An orphan is a message whose English was reworded or removed: its id
    // changed, so the old translation is stranded. `duckalize translate
    // prune` archives them.
    const orphaned = Object.keys(es).filter((id) => !(id in source));

    expect(orphaned).toEqual([]);
});

it('has no empty translation', () => {
    const empty = Object.keys(es).filter((id) =>
        forms(es[id]!).some((text) => text.trim() === ''),
    );

    expect(empty).toEqual([]);
});

it('keeps every {placeholder} exactly as the source has it', () => {
    const broken = Object.keys(source).filter(
        (id) => id in es && !samePlaceholders(id, CURLY),
    );

    expect(broken).toEqual([]);
});

it('keeps every :placeholder exactly as the source has it', () => {
    const broken = Object.keys(source).filter(
        (id) => id in es && !samePlaceholders(id, COLON),
    );

    expect(broken).toEqual([]);
});

it('keeps a plural message plural, with every form carrying {count}', () => {
    const plurals = Object.keys(source).filter(
        (id) => typeof source[id] === 'object',
    );

    // Sanity: the source really has plurals, so this test is not vacuous.
    expect(plurals.length).toBeGreaterThan(0);

    for (const id of plurals) {
        const translated = es[id];

        expect(typeof translated, `${id} should stay a plural object`).toBe(
            'object',
        );

        const object = translated as Record<string, string>;

        // `other` is what every locale falls back to; without it a count the
        // locale's rules do not otherwise name renders nothing.
        expect(Object.keys(object), `${id} needs an "other" form`).toContain(
            'other',
        );

        for (const [form, text] of Object.entries(object)) {
            expect(text, `${id}.${form} must interpolate {count}`).toContain(
                '{count}',
            );
        }
    }
});

it('renders Spanish through the runtime, selecting the right plural form', () => {
    const duck = createDuck({ sourceLocale: 'en', onMissing: false });
    duck.load('es', es as Parameters<typeof duck.load>[1]);
    duck.setLocale('es');

    const { __ } = duck;

    // A plain message.
    expect(__('Log in')).toBe('Iniciar sesión');

    // Interpolation.
    expect(__('Switched to {name}.', { name: 'Café La Esquina' })).toBe(
        'Cambiaste a Café La Esquina.',
    );

    // `context` keeps homonyms apart: the button is a verb, the ledger entry
    // a noun.
    expect(__('Charge', { context: 'verb: charge a card' })).toBe('Cobrar');
    expect(__('Charge', { context: 'transaction type' })).toBe('Cobro');

    // Intl.PluralRules picks `one` for exactly 1 and `other` otherwise. The
    // plural object is written out at each call (not hoisted to a variable)
    // because `__()` needs a static message at the call site.
    expect(
        __({ one: '{count} card', other: '{count} cards' }, { count: 1 }),
    ).toBe('1 tarjeta');
    expect(
        __({ one: '{count} card', other: '{count} cards' }, { count: 2 }),
    ).toBe('2 tarjetas');
    expect(
        __({ one: '{count} card', other: '{count} cards' }, { count: 0 }),
    ).toBe('0 tarjetas');

    // Stock counts agree in number.
    expect(
        __(
            { one: '{count} card in stock', other: '{count} cards in stock' },
            { count: 1 },
        ),
    ).toBe('1 tarjeta en inventario');
    expect(
        __(
            { one: '{count} card in stock', other: '{count} cards in stock' },
            { count: 3 },
        ),
    ).toBe('3 tarjetas en inventario');
});

it('falls back to the English source for a message it has no entry for', () => {
    const duck = createDuck({ sourceLocale: 'en', onMissing: false });
    duck.load('es', {});
    duck.setLocale('es');

    // The designed failure mode, and why a partial catalog is safe to ship: a
    // miss renders the inline source text, never an empty string or a hash.
    expect(duck.__('Log in')).toBe('Log in');
});
