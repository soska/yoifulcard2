import { Head, Link, router } from '@inertiajs/react';
import { Building2, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    OrganizationStatusBadge,
    organizationStatusLabels,
} from '@/components/admin/organization-status-badge';
import { ListPagination } from '@/components/cards/list-pagination';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useDateFormat } from '@/hooks/use-date-format';
import { index as adminIndex } from '@/routes/admin';
import { create, index, show } from '@/routes/admin/organizations';
import type {
    AdminOrganizationFilters,
    AdminOrganizationRow,
    OrganizationStatus,
    Paginated,
} from '@/types';

type Props = {
    organizations: Paginated<AdminOrganizationRow>;
    filters: AdminOrganizationFilters;
    statuses: OrganizationStatus[];
};

const ALL = 'all';

export default function AdminOrganizationsIndex({
    organizations,
    filters,
    statuses,
}: Props) {
    const { formatDate } = useDateFormat();
    const [search, setSearch] = useState(filters.q);

    const visit = (changes: Partial<AdminOrganizationFilters>) => {
        const next = { ...filters, ...changes };

        router.get(
            index.url({
                query: {
                    q: next.q || undefined,
                    status: next.status ?? undefined,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ q: search.trim() });
    };

    const filtered = filters.q !== '' || filters.status !== null;
    const statusItems = [
        { value: ALL, label: 'All statuses' },
        ...statuses.map((status) => ({
            value: status,
            label: organizationStatusLabels[status],
        })),
    ];

    return (
        <>
            <Head title="Organizations" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Organizations"
                        description={`${organizations.total} ${organizations.total === 1 ? 'organization' : 'organizations'}`}
                    />
                    <Button render={<Link href={create()} />}>
                        <Plus data-icon="inline-start" />
                        Create organization
                    </Button>
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <form
                                onSubmit={submitSearch}
                                className="flex flex-1 gap-2"
                                role="search"
                            >
                                <Input
                                    type="search"
                                    name="q"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search by name or slug"
                                    aria-label="Search by name or slug"
                                />
                                <Button type="submit" variant="secondary">
                                    <Search data-icon="inline-start" />
                                    Search
                                </Button>
                            </form>
                            <Select
                                value={filters.status ?? ALL}
                                onValueChange={(value) =>
                                    visit({
                                        status:
                                            value === ALL || value === null
                                                ? null
                                                : (value as OrganizationStatus),
                                    })
                                }
                                items={statusItems}
                            >
                                <SelectTrigger
                                    className="w-full sm:w-44"
                                    aria-label="Filter by status"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {statusItems.map((item) => (
                                        <SelectItem
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {organizations.data.length === 0 ? (
                            <Empty className="border">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Building2 />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {filtered
                                            ? 'No organizations match your search'
                                            : 'No organizations yet'}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {filtered
                                            ? 'Try another name, slug, or status.'
                                            : 'Create one to onboard a business.'}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Organization</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Members
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Cards
                                        </TableHead>
                                        <TableHead>Created</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {organizations.data.map((organization) => (
                                        <TableRow key={organization.id}>
                                            <TableCell>
                                                <Link
                                                    href={show(organization.id)}
                                                    className="font-medium hover:underline"
                                                >
                                                    {organization.name}
                                                </Link>
                                                <div className="text-xs text-muted-foreground">
                                                    /{organization.slug}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <OrganizationStatusBadge
                                                    status={organization.status}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {organization.members_count}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {organization.cards_count}
                                                {organization.card_limit !==
                                                    null && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        /{' '}
                                                        {
                                                            organization.card_limit
                                                        }
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatDate(
                                                    organization.created_at,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        {organizations.total > 0 && (
                            <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                                <p className="text-sm text-muted-foreground">
                                    Showing {organizations.from} to{' '}
                                    {organizations.to} of {organizations.total}{' '}
                                    organizations
                                </p>
                                <div>
                                    <ListPagination paginator={organizations} />
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminOrganizationsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: adminIndex() },
        { title: 'Organizations', href: index() },
    ],
};
