import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    CreditCard,
    Plus,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    CardStatusBadge,
    cardStatusLabels,
} from '@/components/cards/card-status-badge';
import { CardUsageNotice } from '@/components/cards/card-usage-notice';
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
import { formatMoney } from '@/lib/format';
import { create, index, show } from '@/routes/cards';
import type {
    CardFilters,
    CardSort,
    CardStatus,
    CardSummary,
    CardUsage,
    Paginated,
} from '@/types';

type Props = {
    cards: Paginated<CardSummary>;
    filters: CardFilters;
    statuses: CardStatus[];
    currency: string;
    usage: CardUsage;
};

const ALL = 'all';

const columns: { key: CardSort; label: string; align?: 'right' }[] = [
    { key: 'code', label: 'Code' },
    { key: 'balance', label: 'Balance', align: 'right' },
];

const dateColumns: { key: CardSort; label: string }[] = [
    { key: 'created_at', label: 'Created' },
    { key: 'last_used_at', label: 'Last used' },
];

export default function CardsIndex({
    cards,
    filters,
    statuses,
    currency,
    usage,
}: Props) {
    const { currentOrganization } = usePage().props;
    const { formatDate } = useDateFormat();
    const writable = currentOrganization?.status === 'active';
    const [search, setSearch] = useState(filters.q);

    const visit = (changes: Partial<CardFilters>) => {
        const next = { ...filters, ...changes };

        router.get(
            index.url({
                query: {
                    sort: next.sort,
                    direction: next.direction,
                    status: next.status ?? undefined,
                    q: next.q || undefined,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const sortBy = (key: CardSort) => {
        const direction =
            filters.sort === key && filters.direction === 'desc'
                ? 'asc'
                : filters.sort === key
                  ? 'desc'
                  : key === 'code'
                    ? 'asc'
                    : 'desc';

        visit({ sort: key, direction });
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ q: search.trim() });
    };

    const filtered = filters.status !== null || filters.q !== '';

    const sortHeader = (key: CardSort, label: string, alignRight = false) => {
        const active = filters.sort === key;
        const Icon = !active
            ? ArrowUpDown
            : filters.direction === 'asc'
              ? ArrowUp
              : ArrowDown;

        return (
            <TableHead
                key={key}
                className={alignRight ? 'text-right' : undefined}
                aria-sort={
                    active
                        ? filters.direction === 'asc'
                            ? 'ascending'
                            : 'descending'
                        : 'none'
                }
            >
                <Button
                    variant="ghost"
                    size="sm"
                    className={alignRight ? '-mr-2' : '-ml-2'}
                    onClick={() => sortBy(key)}
                >
                    {label}
                    <Icon
                        className={active ? undefined : 'opacity-40'}
                        data-icon="inline-end"
                    />
                </Button>
            </TableHead>
        );
    };

    return (
        <>
            <Head title="Cards" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Cards"
                        description={
                            usage.limit === null
                                ? `${usage.used} cards`
                                : `${usage.used} of ${usage.limit} cards used`
                        }
                    />
                    {writable && !usage.atLimit ? (
                        <Button render={<Link href={create()} />}>
                            <Plus data-icon="inline-start" />
                            Create card
                        </Button>
                    ) : (
                        <Button
                            disabled
                            title={
                                writable
                                    ? 'Card limit reached'
                                    : 'This business is suspended. Contact support.'
                            }
                        >
                            <Plus data-icon="inline-start" />
                            Create card
                        </Button>
                    )}
                </div>

                <CardUsageNotice usage={usage} />

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
                                    placeholder="Search by code or email"
                                    aria-label="Search by code or email"
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
                                                : (value as CardStatus),
                                    })
                                }
                                items={[
                                    { value: ALL, label: 'All statuses' },
                                    ...statuses.map((status) => ({
                                        value: status,
                                        label: cardStatusLabels[status],
                                    })),
                                ]}
                            >
                                <SelectTrigger
                                    className="w-full sm:w-44"
                                    aria-label="Filter by status"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        All statuses
                                    </SelectItem>
                                    {statuses.map((status) => (
                                        <SelectItem key={status} value={status}>
                                            {cardStatusLabels[status]}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {cards.data.length === 0 ? (
                            <Empty className="border">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <CreditCard />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {filtered
                                            ? 'No cards match your search'
                                            : 'No cards yet'}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {filtered
                                            ? 'Try another code, email, or status.'
                                            : 'Create your first card to get started.'}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        {columns.map((column) =>
                                            sortHeader(
                                                column.key,
                                                column.label,
                                                column.align === 'right',
                                            ),
                                        )}
                                        <TableHead>Status</TableHead>
                                        {dateColumns.map((column) =>
                                            sortHeader(
                                                column.key,
                                                column.label,
                                            ),
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {cards.data.map((card) => (
                                        <TableRow key={card.id}>
                                            <TableCell>
                                                <Link
                                                    href={show(card)}
                                                    className="font-mono font-medium hover:underline"
                                                >
                                                    {card.code}
                                                </Link>
                                                {card.email && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {card.email}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    card.balance,
                                                    currency,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <CardStatusBadge
                                                    status={card.status}
                                                />
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatDate(card.created_at)}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatDate(
                                                    card.last_used_at,
                                                    'Never',
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        {cards.total > 0 && (
                            <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                                <p className="text-sm text-muted-foreground">
                                    Showing {cards.from} to {cards.to} of{' '}
                                    {cards.total} cards
                                </p>
                                <div>
                                    <ListPagination paginator={cards} />
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CardsIndex.layout = {
    breadcrumbs: [{ title: 'Cards', href: index() }],
};
