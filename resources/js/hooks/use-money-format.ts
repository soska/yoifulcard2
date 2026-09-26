import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import {
    DEFAULT_LOCALE,
    formatMoney as formatMoneyIn,
    formatSignedMoney as formatSignedMoneyIn,
} from '@/lib/format';

/** Money formatters bound to the interface language (`en-US`, `es-MX`). */
export function useMoneyFormat() {
    const { intlLocale } = usePage().props;
    const locale = intlLocale ?? DEFAULT_LOCALE;

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
