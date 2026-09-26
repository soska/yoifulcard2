import { useCallback, useSyncExternalStore } from 'react';

/**
 * Reader preferences and history, kept in this device's localStorage.
 * Recent scans hold the card code and the time only, never the QR token.
 */
export type RecentScan = {
    code: string;
    scannedAt: string;
};

const RECENT_KEY = 'yoiful.reader.recent-scans';
const SOUND_KEY = 'yoiful.reader.sound';
const MAX_RECENT = 10;
const CHANGE_EVENT = 'yoiful:reader-storage';

function read(key: string): string | null {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function write(key: string, value: string | null): void {
    try {
        if (value === null) {
            window.localStorage.removeItem(key);
        } else {
            window.localStorage.setItem(key, value);
        }
    } catch {
        // Storage can be full or blocked (private mode). The reader still works.
    }

    window.dispatchEvent(new Event(CHANGE_EVENT));
}

function subscribe(onChange: () => void): () => void {
    window.addEventListener(CHANGE_EVENT, onChange);
    window.addEventListener('storage', onChange);

    return () => {
        window.removeEventListener(CHANGE_EVENT, onChange);
        window.removeEventListener('storage', onChange);
    };
}

function parseRecent(raw: string | null): RecentScan[] {
    if (!raw) {
        return [];
    }

    try {
        const value: unknown = JSON.parse(raw);

        if (!Array.isArray(value)) {
            return [];
        }

        // Keep only the two allowed fields, whatever was stored.
        return value
            .filter(
                (item): item is RecentScan =>
                    typeof item === 'object' &&
                    item !== null &&
                    typeof (item as RecentScan).code === 'string' &&
                    typeof (item as RecentScan).scannedAt === 'string',
            )
            .map(({ code, scannedAt }) => ({ code, scannedAt }))
            .slice(0, MAX_RECENT);
    } catch {
        return [];
    }
}

// useSyncExternalStore needs a stable snapshot, so cache the parsed list by
// its raw string.
let recentCache: { raw: string | null; scans: RecentScan[] } = {
    raw: null,
    scans: [],
};

function recentSnapshot(): RecentScan[] {
    const raw = read(RECENT_KEY);

    if (raw !== recentCache.raw) {
        recentCache = { raw, scans: parseRecent(raw) };
    }

    return recentCache.scans;
}

const emptyScans: RecentScan[] = [];

/** Add a scanned card to the top of the recent list. */
export function recordRecentScan(code: string): void {
    const scans = [
        { code, scannedAt: new Date().toISOString() },
        ...recentSnapshot().filter((scan) => scan.code !== code),
    ].slice(0, MAX_RECENT);

    write(RECENT_KEY, JSON.stringify(scans));
}

export function useRecentScans() {
    const scans = useSyncExternalStore(
        subscribe,
        recentSnapshot,
        () => emptyScans,
    );

    const clear = useCallback(() => write(RECENT_KEY, null), []);

    return { scans, clear };
}

/** Whether reader sounds are on. Off unless the user turned them on. */
export function isReaderSoundOn(): boolean {
    return read(SOUND_KEY) === 'on';
}

export function useReaderSound() {
    const enabled = useSyncExternalStore(
        subscribe,
        isReaderSoundOn,
        () => false,
    );

    const setEnabled = useCallback(
        (value: boolean) => write(SOUND_KEY, value ? 'on' : null),
        [],
    );

    return { enabled, setEnabled };
}
