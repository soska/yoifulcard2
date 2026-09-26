import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    XAxis,
    YAxis,
} from 'recharts';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { useDateFormat } from '@/hooks/use-date-format';
import { useMoneyFormat } from '@/hooks/use-money-format';
import type { AnalyticsDay } from '@/types';
import { __ } from '@/i18n';

/*
 * Series colors follow the entity, in a fixed order, the same in every chart:
 * loads and new cards are slot 1 (blue), spends slot 2 (orange), adjustments
 * slot 3 (aqua). Light and dark steps are chosen per theme.
 */
const SLOT_1 = { light: '#2a78d6', dark: '#3987e5' };
const SLOT_2 = { light: '#eb6834', dark: '#d95926' };
const SLOT_3 = { light: '#1baf7a', dark: '#199e70' };

// Series labels are translated when the chart renders.
const cardsConfig = () =>
    ({
        cards: { label: __('Cards created'), theme: SLOT_1 },
    }) satisfies ChartConfig;

const transactionsConfig = () =>
    ({
        load: { label: __('Loads'), theme: SLOT_1 },
        spend: { label: __('Charges'), theme: SLOT_2 },
        adjustment: { label: __('Adjustments'), theme: SLOT_3 },
    }) satisfies ChartConfig;

const moneyConfig = () =>
    ({
        loaded: { label: __('Loaded'), theme: SLOT_1 },
        spent: { label: __('Charged'), theme: SLOT_2 },
    }) satisfies ChartConfig;

/** Axis and tooltip day labels in the interface language. */
function useDayLabels() {
    const { formatDay } = useDateFormat();

    const dayLabel = (value: unknown): string =>
        typeof value === 'string' ? formatDay(value, 'short') : '';

    const tooltipDay = (
        _: unknown,
        payload: readonly { payload?: unknown }[],
    ) => {
        const row = payload[0]?.payload as AnalyticsDay | undefined;

        return row ? formatDay(row.date) : '';
    };

    return { dayLabel, tooltipDay, formatDay };
}

const axisProps = {
    tickLine: false,
    axisLine: false,
    tickMargin: 8,
} as const;

function ChartCard({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: React.ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

/** New cards per local day. */
export function CardsCreatedChart({ series }: { series: AnalyticsDay[] }) {
    const { dayLabel, tooltipDay } = useDayLabels();

    return (
        <ChartCard
            title={__('Cards created')}
            description={__('New cards per day in your timezone.')}
        >
            <ChartContainer
                config={cardsConfig()}
                className="aspect-auto h-64 w-full"
            >
                <AreaChart data={series} accessibilityLayer>
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="date"
                        tickFormatter={dayLabel}
                        minTickGap={24}
                        {...axisProps}
                    />
                    <YAxis allowDecimals={false} width={32} {...axisProps} />
                    <ChartTooltip
                        content={
                            <ChartTooltipContent
                                labelFormatter={tooltipDay}
                                indicator="line"
                            />
                        }
                    />
                    <Area
                        dataKey="cards"
                        type="monotone"
                        stroke="var(--color-cards)"
                        strokeWidth={2}
                        fill="var(--color-cards)"
                        fillOpacity={0.15}
                    />
                </AreaChart>
            </ChartContainer>
        </ChartCard>
    );
}

/** Transactions per local day, stacked by type. */
export function TransactionVolumeChart({ series }: { series: AnalyticsDay[] }) {
    const { dayLabel, tooltipDay } = useDayLabels();

    return (
        <ChartCard
            title={__('Transaction volume')}
            description={__('Transactions per day, by type.')}
        >
            <ChartContainer
                config={transactionsConfig()}
                className="aspect-auto h-64 w-full"
            >
                <BarChart data={series} accessibilityLayer>
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="date"
                        tickFormatter={dayLabel}
                        minTickGap={24}
                        {...axisProps}
                    />
                    <YAxis allowDecimals={false} width={32} {...axisProps} />
                    <ChartTooltip
                        content={
                            <ChartTooltipContent labelFormatter={tooltipDay} />
                        }
                    />
                    <ChartLegend content={<ChartLegendContent />} />
                    <Bar
                        dataKey="load"
                        stackId="type"
                        fill="var(--color-load)"
                        stroke="var(--background)"
                        strokeWidth={1}
                    />
                    <Bar
                        dataKey="spend"
                        stackId="type"
                        fill="var(--color-spend)"
                        stroke="var(--background)"
                        strokeWidth={1}
                    />
                    <Bar
                        dataKey="adjustment"
                        stackId="type"
                        fill="var(--color-adjustment)"
                        stroke="var(--background)"
                        strokeWidth={1}
                        radius={[4, 4, 0, 0]}
                    />
                </BarChart>
            </ChartContainer>
        </ChartCard>
    );
}

/** Money loaded and charged per local day. */
export function MoneyFlowChart({
    series,
    currency,
}: {
    series: AnalyticsDay[];
    currency: string;
}) {
    const { formatMoney } = useMoneyFormat();
    const { dayLabel, formatDay } = useDayLabels();
    const config = moneyConfig();

    // Numbers for drawing only; labels use the decimal strings' values.
    const data = series.map((day) => ({
        date: day.date,
        loaded: Number(day.loadAmount),
        spent: Number(day.spendAmount),
    }));

    return (
        <ChartCard
            title={__('Money in and out')}
            description={__(
                'Amounts loaded and charged per day ({currency}).',
                {
                    currency,
                },
            )}
        >
            <ChartContainer config={config} className="aspect-auto h-64 w-full">
                <BarChart data={data} accessibilityLayer barGap={2}>
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="date"
                        tickFormatter={dayLabel}
                        minTickGap={24}
                        {...axisProps}
                    />
                    <YAxis width={56} {...axisProps} />
                    <ChartTooltip
                        content={
                            <ChartTooltipContent
                                labelFormatter={(_, payload) => {
                                    const date = (
                                        payload[0]?.payload as
                                            | { date?: string }
                                            | undefined
                                    )?.date;

                                    return date ? formatDay(date) : '';
                                }}
                                formatter={(value, name) => (
                                    <div className="flex w-full justify-between gap-4">
                                        <span className="text-muted-foreground">
                                            {config[name as keyof typeof config]
                                                ?.label ?? name}
                                        </span>
                                        <span className="font-mono font-medium tabular-nums">
                                            {formatMoney(
                                                String(value),
                                                currency,
                                            )}
                                        </span>
                                    </div>
                                )}
                            />
                        }
                    />
                    <ChartLegend content={<ChartLegendContent />} />
                    <Bar
                        dataKey="loaded"
                        fill="var(--color-loaded)"
                        radius={[4, 4, 0, 0]}
                    />
                    <Bar
                        dataKey="spent"
                        fill="var(--color-spent)"
                        radius={[4, 4, 0, 0]}
                    />
                </BarChart>
            </ChartContainer>
        </ChartCard>
    );
}
