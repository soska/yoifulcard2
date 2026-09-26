export type OrganizationStatus = 'active' | 'suspended' | 'cancelled';

export type MembershipRole = 'owner' | 'manager' | 'employee';

export type CurrentOrganization = {
    id: string;
    name: string;
    status: OrganizationStatus;
    /** IANA timezone; dates and times are shown in it. */
    timezone: string;
    role: MembershipRole;
};

/** One of the user's businesses, as listed in the business switcher. */
export type SwitchableOrganization = {
    id: string;
    name: string;
    role: MembershipRole;
};
