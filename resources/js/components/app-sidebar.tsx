import { usePage } from '@inertiajs/react';
import {
    Building2,
    ChartColumn,
    ShieldCheck,
    Users,
    Settings,
    CreditCard,
    LayoutGrid,
    ReceiptText,
} from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
} from '@/components/ui/sidebar';
import { analytics, dashboard } from '@/routes';
import { index as adminIndex } from '@/routes/admin';
import { index as adminOrganizationsIndex } from '@/routes/admin/organizations';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as cardsIndex } from '@/routes/cards';
import { edit as settingsEdit } from '@/routes/settings';
import { index as transactionsIndex } from '@/routes/transactions';
import type { NavItem } from '@/types';
import { __ } from '@/i18n';

/** Built per render: `__()` at module scope would freeze English. */
function mainNavItems(): NavItem[] {
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
        {
            title: __('Transactions'),
            href: transactionsIndex(),
            icon: ReceiptText,
        },
        {
            title: __('Analytics'),
            href: analytics(),
            icon: ChartColumn,
        },
        {
            title: __('Settings'),
            href: settingsEdit(),
            icon: Settings,
        },
    ];
}

function adminNavItems(): NavItem[] {
    return [
        {
            title: __('Admin'),
            href: adminIndex(),
            icon: ShieldCheck,
        },
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
    const { auth } = usePage().props;
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarContent>
                <NavMain items={mainNavItems()} label={__('Business')} />
                {auth.isSuperadmin && (
                    <NavMain items={adminNavItems()} label={__('Superadmin')} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
