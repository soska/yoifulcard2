import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    CreditCard,
    Package,
    Plus,
    Search,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { CardStatusBadge } from '@/components/cards/card-status-badge';
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
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
import { useMoneyFormat } from '@/hooks/use-money-format';
import { create, index, show } from '@/routes/cards';
import type {
    CardBatchFilter,
    CardFilters,
    CardSort,
    CardStatus,
    CardSummary,
    CardUsage,
    Paginated,
} from '@/types';
import { __ } from '@/i18n';
import { cardStatusLabel } from '@/lib/labels';

type Props = {
    cards: Paginated<CardSummary>;
    filters: CardFilters;
    statuses: CardStatus[];
    currency: string;
    usage: CardUsage;
    /** The batch the list is filtered by, if any. */
    batch: CardBatchFilter | null;
};

const ALL = 'all';

export default function CardsIndex({
    cards,
    filters,
    statuses,
    currency,
    usage,
    batch,
}: Props) {
    const { currentOrganization } = usePage().props;
    const { formatDate, formatDateTime } = useDateFormat();
    const { formatMoney } = useMoneyFormat();
    const writable = currentOrganization?.status === 'active';
    const [search, setSearch] = useState(filters.q);

    const visit = (changes: Partial<CardFilters>) => {
        const next = { ...filters, ...changes };

        router.get(
            index.url({
                query: {
                    view: next.view,
                    sort: next.sort,
                    direction: next.direction,
                    status: next.status ?? undefined,
                    q: next.q || undefined,
                    batch: next.batch ?? undefined,
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

    const inventory = filters.view === 'inventory';
    const visibleStatuses = statuses.filter((status) => status !== 'inactive');

    const filtered =
        (!inventory && filters.status !== null) ||
        filters.q !== '' ||
        filters.batch !== null;

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
            <Head title={__('Cards')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={__('Cards')}
                        description={
                            usage.limit === null
                                ? __(
                                      {
                                          one: '{count} card',
                                          other: '{count} cards',
                                      },
                                      { count: usage.used },
                                  )
                                : __('{used} of {limit} cards issued', {
                                      used: usage.used,
                                      limit: usage.limit,
                                  })
                        }
                    />
                    {writable && !usage.atLimit ? (
                        <Button render={<Link href={create()} />}>
                            <Plus data-icon="inline-start" />
                            {__('Create card')}
                        </Button>
                    ) : (
                        <Button
                            disabled
                            title={
                                writable
                                    ? __('Card limit reached')
                                    : __(
                                          'This business is suspended. Contact support.',
                                      )
                            }
                        >
                            <Plus data-icon="inline-start" />
                            {__('Create card')}
                        </Button>
                    )}
                </div>

                <CardUsageNotice usage={usage} />

                {batch && (
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <Package className="size-4 text-muted-foreground" />
                        <span>
                            {__('Only cards from the batch of {date}.', {
                                date: formatDateTime(batch.created_at),
                            })}
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => visit({ batch: null })}
                        >
                            <X data-icon="inline-start" />
                            {__('Show all cards')}
                        </Button>
                    </div>
                )}

                <Tabs value={filters.view}>
                    <TabsList variant="line" aria-label={__('Cards')}>
                        <TabsTrigger
                            value="issued"
                            onClick={() =>
                                visit({ view: 'issued', status: null })
                            }
                        >
                            {__('Issued')}{' '}
                            <span className="text-muted-foreground tabular-nums">
                                {usage.used}
                            </span>
                        </TabsTrigger>
                        <TabsTrigger
                            value="inventory"
                            onClick={() =>
                                visit({ view: 'inventory', status: null })
                            }
                        >
                            {__('In stock')}{' '}
                            <span className="text-muted-foreground tabular-nums">
                                {usage.stock}
                            </span>
                        </TabsTrigger>
                        {batch && (
                            <TabsTrigger
                                value="all"
                                onClick={() =>
                                    visit({ view: 'all', status: null })
                                }
                            >
                                {__('Show all cards')}
                            </TabsTrigger>
                        )}
                    </TabsList>
                </Tabs>

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
                                    placeholder={__('Search by code or email')}
                                    aria-label={__('Search by code or email')}
                                />
                                <Button type="submit" variant="secondary">
                                    <Search data-icon="inline-start" />
                                    {__('Search')}
                                </Button>
                            </form>
                            {!inventory && (
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
                                        {
                                            value: ALL,
                                            label: __('All statuses'),
                                        },
                                        ...visibleStatuses.map((status) => ({
                                            value: status,
                                            label: cardStatusLabel(status),
                                        })),
                                    ]}
                                >
                                    <SelectTrigger
                                        className="w-full sm:w-44"
                                        aria-label={__('Filter by status')}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>
                                            {__('All statuses')}
                                        </SelectItem>
                                        {visibleStatuses.map((status) => (
                                            <SelectItem
                                                key={status}
                                                value={status}
                                            >
                                                {cardStatusLabel(status)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </div>

                        {cards.data.length === 0 ? (
                            <Empty className="border">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <CreditCard />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {filtered
                                            ? __('No cards match your search')
                                            : __('No cards yet')}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {filtered
                                            ? __(
                                                  'Try another code, email, or status.',
                                              )
                                            : __(
                                                  'Create your first card to get started.',
                                              )}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        {sortHeader('code', __('Code'))}
                                        {!inventory &&
                                            sortHeader(
                                                'balance',
                                                __('Balance'),
                                                true,
                                            )}
                                        {!inventory && (
                                            <TableHead>
                                                {__('Status')}
                                            </TableHead>
                                        )}
                                        {filters.view !== 'issued' &&
                                            sortHeader(
                                                'created_at',
                                                __('Created'),
                                            )}
                                        {!inventory &&
                                            sortHeader(
                                                'last_used_at',
                                                __('Last used'),
                                            )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {cards.data.map((card) => (
                                        <TableRow
                                            key={card.id}
                                            className="cursor-pointer focus-within:bg-muted/50"
                                            onClick={(event) => {
                                                const target =
                                                    event.target as HTMLElement;
                                                if (
                                                    target.closest(
                                                        'a, button',
                                                    ) ||
                                                    window
                                                        .getSelection()
                                                        ?.toString()
                                                ) {
                                                    return;
                                                }
                                                router.visit(show.url(card));
                                            }}
                                        >
                                            <TableCell>
                                                <Link
                                                    href={show(card)}
                                                    className="inline-flex min-h-8 items-center rounded-sm font-mono font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {card.code}
                                                </Link>
                                                {card.email && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {card.email}
                                                    </div>
                                                )}
                                            </TableCell>
                                            {!inventory && (
                                                <>
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
                                                </>
                                            )}
                                            {filters.view !== 'issued' && (
                                                <TableCell className="text-muted-foreground">
                                                    {formatDate(
                                                        card.created_at,
                                                    )}
                                                </TableCell>
                                            )}
                                            {!inventory && (
                                                <TableCell className="text-muted-foreground">
                                                    {formatDate(
                                                        card.last_used_at,
                                                        __('Never'),
                                                    )}
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        {cards.total > 0 && (
                            <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                                <p className="text-sm text-muted-foreground">
                                    {__(
                                        'Showing {from} to {to} of {total} cards',
                                        {
                                            from: cards.from ?? 0,
                                            to: cards.to ?? 0,
                                            total: cards.total,
                                        },
                                    )}
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

CardsIndex.layout = () => ({
    breadcrumbs: [{ title: __('Cards'), href: index() }],
});
