import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Ban, Copy, Plus, ScanLine } from 'lucide-react';
import { toast } from 'sonner';
import { CardStockNotice } from '@/components/cards/card-stock-notice';
import { CardUsageNotice } from '@/components/cards/card-usage-notice';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { TransactionTypeBadge } from '@/components/transactions/transaction-type-badge';
import { useDateFormat } from '@/hooks/use-date-format';
import { useMoneyFormat } from '@/hooks/use-money-format';
import { dashboard, scan } from '@/routes';
import { create, show } from '@/routes/cards';
import { index as transactionsIndex } from '@/routes/transactions';
import type { CardUsage, TransactionRow } from '@/types';
import { __ } from '@/i18n';

type Props = {
    recentTransactions: TransactionRow[];
    currency: string;
    usage: CardUsage;
    canCreateCards: boolean;
};

// The two things people come to the dashboard to do.
const primaryAction = 'h-16 gap-3 text-base sm:h-20 sm:text-lg [&_svg]:size-6!';

export default function Dashboard({
    usage,
    canCreateCards,
    recentTransactions,
    currency,
}: Props) {
    const { formatMoney } = useMoneyFormat();
    const { formatDateTime } = useDateFormat();

    async function copyAmount(amount: string) {
        try {
            await navigator.clipboard.writeText(amount);
            toast.success(__('Copied'));
        } catch {
            toast.error(__('The amount could not be copied.'));
        }
    }
    const createBlocked = !canCreateCards || usage.atLimit;

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

                {!canCreateCards && (
                    <Alert variant="destructive">
                        <Ban />
                        <AlertTitle>{__('Create card')}</AlertTitle>
                        <AlertDescription>
                            {__('This business is suspended. Contact support.')}
                        </AlertDescription>
                    </Alert>
                )}
                <CardUsageNotice usage={usage} />
                <CardStockNotice usage={usage} />

                <Card>
                    <CardHeader>
                        <CardTitle>{__('Recent activity')}</CardTitle>
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
                    </CardHeader>
                    <CardContent>
                        {recentTransactions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {__('No activity yet')}
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {recentTransactions
                                    .slice(0, 5)
                                    .map((transaction) => (
                                        <li
                                            key={transaction.id}
                                            className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                        >
                                            <div className="min-w-0 space-y-1.5">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <TransactionTypeBadge
                                                        type={transaction.type}
                                                    />
                                                    <Link
                                                        href={show(
                                                            transaction.card.id,
                                                        )}
                                                        className="font-mono text-sm font-medium hover:underline"
                                                    >
                                                        {transaction.card.code}
                                                    </Link>
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDateTime(
                                                        transaction.created_at,
                                                    )}
                                                </p>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                className="h-auto shrink-0 gap-2 px-2 py-3 text-base font-semibold tabular-nums"
                                                title={__('Copy amount')}
                                                aria-label={`${__('Copy amount')}: ${formatMoney(transaction.amount, currency)}`}
                                                onClick={() =>
                                                    void copyAmount(
                                                        transaction.amount,
                                                    )
                                                }
                                            >
                                                {formatMoney(
                                                    transaction.amount,
                                                    currency,
                                                )}
                                                <Copy className="size-3.5 text-muted-foreground" />
                                            </Button>
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

Dashboard.layout = () => ({
    breadcrumbs: [
        {
            title: __('Dashboard'),
            href: dashboard(),
        },
    ],
});
