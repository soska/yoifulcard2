import { useCallback } from 'react';
import { activeLocale } from '@/i18n';
import {
    formatMoney as formatMoneyIn,
    formatSignedMoney as formatSignedMoneyIn,
} from '@/lib/format';

/** Money formatters bound to the interface language (`activeLocale()`). */
export function useMoneyFormat() {
    // Stable for the page's lifetime: a language change is a full reload.
    const locale = activeLocale();

    const formatMoney = useCallback(
        (amount: string, currency: string) =>
            formatMoneyIn(amount, currency, locale),
        [locale],
    );

    const formatSignedMoney = useCallback(
        (amount: string, currency: string, signed = true) =>
            formatSignedMoneyIn(amount, currency, signed, locale),
        [locale],
    );

    return { formatMoney, formatSignedMoney };
}
