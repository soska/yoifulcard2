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
import { formatDay, formatMoney } from '@/lib/format';
import { analytics } from '@/routes';
import { exportMethod } from '@/routes/transactions';
import type { AnalyticsDay, AnalyticsSummary } from '@/types';

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
    const rangeItems = ranges.map((range) => ({
        value: String(range),
        label: `Last ${range} days`,
    }));

    return (
        <>
            <Head title="Analytics" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Analytics"
                        description={`${formatDay(from)} to ${formatDay(to)}, in your business's timezone.`}
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
                                aria-label="Date range"
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
                            Export CSV
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard
                        title="Cards created"
                        value={summary.cards}
                        icon={<CreditCard />}
                    />
                    <SummaryCard
                        title="Transactions"
                        value={summary.transactions}
                        icon={<ReceiptText />}
                    />
                    <SummaryCard
                        title="Loaded"
                        value={formatMoney(summary.loadAmount, currency)}
                        icon={<ArrowDownToLine />}
                    />
                    <SummaryCard
                        title="Charged"
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
                        <CardTitle>By day</CardTitle>
                        <CardDescription>
                            The numbers behind the charts, newest first.
                        </CardDescription>
                    </CardHeader>
                    <div className="px-(--card-spacing)">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Day</TableHead>
                                    <TableHead className="text-right">
                                        Cards
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Loads
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Charges
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Adjustments
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Loaded
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Charged
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
