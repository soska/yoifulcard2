export type OrganizationStatus = 'active' | 'suspended' | 'cancelled';

export type MembershipRole = 'owner' | 'manager' | 'employee';

export type CurrentOrganization = {
    id: string;
    name: string;
    status: OrganizationStatus;
    role: MembershipRole;
};
