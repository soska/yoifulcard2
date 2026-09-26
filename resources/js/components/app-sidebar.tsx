import { Link, usePage } from '@inertiajs/react';
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
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { SidebarOrganizationSwitcher } from '@/components/organization/organization-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { analytics, dashboard } from '@/routes';
import { index as adminIndex } from '@/routes/admin';
import { index as adminOrganizationsIndex } from '@/routes/admin/organizations';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as cardsIndex } from '@/routes/cards';
import { edit as settingsEdit } from '@/routes/settings';
import { index as transactionsIndex } from '@/routes/transactions';
import type { NavItem } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Cards',
        href: cardsIndex(),
        icon: CreditCard,
    },
    {
        title: 'Transactions',
        href: transactionsIndex(),
        icon: ReceiptText,
    },
    {
        title: 'Analytics',
        href: analytics(),
        icon: ChartColumn,
    },
    {
        title: 'Settings',
        href: settingsEdit(),
        icon: Settings,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Admin',
        href: adminIndex(),
        icon: ShieldCheck,
    },
    {
        title: 'Organizations',
        href: adminOrganizationsIndex(),
        icon: Building2,
    },
    {
        title: 'Users',
        href: adminUsersIndex(),
        icon: Users,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            render={<Link href={dashboard()} prefetch />}
                        >
                            <AppLogo />
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarOrganizationSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} label={t('Business')} />
                {auth.isSuperadmin && (
                    <NavMain items={adminNavItems} label={t('Superadmin')} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
