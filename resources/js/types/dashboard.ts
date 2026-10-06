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

export type ProgramSettings = {
    name: string;
    terms_url: string | null;
};
