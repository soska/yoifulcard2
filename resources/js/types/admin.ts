import type { AdminCardBatch, CardUsage } from '@/types/card';
import type { MembershipRole, OrganizationStatus } from '@/types/organization';

export type AdminStats = {
    organizations: number;
    activeOrganizations: number;
    suspendedOrganizations: number;
    cards: number;
    users: number;
    superadmins: number;
};

export type AdminOrganizationSummary = {
    id: string;
    name: string;
    slug: string;
    status: OrganizationStatus;
    created_at: string | null;
};

export type AdminOrganizationRow = AdminOrganizationSummary & {
    card_limit: number | null;
    members_count: number;
    cards_count: number;
};

export type AdminOrganizationFilters = {
    q: string;
    status: OrganizationStatus | null;
};

export type AdminOrganizationDetail = {
    id: string;
    name: string;
    slug: string;
    status: OrganizationStatus;
    currency: string;
    timezone: string;
    primary_color: string;
    logo_url: string | null;
    card_limit: number | null;
    /** The most unactivated cards the business can hold; null is unlimited. */
    preissue_limit: number | null;
    /** Whether the business creates card batches itself. */
    can_preissue: boolean;
    plan_notes: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type AdminOrganizationStats = {
    inactive: number;
    active: number;
    frozen: number;
    depleted: number;
    cancelled: number;
    /** Decimal string. */
    outstandingBalance: string;
    transactions: number;
    /** Decimal string: the sum of all loads. */
    loaded: string;
};

export type AdminMember = {
    id: string;
    role: MembershipRole;
    name: string | null;
    email: string | null;
    user_id: number;
};

export type AdminProgram = {
    id: string;
    name: string;
    type: string;
    is_active: boolean;
};

export type AdminOrganizationPage = {
    organization: AdminOrganizationDetail;
    usage: CardUsage;
    stats: AdminOrganizationStats;
    members: AdminMember[];
    programs: AdminProgram[];
    batches: AdminCardBatch[];
    maxBatchSize: number;
};

export type AdminUserMembership = {
    organization_id: string;
    organization: string | null;
    role: MembershipRole;
};

export type AdminUserRow = {
    id: number;
    name: string;
    email: string;
    created_at: string | null;
    is_superadmin: boolean;
    superadmin_granted_at: string | null;
    memberships: AdminUserMembership[];
};

/** A generated password, flashed for one response only. */
export type OneTimeCredentials = {
    email: string;
    password: string;
};
