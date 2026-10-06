import type { CardStatus } from '@/types/enums.generated';

export type { CardStatus };

export type CardSort = 'code' | 'balance' | 'created_at' | 'last_used_at';

export type SortDirection = 'asc' | 'desc';

/** A card as pages see it. The QR token is never included. */
export type CardSummary = {
    id: string;
    code: string;
    balance: string;
    status: CardStatus;
    email: string | null;
    /** The batch a preissued card came from; null for cards made one at a time. */
    batch_id: string | null;
    created_at: string | null;
    last_used_at: string | null;
    /** When a preissued card was activated; null for every other card. */
    activated_at: string | null;
};

export type CardDetail = CardSummary & {
    program: string;
    updated_at: string | null;
};

export type CardUsage = {
    used: number;
    limit: number | null;
    percent: number | null;
    nearLimit: boolean;
    atLimit: boolean;
    /** Preissued cards not activated yet. They don't count toward `used`. */
    stock: number;
};

export type CardFilters = {
    sort: CardSort;
    direction: SortDirection;
    status: CardStatus | null;
    q: string;
    /** Only the cards of this batch. */
    batch: string | null;
};

/** Preissued (inactive) cards made at once for printing. */
export type CardBatchSummary = {
    id: string;
    count: number;
    /** Cards from the batch that were activated, whatever their status now. */
    activated: number;
    /** Cards from the batch still not activated. */
    stock: number;
    created_by: string;
    /** Made by Yoiful on the business's behalf. */
    issued_by_admin: boolean;
    voided_at: string | null;
    created_at: string | null;
};

/** A batch as superadmins see it: with the internal notes. */
export type AdminCardBatch = CardBatchSummary & {
    notes: string | null;
};

/** The batch the card list is filtered by. */
export type CardBatchFilter = {
    id: string;
    count: number;
    created_at: string | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    page: number | null;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    first_page_url: string;
    last_page_url: string;
    next_page_url: string | null;
    prev_page_url: string | null;
    links: PaginationLink[];
};
