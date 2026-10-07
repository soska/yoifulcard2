import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    CreditCard,
    Wallet,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { MoneyFlowChart } from '@/components/analytics/analytics-charts';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { analytics } from '@/routes';
import {
    exportMethod,
    index as transactionsIndex,
} from '@/routes/transactions';
import type { AnalyticsDay, AnalyticsSummary } from '@/types';
import { __ } from '@/i18n';

type Props = {
    days: number;
    from: string;
    to: string;
    series: AnalyticsDay[];
    summary: AnalyticsSummary;
    ranges: number[];
    currency: string;
    currentBalance: string;
};

function SummaryCard({
    title,
    value,
    icon,
}: {
    title: string;
    value: ReactNode;
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
        </Card>
    );
}

export default function Analytics({
    days,
    from,
    to,
    series,
    summary,
    ranges,
    currency,
    currentBalance,
}: Props) {
    const { formatMoney } = useMoneyFormat();
    const { formatDay } = useDateFormat();
    const rangeItems = ranges.map((range) => ({
        value: String(range),
        label: __('Last {days} days', { days: range }),
    }));

    return (
        <>
            <Head title={__('Overview')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title={__('Overview')}
                        description={__(
                            "{from} to {to}, in your business's timezone.",
                            { from: formatDay(from), to: formatDay(to) },
                        )}
                    />
                    <div className="flex gap-2">
                        <Select
                            value={String(days)}
                            items={rangeItems}
                            onValueChange={(value) =>
                                router.get(
                                    analytics.url({ query: { days: value } }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <SelectTrigger
                                className="w-40"
                                aria-label={__('Date range')}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {rangeItems.map((item) => (
                                    <SelectItem
                                        key={item.value}
                                        value={item.value}
                                    >
                                        {item.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            variant="outline"
                            nativeButton={false}
                            render={
                                <a
                                    href={exportMethod.url({
                                        query: { from, to },
                                    })}
                                />
                            }
                        >
                            <ArrowDownToLine data-icon="inline-start" />
                            {__('Export CSV')}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard
                        title={__('Cards issued')}
                        value={summary.cards}
                        icon={<CreditCard />}
                    />
                    <SummaryCard
                        title={__('Current card balance')}
                        value={formatMoney(currentBalance, currency)}
                        icon={<Wallet />}
                    />
                    <SummaryCard
                        title={__('Loaded')}
                        value={formatMoney(summary.loadAmount, currency)}
                        icon={<ArrowDownToLine />}
                    />
                    <SummaryCard
                        title={__('Charged')}
                        value={formatMoney(summary.spendAmount, currency)}
                        icon={<ArrowUpFromLine />}
                    />
                </div>

                <p className="text-sm text-muted-foreground">
                    {__(
                        'Loads, charges and cards issued follow the selected dates. Current card balance includes all cards as of now.',
                    )}
                </p>
                <MoneyFlowChart series={series} currency={currency} />

                <Card>
                    <CardHeader>
                        <CardTitle>{__('By day')}</CardTitle>
                        <CardDescription>
                            {__(
                                'Daily prepaid activity. Select a date to see its transactions.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <div className="px-(--card-spacing)">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{__('Day')}</TableHead>
                                    <TableHead className="text-right">
                                        {__('Cards issued')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {__('Loads')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {__('Charges')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {__('Adjustments')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {__('Loaded')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {__('Charged')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {[...series].reverse().map((day) => (
                                    <TableRow key={day.date}>
                                        <TableCell className="whitespace-nowrap">
                                            <Link
                                                href={transactionsIndex({
                                                    query: {
                                                        from: day.date,
                                                        to: day.date,
                                                    },
                                                })}
                                                className="rounded-sm font-medium underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                {formatDay(day.date)}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {day.cards}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {day.load}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {day.spend}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {day.adjustment}
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap tabular-nums">
                                            {formatMoney(
                                                day.loadAmount,
                                                currency,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap tabular-nums">
                                            {formatMoney(
                                                day.spendAmount,
                                                currency,
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </Card>
            </div>
        </>
    );
}

Analytics.layout = () => ({
    breadcrumbs: [{ title: __('Overview'), href: analytics() }],
});
