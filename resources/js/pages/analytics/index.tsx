import { Head, router } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    CreditCard,
    ReceiptText,
} from 'lucide-react';
import type { ReactNode } from 'react';
import {
    CardsCreatedChart,
    MoneyFlowChart,
    TransactionVolumeChart,
} from '@/components/analytics/analytics-charts';
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
import { exportMethod } from '@/routes/transactions';
import type { AnalyticsDay, AnalyticsSummary } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    days: number;
    from: string;
    to: string;
    series: AnalyticsDay[];
    summary: AnalyticsSummary;
    ranges: number[];
    currency: string;
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
}: Props) {
    const { t } = useTranslation();
    const { formatMoney } = useMoneyFormat();
    const { formatDay } = useDateFormat();
    const rangeItems = ranges.map((range) => ({
        value: String(range),
        label: t('Last :days days', { days: range }),
    }));

    return (
        <>
            <Head title={t('Analytics')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title={t('Analytics')}
                        description={t(
                            ":from to :to, in your business's timezone.",
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
                                aria-label={t('Date range')}
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
                            {t('Export CSV')}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard
                        title={t('Cards created')}
                        value={summary.cards}
                        icon={<CreditCard />}
                    />
                    <SummaryCard
                        title={t('Transactions')}
                        value={summary.transactions}
                        icon={<ReceiptText />}
                    />
                    <SummaryCard
                        title={t('Loaded')}
                        value={formatMoney(summary.loadAmount, currency)}
                        icon={<ArrowDownToLine />}
                    />
                    <SummaryCard
                        title={t('Charged')}
                        value={formatMoney(summary.spendAmount, currency)}
                        icon={<ArrowUpFromLine />}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <CardsCreatedChart series={series} />
                    <TransactionVolumeChart series={series} />
                </div>
                <MoneyFlowChart series={series} currency={currency} />

                <Card>
                    <CardHeader>
                        <CardTitle>{t('By day')}</CardTitle>
                        <CardDescription>
                            {t('The numbers behind the charts, newest first.')}
                        </CardDescription>
                    </CardHeader>
                    <div className="px-(--card-spacing)">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Day')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Cards')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Loads')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Charges')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Adjustments')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Loaded')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Charged')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {[...series].reverse().map((day) => (
                                    <TableRow key={day.date}>
                                        <TableCell className="whitespace-nowrap">
                                            {formatDay(day.date)}
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

Analytics.layout = {
    breadcrumbs: [{ title: 'Analytics', href: analytics() }],
};
