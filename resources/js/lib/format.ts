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
