// Locale switching arrives with translations. Until then, format for en-US.
const LOCALE = 'en-US';

/** Format a decimal string such as "12.50" as money. Display only. */
export function formatMoney(amount: string, currency: string): string {
    return new Intl.NumberFormat(LOCALE, {
        style: 'currency',
        currency,
    }).format(Number(amount));
}

export function formatDate(value: string | null, fallback = '—'): string {
    if (!value) {
        return fallback;
    }

    return new Intl.DateTimeFormat(LOCALE, { dateStyle: 'medium' }).format(
        new Date(value),
    );
}

export function formatDateTime(value: string | null, fallback = '—'): string {
    if (!value) {
        return fallback;
    }

    return new Intl.DateTimeFormat(LOCALE, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
