// Locale switching arrives with translations. Until then, format for en-US.
const LOCALE = 'en-US';

/** Format a decimal string such as "12.50" as money. Display only. */
export function formatMoney(amount: string, currency: string): string {
    return new Intl.NumberFormat(LOCALE, {
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
};

/** Format an ISO timestamp as a date in the given timezone. */
export function formatDate(
    value: string | null,
    { timeZone = DEFAULT_TIME_ZONE, fallback = '—' }: DateOptions = {},
): string {
    if (!value) {
        return fallback;
    }

    return new Intl.DateTimeFormat(LOCALE, {
        dateStyle: 'medium',
        timeZone,
    }).format(new Date(value));
}

/** Format an ISO timestamp as a date and time in the given timezone. */
export function formatDateTime(
    value: string | null,
    { timeZone = DEFAULT_TIME_ZONE, fallback = '—' }: DateOptions = {},
): string {
    if (!value) {
        return fallback;
    }

    return new Intl.DateTimeFormat(LOCALE, {
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
): string {
    return new Intl.NumberFormat(LOCALE, {
        style: 'currency',
        currency,
        signDisplay: signed ? 'exceptZero' : 'auto',
    }).format(Number(amount));
}
