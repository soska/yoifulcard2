import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Minus, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { CardStatusBadge } from '@/components/cards/card-status-badge';
import { SUSPENDED_MESSAGE } from '@/components/organization/suspended-banner';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { recordRecentScan } from '@/hooks/use-reader-storage';
import { useMoneyFormat } from '@/hooks/use-money-format';
import { playReaderSound } from '@/lib/reader-sound';
import { scan } from '@/routes';
import { load, spend } from '@/routes/cards';
import type { CardStatus } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    card: {
        id: string;
        code: string;
        balance: string;
        status: CardStatus;
    };
    currency: string;
};

type Action = 'spend' | 'load';

const PRESETS = ['10', '20', '50', '100'];

const labels: Record<Action, { tab: string; submit: string }> = {
    spend: { tab: 'Charge', submit: 'Charge' },
    load: { tab: 'Add funds', submit: 'Add' },
};

/**
 * A scanned card: charge it or add funds. Posts to the same ledger routes as
 * the card page, with `reader` set so a success returns to the scanner.
 */
export default function ReaderCard({ card, currency }: Props) {
    const { currentOrganization } = usePage().props;
    const { t } = useTranslation();
    const { formatMoney } = useMoneyFormat();
    const [action, setAction] = useState<Action>('spend');
    const form = useForm({ amount: '', reader: true });

    useEffect(() => {
        recordRecentScan(card.code);
    }, [card.code]);

    const writable = currentOrganization?.status === 'active';
    const blockedReason = !writable
        ? t(SUSPENDED_MESSAGE)
        : card.status === 'frozen'
          ? t('This card is frozen. It cannot be charged or loaded.')
          : card.status === 'cancelled'
            ? t('This card is cancelled.')
            : null;
    const disabled = blockedReason !== null;
    // CardLedger refusals come back on `card` or `organization`.
    const errors = form.errors as Partial<
        Record<'amount' | 'card' | 'organization', string>
    >;
    const formError = errors.card ?? errors.organization;
    const amount = form.data.amount.trim();

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const route = action === 'spend' ? spend : load;

        form.post(route.url(card), {
            preserveScroll: true,
            onSuccess: () => playReaderSound('success'),
            onError: () => playReaderSound('error'),
        });
    }

    return (
        <>
            <Head title={`${t('Reader')} · ${card.code}`} />
            <div className="flex flex-1 flex-col gap-4">
                <Button
                    variant="ghost"
                    size="lg"
                    className="h-12 self-start"
                    nativeButton={false}
                    render={<Link href={scan()} />}
                >
                    <ArrowLeft data-icon="inline-start" />
                    {t('Back to scanner')}
                </Button>

                <Card>
                    <CardContent className="flex flex-col gap-1">
                        <div className="flex items-center justify-between gap-2">
                            <span className="font-mono text-lg font-semibold">
                                {card.code}
                            </span>
                            <CardStatusBadge status={card.status} />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {t('Balance')}
                        </p>
                        <p className="text-4xl font-semibold tabular-nums">
                            {formatMoney(card.balance, currency)}
                        </p>
                    </CardContent>
                </Card>

                {blockedReason && (
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertDescription>{blockedReason}</AlertDescription>
                    </Alert>
                )}

                <Tabs
                    value={action}
                    onValueChange={(value: Action) => {
                        setAction(value);
                        form.clearErrors();
                    }}
                >
                    <TabsList className="h-12 w-full">
                        <TabsTrigger value="spend" className="text-base">
                            <Minus data-icon="inline-start" />
                            {t(labels.spend.tab)}
                        </TabsTrigger>
                        <TabsTrigger value="load" className="text-base">
                            <Plus data-icon="inline-start" />
                            {t(labels.load.tab)}
                        </TabsTrigger>
                    </TabsList>
                </Tabs>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    {formError && (
                        <Alert variant="destructive">
                            <AlertCircle />
                            <AlertDescription>{formError}</AlertDescription>
                        </Alert>
                    )}

                    <div className="grid grid-cols-4 gap-2">
                        {PRESETS.map((preset) => (
                            <Button
                                key={preset}
                                type="button"
                                variant={
                                    amount === preset ? 'default' : 'outline'
                                }
                                className="h-14 text-lg"
                                disabled={disabled || form.processing}
                                onClick={() => form.setData('amount', preset)}
                            >
                                {formatMoney(preset, currency)}
                            </Button>
                        ))}
                    </div>

                    <Field data-invalid={!!form.errors.amount}>
                        <FieldLabel htmlFor="reader-amount">
                            {t('Amount (:currency)', { currency })}
                        </FieldLabel>
                        <Input
                            id="reader-amount"
                            name="amount"
                            inputMode="decimal"
                            autoComplete="off"
                            placeholder="0.00"
                            className="h-14 text-2xl md:text-2xl"
                            value={form.data.amount}
                            onChange={(event) =>
                                form.setData('amount', event.target.value)
                            }
                            disabled={disabled || form.processing}
                            aria-invalid={!!form.errors.amount}
                            required
                        />
                        <FieldError>{form.errors.amount}</FieldError>
                    </Field>

                    <Button
                        type="submit"
                        size="lg"
                        className="h-16 text-lg"
                        disabled={disabled || form.processing || amount === ''}
                        title={blockedReason ?? undefined}
                    >
                        {form.processing && <Spinner />}
                        {t(labels[action].submit)}
                        {amount !== '' &&
                            !Number.isNaN(Number(amount)) &&
                            ` ${formatMoney(amount, currency)}`}
                    </Button>
                </form>
            </div>
        </>
    );
}
