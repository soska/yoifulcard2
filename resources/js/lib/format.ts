/**
 * Display formatting for money and dates. Every function takes the Intl
 * locale (`en-US` or `es-MX`, `activeLocale()` from `@/i18n`). Components use
 * the `useDateFormat` and `useMoneyFormat` hooks, which bind the locale and
 * the organization's timezone.
 */
export const DEFAULT_LOCALE = 'en-US';

/** Format a decimal string such as "12.50" as money. Display only. */
export function formatMoney(
    amount: string,
    currency: string,
    locale: string = DEFAULT_LOCALE,
): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
    }).format(Number(amount));
}

/**
 * The fallback timezone when no organization is loaded. Matches the
 * `organizations.timezone` column default.
 */
export const DEFAULT_TIME_ZONE = 'America/Mexico_City';

type DateOptions = {
    /** IANA timezone to show the date in, normally the organization's. */
    timeZone?: string;
    /** Shown when the value is empty. */
    fallback?: string;
    /** Intl locale, `en-US` or `es-MX`. */
    locale?: string;
};

/** Format an ISO timestamp as a date in the given timezone. */
export function formatDate(
    value: string | null,
    {
        timeZone = DEFAULT_TIME_ZONE,
        fallback = '—',
        locale = DEFAULT_LOCALE,
    }: DateOptions = {},
): string {
    if (!value) {
        return fallback;
    }

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeZone,
    }).format(new Date(value));
}

/** Format an ISO timestamp as a date and time in the given timezone. */
export function formatDateTime(
    value: string | null,
    {
        timeZone = DEFAULT_TIME_ZONE,
        fallback = '—',
        locale = DEFAULT_LOCALE,
    }: DateOptions = {},
): string {
    if (!value) {
        return fallback;
    }

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone,
    }).format(new Date(value));
}

/**
 * Format a decimal string as money with an explicit sign, such as "+$5.00"
 * or "-$2.50". Pass `signed = false` for a plain amount.
 */
export function formatSignedMoney(
    amount: string,
    currency: string,
    signed = true,
    locale: string = DEFAULT_LOCALE,
): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        signDisplay: signed ? 'exceptZero' : 'auto',
    }).format(Number(amount));
}

/**
 * Format a calendar date such as "2026-09-15" (already a local day of the
 * organization, so no timezone shift applies). `style` picks a short
 * "Sep 15" label for chart axes or a medium date.
 */
export function formatDay(
    date: string,
    style: 'short' | 'medium' = 'medium',
    locale: string = DEFAULT_LOCALE,
): string {
    const options: Intl.DateTimeFormatOptions =
        style === 'short'
            ? { month: 'short', day: 'numeric', timeZone: 'UTC' }
            : { dateStyle: 'medium', timeZone: 'UTC' };

    return new Intl.DateTimeFormat(locale, options).format(
        new Date(`${date}T00:00:00Z`),
    );
}

/** The month name of a calendar date such as "2026-09-01". */
export function formatMonth(
    date: string,
    locale: string = DEFAULT_LOCALE,
): string {
    return new Intl.DateTimeFormat(locale, {
        month: 'long',
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
}
