import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import {
    DEFAULT_LOCALE,
    DEFAULT_TIME_ZONE,
    formatDate as formatDateIn,
    formatDateTime as formatDateTimeIn,
    formatDay as formatDayIn,
    formatMonth as formatMonthIn,
} from '@/lib/format';

/**
 * Date formatters bound to the current organization's timezone and the
 * interface language (`en-US` or `es-MX`), so every date the business sees
 * matches its local day and its language.
 */
export function useDateFormat() {
    const { currentOrganization, intlLocale } = usePage().props;
    const timeZone = currentOrganization?.timezone ?? DEFAULT_TIME_ZONE;
    const locale = intlLocale ?? DEFAULT_LOCALE;

    const formatDate = useCallback(
        (value: string | null, fallback?: string) =>
            formatDateIn(value, { timeZone, fallback, locale }),
        [timeZone, locale],
    );

    const formatDateTime = useCallback(
        (value: string | null, fallback?: string) =>
            formatDateTimeIn(value, { timeZone, fallback, locale }),
        [timeZone, locale],
    );

    /** Like formatDateTime, in another timezone (e.g. another business's). */
    const formatDateTimeInZone = useCallback(
        (value: string | null, zone: string, fallback?: string) =>
            formatDateTimeIn(value, { timeZone: zone, fallback, locale }),
        [locale],
    );

    const formatDay = useCallback(
        (date: string, style: 'short' | 'medium' = 'medium') =>
            formatDayIn(date, style, locale),
        [locale],
    );

    const formatMonth = useCallback(
        (date: string) => formatMonthIn(date, locale),
        [locale],
    );

    return {
        timeZone,
        locale,
        formatDate,
        formatDateTime,
        formatDateTimeInZone,
        formatDay,
        formatMonth,
    };
}
