import { Head, router } from '@inertiajs/react';
import { Download, Filter, ReceiptText, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ListPagination } from '@/components/cards/list-pagination';
import Heading from '@/components/heading';
import { transactionTypeLabels } from '@/components/transactions/transaction-type-badge';
import { TransactionsTable } from '@/components/transactions/transactions-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { exportMethod, index } from '@/routes/transactions';
import type {
    Paginated,
    TransactionFilters,
    TransactionRow,
    TransactionType,
} from '@/types';

type Props = {
    transactions: Paginated<TransactionRow>;
    filters: TransactionFilters;
    types: TransactionType[];
    currency: string;
};

const ALL = 'all';

function toQuery(filters: TransactionFilters) {
    return {
        type: filters.type ?? undefined,
        from: filters.from || undefined,
        to: filters.to || undefined,
        card: filters.card || undefined,
    };
}

export default function TransactionsIndex({
    transactions,
    filters,
    types,
    currency,
}: Props) {
    const [draft, setDraft] = useState<TransactionFilters>(filters);

    const visit = (next: TransactionFilters) => {
        router.get(
            index.url({ query: toQuery(next) }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        visit({ ...draft, card: draft.card.trim() });
    };

    const empty: TransactionFilters = {
        type: null,
        from: null,
        to: null,
        card: '',
    };

    const clear = () => {
        setDraft(empty);
        visit(empty);
    };

    const filtered =
        filters.type !== null ||
        filters.from !== null ||
        filters.to !== null ||
        filters.card !== '';

    const typeItems = [
        { value: ALL, label: 'All types' },
        ...types.map((type) => ({
            value: type,
            label: transactionTypeLabels[type],
        })),
    ];

    return (
        <>
            <Head title="Transactions" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Transactions"
                        description="Every load, charge, and adjustment on your cards."
                    />
                    <Button
                        variant="outline"
                        nativeButton={false}
                        render={
                            <a
                                href={exportMethod.url({
                                    query: toQuery(filters),
                                })}
                            />
                        }
                    >
                        <Download data-icon="inline-start" />
                        Export CSV
                    </Button>
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4">
                        <form
                            onSubmit={submit}
                            className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto]"
                            role="search"
                            aria-label="Filter transactions"
                        >
                            <Field>
                                <FieldLabel htmlFor="filter-type">
                                    Type
                                </FieldLabel>
                                <Select
                                    value={draft.type ?? ALL}
                                    onValueChange={(value) =>
                                        setDraft({
                                            ...draft,
                                            type:
                                                value === ALL || value === null
                                                    ? null
                                                    : (value as TransactionType),
                                        })
                                    }
                                    items={typeItems}
                                >
                                    <SelectTrigger
                                        id="filter-type"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {typeItems.map((item) => (
                                            <SelectItem
                                                key={item.value}
                                                value={item.value}
                                            >
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="filter-from">
                                    From
                                </FieldLabel>
                                <Input
                                    id="filter-from"
                                    type="date"
                                    value={draft.from ?? ''}
                                    max={draft.to ?? undefined}
                                    onChange={(event) =>
                                        setDraft({
                                            ...draft,
                                            from: event.target.value || null,
                                        })
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="filter-to">To</FieldLabel>
                                <Input
                                    id="filter-to"
                                    type="date"
                                    value={draft.to ?? ''}
                                    min={draft.from ?? undefined}
                                    onChange={(event) =>
                                        setDraft({
                                            ...draft,
                                            to: event.target.value || null,
                                        })
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="filter-card">
                                    Card code
                                </FieldLabel>
                                <Input
                                    id="filter-card"
                                    type="search"
                                    value={draft.card}
                                    placeholder="YGFT-"
                                    onChange={(event) =>
                                        setDraft({
                                            ...draft,
                                            card: event.target.value,
                                        })
                                    }
                                />
                            </Field>
                            <div className="flex items-end gap-2">
                                <Button type="submit" variant="secondary">
                                    <Filter data-icon="inline-start" />
                                    Apply
                                </Button>
                                {filtered && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={clear}
                                    >
                                        <X data-icon="inline-start" />
                                        Clear
                                    </Button>
                                )}
                            </div>
                        </form>

                        {transactions.data.length === 0 ? (
                            <Empty className="border">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <ReceiptText />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {filtered
                                            ? 'No transactions match these filters'
                                            : 'No transactions yet'}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {filtered
                                            ? 'Try another type, date range, or card code.'
                                            : 'Loads, charges, and adjustments show up here.'}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <TransactionsTable
                                transactions={transactions.data}
                                currency={currency}
                            />
                        )}

                        {transactions.total > 0 && (
                            <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                                <p className="text-sm text-muted-foreground">
                                    Showing {transactions.from} to{' '}
                                    {transactions.to} of {transactions.total}{' '}
                                    transactions
                                </p>
                                <div>
                                    <ListPagination paginator={transactions} />
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TransactionsIndex.layout = {
    breadcrumbs: [{ title: 'Transactions', href: index() }],
};
