import { Head, Link } from '@inertiajs/react';
import { Building2, CreditCard, Plus, ShieldCheck, Users } from 'lucide-react';
import { OrganizationStatusBadge } from '@/components/admin/organization-status-badge';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import { useDateFormat } from '@/hooks/use-date-format';
import { index } from '@/routes/admin';
import {
    create as createOrganization,
    index as organizationsIndex,
    show as showOrganization,
} from '@/routes/admin/organizations';
import { index as usersIndex } from '@/routes/admin/users';
import type { AdminOrganizationSummary, AdminStats } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    stats: AdminStats;
    recentOrganizations: AdminOrganizationSummary[];
};

export default function AdminIndex({ stats, recentOrganizations }: Props) {
    const { formatDate } = useDateFormat();
    const { t } = useTranslation();

    const tiles = [
        {
            label: t('Organizations'),
            value: stats.organizations,
            detail: t(':active active, :suspended suspended', {
                active: stats.activeOrganizations,
                suspended: stats.suspendedOrganizations,
            }),
            icon: Building2,
        },
        {
            label: t('Cards'),
            value: stats.cards,
            detail: t('Across all organizations'),
            icon: CreditCard,
        },
        {
            label: t('Users'),
            value: stats.users,
            detail: t('Registered accounts'),
            icon: Users,
        },
        {
            label: t('Superadmins'),
            value: stats.superadmins,
            detail: t('Can open this area'),
            icon: ShieldCheck,
        },
    ];

    return (
        <>
            <Head title={t('Admin')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('Admin')}
                        description={t(
                            'Manage organizations, plans, and users.',
                        )}
                    />
                    <Button render={<Link href={createOrganization()} />}>
                        <Plus data-icon="inline-start" />
                        {t('Create organization')}
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {tiles.map((tile) => (
                        <Card key={tile.label}>
                            <CardHeader>
                                <CardDescription className="flex items-center gap-2">
                                    <tile.icon className="size-4" />
                                    {tile.label}
                                </CardDescription>
                                <CardTitle className="text-3xl tabular-nums">
                                    {tile.value}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm text-muted-foreground">
                                {tile.detail}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4">
                        <div className="flex flex-col gap-1">
                            <CardTitle>{t('Recent organizations')}</CardTitle>
                            <CardDescription>
                                {t('The newest businesses on Yoiful.')}
                            </CardDescription>
                        </div>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                render={<Link href={usersIndex()} />}
                            >
                                {t('Users')}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                render={<Link href={organizationsIndex()} />}
                            >
                                {t('View all')}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {recentOrganizations.length === 0 ? (
                            <Empty className="border">
                                <EmptyHeader>
                                    <EmptyTitle>
                                        {t('No organizations yet')}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {t('Create one to onboard a business.')}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <ul className="divide-y">
                                {recentOrganizations.map((organization) => (
                                    <li
                                        key={organization.id}
                                        className="flex items-center justify-between gap-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <Link
                                                href={showOrganization(
                                                    organization.id,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {organization.name}
                                            </Link>
                                            <p className="truncate text-sm text-muted-foreground">
                                                /{organization.slug} ·{' '}
                                                {t('Created :date', {
                                                    date: formatDate(
                                                        organization.created_at,
                                                    ),
                                                })}
                                            </p>
                                        </div>
                                        <OrganizationStatusBadge
                                            status={organization.status}
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminIndex.layout = {
    breadcrumbs: [{ title: 'Admin', href: index() }],
};
