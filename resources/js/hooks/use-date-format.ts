import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import {
    DEFAULT_TIME_ZONE,
    formatDate as formatDateIn,
    formatDateTime as formatDateTimeIn,
} from '@/lib/format';

/**
 * Date formatters bound to the current organization's timezone, so every
 * date the business sees matches its local day.
 */
export function useDateFormat() {
    const { currentOrganization } = usePage().props;
    const timeZone = currentOrganization?.timezone ?? DEFAULT_TIME_ZONE;

    const formatDate = useCallback(
        (value: string | null, fallback?: string) =>
            formatDateIn(value, { timeZone, fallback }),
        [timeZone],
    );

    const formatDateTime = useCallback(
        (value: string | null, fallback?: string) =>
            formatDateTimeIn(value, { timeZone, fallback }),
        [timeZone],
    );

    return { timeZone, formatDate, formatDateTime };
}
