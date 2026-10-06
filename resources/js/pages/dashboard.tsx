import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CreditCard,
    Plus,
    ReceiptText,
    ScanLine,
    Wallet,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { CardStockNotice } from '@/components/cards/card-stock-notice';
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
import { useDateFormat } from '@/hooks/use-date-format';
import { useMoneyFormat } from '@/hooks/use-money-format';
import { analytics, dashboard, scan } from '@/routes';
import { create, index as cardsIndex } from '@/routes/cards';
import { index as transactionsIndex } from '@/routes/transactions';
import type { CardUsage, DashboardStats, TransactionRow } from '@/types';
import type { RouteDefinition } from '@/wayfinder';
import { __ } from '@/i18n';

type Props = {
    stats: DashboardStats;
    recentTransactions: TransactionRow[];
    currency: string;
    usage: CardUsage;
    canCreateCards: boolean;
};

// The two things people come to the dashboard to do.
const primaryAction = 'h-16 gap-3 text-base sm:h-20 sm:text-lg [&_svg]:size-6!';

function StatCard({
    title,
    value,
    detail,
    icon,
    href,
}: {
    title: string;
    value: ReactNode;
    detail?: ReactNode;
    icon: ReactNode;
    href: RouteDefinition<'get'>;
}) {
    return (
        <Link
            href={href}
            className="rounded-xl outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
        >
            <Card
                size="sm"
                className="h-full transition-colors hover:bg-muted/50"
            >
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
        </Link>
    );
}

/** Preissued cards not activated yet. */
function cardsInStock(count: number): string {
    return __(
        { one: '{count} card in stock', other: '{count} cards in stock' },
        { count },
    );
}

function cardBreakdown(stats: DashboardStats): string | undefined {
    if (stats.cards === 0) {
        return undefined;
    }

    return [
        __(
            { one: '{count} active', other: '{count} active' },
            { count: stats.active, context: 'cards' },
        ),
        stats.frozen > 0
            ? __(
                  { one: '{count} frozen', other: '{count} frozen' },
                  { count: stats.frozen, context: 'cards' },
              )
            : null,
        stats.depleted > 0
            ? __(
                  { one: '{count} depleted', other: '{count} depleted' },
                  { count: stats.depleted, context: 'cards' },
              )
            : null,
        stats.cancelled > 0
            ? __(
                  { one: '{count} cancelled', other: '{count} cancelled' },
                  { count: stats.cancelled, context: 'cards' },
              )
            : null,
        stats.inactive > 0 ? cardsInStock(stats.inactive) : null,
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
    const { formatMoney } = useMoneyFormat();
    const { formatMonth } = useDateFormat();

    return (
        <>
            <Head title={__('Dashboard')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    {createBlocked ? (
                        <Button
                            size="lg"
                            className={primaryAction}
                            disabled
                            title={
                                canCreateCards
                                    ? __('Card limit reached')
                                    : __(
                                          'This business is suspended. Contact support.',
                                      )
                            }
                        >
                            <Plus data-icon="inline-start" />
                            {__('Create card')}
                        </Button>
                    ) : (
                        <Button
                            size="lg"
                            className={primaryAction}
                            nativeButton={false}
                            render={<Link href={create()} />}
                        >
                            <Plus data-icon="inline-start" />
                            {__('Create card')}
                        </Button>
                    )}
                    <Button
                        size="lg"
                        variant="outline"
                        className={primaryAction}
                        nativeButton={false}
                        render={<Link href={scan()} />}
                    >
                        <ScanLine data-icon="inline-start" />
                        {__('Open reader')}
                    </Button>
                </div>

                <CardUsageNotice usage={usage} />
                <CardStockNotice usage={usage} />

                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard
                        title={__('Cards')}
                        value={
                            // Cards in stock don't count until activated.
                            usage.limit === null
                                ? usage.used
                                : `${usage.used} / ${usage.limit}`
                        }
                        detail={cardBreakdown(stats)}
                        icon={<CreditCard />}
                        href={cardsIndex()}
                    />
                    <StatCard
                        title={__('Outstanding balance')}
                        value={formatMoney(stats.outstandingBalance, currency)}
                        detail={__(
                            {
                                one: '{count} transaction in {month}',
                                other: '{count} transactions in {month}',
                            },
                            {
                                count: stats.monthTransactions,
                                month: formatMonth(stats.month),
                            },
                        )}
                        icon={<Wallet />}
                        href={analytics()}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{__('Recent activity')}</CardTitle>
                        <CardDescription>
                            {__('The latest transactions across all cards.')}
                        </CardDescription>
                        {recentTransactions.length > 0 && (
                            <CardAction>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    nativeButton={false}
                                    render={<Link href={transactionsIndex()} />}
                                >
                                    {__('View all')}
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
                                    <EmptyTitle>
                                        {__('No activity yet')}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {__(
                                            'Create a card and add funds to get started.',
                                        )}
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

Dashboard.layout = () => ({
    breadcrumbs: [
        {
            title: __('Dashboard'),
            href: dashboard(),
        },
    ],
});
