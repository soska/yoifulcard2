/**
 * A tiny translator for the JSON lines Laravel shares as the
 * `translations` prop. Keys are the English text, like Laravel's `__()`:
 * a missing line falls back to the key itself, so English never breaks.
 */
export type Translations = Record<string, string>;

export type Replacements = Record<string, string | number>;

/** Swap `:name` placeholders, longest names first (Laravel's rule). */
function replace(line: string, replacements?: Replacements): string {
    if (!replacements) {
        return line;
    }

    return Object.keys(replacements)
        .sort((a, b) => b.length - a.length)
        .reduce(
            (text, name) =>
                text.replaceAll(`:${name}`, String(replacements[name])),
            line,
        );
}

export function translate(
    translations: Translations | undefined,
    key: string,
    replacements?: Replacements,
): string {
    return replace(translations?.[key] ?? key, replacements);
}

/**
 * Pick the singular or plural form of a `singular|plural` line, like a
 * two-form `trans_choice`. `:count` is filled in.
 */
export function translateChoice(
    translations: Translations | undefined,
    key: string,
    count: number,
    replacements?: Replacements,
): string {
    const forms = (translations?.[key] ?? key).split('|');
    const line = (count === 1 ? forms[0] : forms[1]) ?? forms[0];

    return replace(line, { count, ...replacements });
}

/** The `t` function from useTranslation, for helpers outside components. */
export type Translate = (key: string, replacements?: Replacements) => string;
