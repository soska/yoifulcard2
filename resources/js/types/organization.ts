import type {
    MembershipRole,
    OrganizationStatus,
} from '@/types/enums.generated';

export type { MembershipRole, OrganizationStatus };

export type CurrentOrganization = {
    id: string;
    name: string;
    status: OrganizationStatus;
    /** IANA timezone; dates and times are shown in it. */
    timezone: string;
    role: MembershipRole;
    /** Whether the business creates card batches itself. */
    can_preissue: boolean;
};

/** One of the user's businesses, as listed in the business switcher. */
export type SwitchableOrganization = {
    id: string;
    name: string;
    role: MembershipRole;
};
