import type { EarnMode } from '@/types/enums.generated';

export type DashboardStats = {
    cards: number;
    inactive: number;
    active: number;
    frozen: number;
    depleted: number;
    cancelled: number;
    /** Decimal string: the sum of every card's balance. */
    outstandingBalance: string;
    /** First day of the current month in the organization's timezone. */
    month: string;
    monthTransactions: number;
};

/** One local day of analytics. Amounts are decimal strings. */
export type AnalyticsDay = {
    date: string;
    cards: number;
    load: number;
    spend: number;
    adjustment: number;
    loadAmount: string;
    spendAmount: string;
};

export type AnalyticsSummary = {
    cards: number;
    transactions: number;
    loadAmount: string;
    spendAmount: string;
};

export type OrganizationSettings = {
    name: string;
    logo_url: string | null;
    primary_color: string;
    currency: string;
    timezone: string;
};

/** How a program's rewards work. */
export type RewardsSettings = {
    enabled: boolean;
    mode: EarnMode | null;
    /** Points per 1.00 spent, in spend mode. */
    rate: string | null;
    /** The most visits that earn points for one card in a day. */
    visit_cap: number;
};

export type ProgramSettings = {
    name: string;
    terms_url: string | null;
    rewards: RewardsSettings;
};

/** A reward in the catalog. */
export type Reward = {
    id: string;
    name: string;
    points_cost: number;
    is_active: boolean;
};

/** A reward the reader offers for a card. Only active ones. */
export type RewardOption = Pick<Reward, 'id' | 'name' | 'points_cost'>;
