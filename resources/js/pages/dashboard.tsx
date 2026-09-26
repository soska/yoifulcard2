import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    ChartColumn,
    CreditCard,
    List,
    Plus,
    ReceiptText,
    ScanLine,
    Wallet,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { CardUsageNotice } from '@/components/cards/card-usage-notice';
import { TransactionsTable } from '@/components/transactions/transactions-table';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { formatMoney, formatMonth } from '@/lib/format';
import { analytics, dashboard, scan } from '@/routes';
import { create, index as cardsIndex } from '@/routes/cards';
import { index as transactionsIndex } from '@/routes/transactions';
import type { CardUsage, DashboardStats, TransactionRow } from '@/types';

type Props = {
    stats: DashboardStats;
    recentTransactions: TransactionRow[];
    currency: string;
    usage: CardUsage;
    canCreateCards: boolean;
};

function StatCard({
    title,
    value,
    detail,
    icon,
}: {
    title: string;
    value: ReactNode;
    detail?: ReactNode;
    icon: ReactNode;
}) {
    return (
        <Card size="sm">
            <CardHeader>
                <CardDescription>{title}</CardDescription>
                <CardTitle className="text-2xl font-semibold tabular-nums">
                    {value}
                </CardTitle>
                <CardAction className="text-muted-foreground [&_svg]:size-4">
                    {icon}
                </CardAction>
            </CardHeader>
            {detail && (
                <CardContent className="text-sm text-muted-foreground">
                    {detail}
                </CardContent>
            )}
        </Card>
    );
}

function cardBreakdown(stats: DashboardStats): string | undefined {
    if (stats.cards === 0) {
        return undefined;
    }

    return [
        `${stats.active} active`,
        stats.frozen > 0 ? `${stats.frozen} frozen` : null,
        stats.depleted > 0 ? `${stats.depleted} depleted` : null,
        stats.cancelled > 0 ? `${stats.cancelled} cancelled` : null,
    ]
        .filter(Boolean)
        .join(' · ');
}

export default function Dashboard({
    stats,
    recentTransactions,
    currency,
    usage,
    canCreateCards,
}: Props) {
    const createBlocked = !canCreateCards || usage.atLimit;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap gap-2">
                    {createBlocked ? (
                        <Button
                            size="lg"
                            disabled
                            title={
                                canCreateCards
                                    ? 'Card limit reached'
                                    : 'This business is suspended. Contact support.'
                            }
                        >
                            <Plus data-icon="inline-start" />
                            Create card
                        </Button>
                    ) : (
                        <Button
                            size="lg"
                            nativeButton={false}
                            render={<Link href={create()} />}
                        >
                            <Plus data-icon="inline-start" />
                            Create card
                        </Button>
                    )}
                    <Button
                        size="lg"
                        variant="outline"
                        nativeButton={false}
                        render={<Link href={scan()} />}
                    >
                        <ScanLine data-icon="inline-start" />
                        Open reader
                    </Button>
                    <Button
                        size="lg"
                        variant="outline"
                        nativeButton={false}
                        render={<Link href={cardsIndex()} />}
                    >
                        <List data-icon="inline-start" />
                        All cards
                    </Button>
                    <Button
                        size="lg"
                        variant="outline"
                        nativeButton={false}
                        render={<Link href={transactionsIndex()} />}
                    >
                        <ReceiptText data-icon="inline-start" />
                        Transactions
                    </Button>
                    <Button
                        size="lg"
                        variant="outline"
                        nativeButton={false}
                        render={<Link href={analytics()} />}
                    >
                        <ChartColumn data-icon="inline-start" />
                        Analytics
                    </Button>
                </div>

                <CardUsageNotice usage={usage} />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <StatCard
                        title="Cards"
                        value={
                            usage.limit === null
                                ? stats.cards
                                : `${stats.cards} / ${usage.limit}`
                        }
                        detail={cardBreakdown(stats)}
                        icon={<CreditCard />}
                    />
                    <StatCard
                        title="Outstanding balance"
                        value={formatMoney(stats.outstandingBalance, currency)}
                        detail="Across all cards"
                        icon={<Wallet />}
                    />
                    <StatCard
                        title="This month"
                        value={stats.monthTransactions}
                        detail={`Transactions in ${formatMonth(stats.month)}`}
                        icon={<Activity />}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent activity</CardTitle>
                        <CardDescription>
                            The latest transactions across all cards.
                        </CardDescription>
                        {recentTransactions.length > 0 && (
                            <CardAction>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    nativeButton={false}
                                    render={<Link href={transactionsIndex()} />}
                                >
                                    View all
                                    <ArrowRight data-icon="inline-end" />
                                </Button>
                            </CardAction>
                        )}
                    </CardHeader>
                    <CardContent>
                        {recentTransactions.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <ReceiptText />
                                    </EmptyMedia>
                                    <EmptyTitle>No activity yet</EmptyTitle>
                                    <EmptyDescription>
                                        Create a card and add funds to get
                                        started.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <TransactionsTable
                                transactions={recentTransactions}
                                currency={currency}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
