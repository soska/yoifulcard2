import { usePage } from '@inertiajs/react';
import {
    Building2,
    ChartColumn,
    Users,
    CreditCard,
    LayoutGrid,
    Package,
    ReceiptText,
} from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { analytics, dashboard } from '@/routes';
import { index as adminOrganizationsIndex } from '@/routes/admin/organizations';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as batchesIndex } from '@/routes/batches';
import { index as cardsIndex } from '@/routes/cards';
import { index as transactionsIndex } from '@/routes/transactions';
import type { CurrentOrganization, NavItem } from '@/types';
import { __ } from '@/i18n';

/**
 * Built per render: `__()` at module scope would freeze English. Card
 * batches show for owners and managers of a business that can preissue
 * cards (OrganizationPolicy::viewBatches).
 */
function mainNavItems(organization: CurrentOrganization | null): NavItem[] {
    const batches =
        organization?.can_preissue === true &&
        (organization.role === 'owner' || organization.role === 'manager');

    return [
        {
            title: __('Dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: __('Cards'),
            href: cardsIndex(),
            icon: CreditCard,
        },
        ...(batches
            ? [
                  {
                      title: __('Card batches'),
                      href: batchesIndex(),
                      icon: Package,
                  },
              ]
            : []),
        {
            title: __('Transactions'),
            href: transactionsIndex(),
            icon: ReceiptText,
        },
        {
            title: __('Overview'),
            href: analytics(),
            icon: ChartColumn,
        },
    ];
}

function adminNavItems(): NavItem[] {
    return [
        {
            title: __('Organizations'),
            href: adminOrganizationsIndex(),
            icon: Building2,
        },
        {
            title: __('Users'),
            href: adminUsersIndex(),
            icon: Users,
        },
    ];
}

export function AppSidebar() {
    const { auth, currentOrganization } = usePage().props;
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <NavUser />
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    items={mainNavItems(currentOrganization)}
                    label={__('Business')}
                />
                {auth.isSuperadmin && (
                    <NavMain items={adminNavItems()} label={__('Superadmin')} />
                )}
            </SidebarContent>
        </Sidebar>
    );
}
