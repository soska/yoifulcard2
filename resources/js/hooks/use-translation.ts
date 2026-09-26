import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import type { Replacements } from '@/lib/i18n';
import { translate, translateChoice } from '@/lib/i18n';

/**
 * `t('Save')` returns the line in the current language. Strings live in
 * `lang/en.json` and `lang/es.json`, keyed by the English text.
 */
export function useTranslation() {
    const { translations, locale: resolved } = usePage().props;
    const locale = resolved.current;
    const intlLocale = resolved.intl;

    const t = useCallback(
        (key: string, replacements?: Replacements) =>
            translate(translations, key, replacements),
        [translations],
    );

    const tc = useCallback(
        (key: string, count: number, replacements?: Replacements) =>
            translateChoice(translations, key, count, replacements),
        [translations],
    );

    return { t, tc, locale, intlLocale };
}
