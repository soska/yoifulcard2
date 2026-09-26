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
import { useDateFormat } from '@/hooks/use-date-format';
import { useMoneyFormat } from '@/hooks/use-money-format';
import type { Translate } from '@/lib/i18n';
import { analytics, dashboard, scan } from '@/routes';
import { create, index as cardsIndex } from '@/routes/cards';
import { index as transactionsIndex } from '@/routes/transactions';
import type { CardUsage, DashboardStats, TransactionRow } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

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

function cardBreakdown(
    stats: DashboardStats,
    t: Translate,
): string | undefined {
    if (stats.cards === 0) {
        return undefined;
    }

    return [
        t(':count active', { count: stats.active }),
        stats.frozen > 0 ? t(':count frozen', { count: stats.frozen }) : null,
        stats.depleted > 0
            ? t(':count depleted', { count: stats.depleted })
            : null,
        stats.cancelled > 0
            ? t(':count cancelled', { count: stats.cancelled })
            : null,
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
    const { t } = useTranslation();
    const { formatMoney } = useMoneyFormat();
    const { formatMonth } = useDateFormat();

    return (
        <>
            <Head title={t('Dashboard')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap gap-2">
                    {createBlocked ? (
                        <Button
                            size="lg"
                            disabled
                            title={
                                canCreateCards
                                    ? t('Card limit reached')
                                    : t(
                                          'This business is suspended. Contact support.',
                                      )
                            }
                        >
                            <Plus data-icon="inline-start" />
                            {t('Create card')}
                        </Button>
                    ) : (
                        <Button
                            size="lg"
                            nativeButton={false}
                            render={<Link href={create()} />}
                        >
                            <Plus data-icon="inline-start" />
                            {t('Create card')}
                        </Button>
                    )}
                    <Button
                        size="lg"
                        variant="outline"
                        nativeButton={false}
                        render={<Link href={scan()} />}
                    >
                        <ScanLine data-icon="inline-start" />
                        {t('Open reader')}
                    </Button>
                    <Button
                        size="lg"
                        variant="outline"
                        nativeButton={false}
                        render={<Link href={cardsIndex()} />}
                    >
                        <List data-icon="inline-start" />
                        {t('All cards')}
                    </Button>
                    <Button
                        size="lg"
                        variant="outline"
                        nativeButton={false}
                        render={<Link href={transactionsIndex()} />}
                    >
                        <ReceiptText data-icon="inline-start" />
                        {t('Transactions')}
                    </Button>
                    <Button
                        size="lg"
                        variant="outline"
                        nativeButton={false}
                        render={<Link href={analytics()} />}
                    >
                        <ChartColumn data-icon="inline-start" />
                        {t('Analytics')}
                    </Button>
                </div>

                <CardUsageNotice usage={usage} />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <StatCard
                        title={t('Cards')}
                        value={
                            usage.limit === null
                                ? stats.cards
                                : `${stats.cards} / ${usage.limit}`
                        }
                        detail={cardBreakdown(stats, t)}
                        icon={<CreditCard />}
                    />
                    <StatCard
                        title={t('Outstanding balance')}
                        value={formatMoney(stats.outstandingBalance, currency)}
                        detail={t('Across all cards')}
                        icon={<Wallet />}
                    />
                    <StatCard
                        title={t('This month')}
                        value={stats.monthTransactions}
                        detail={t('Transactions in :month', {
                            month: formatMonth(stats.month),
                        })}
                        icon={<Activity />}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Recent activity')}</CardTitle>
                        <CardDescription>
                            {t('The latest transactions across all cards.')}
                        </CardDescription>
                        {recentTransactions.length > 0 && (
                            <CardAction>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    nativeButton={false}
                                    render={<Link href={transactionsIndex()} />}
                                >
                                    {t('View all')}
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
                                        {t('No activity yet')}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {t(
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

Dashboard.layout = {
    breadcrumbs: [
        {
            titleKey: 'Dashboard',
            href: dashboard(),
        },
    ],
};
