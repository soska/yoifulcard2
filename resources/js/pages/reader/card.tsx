import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Minus, Plus, Power } from 'lucide-react';
import { useEffect, useState } from 'react';
import { CardStatusBadge } from '@/components/cards/card-status-badge';
import { suspendedMessage } from '@/components/organization/suspended-banner';
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
import { activate, load, spend } from '@/routes/cards';
import type { CardStatus } from '@/types';
import { __ } from '@/i18n';

type Props = {
    card: {
        id: string;
        code: string;
        balance: string;
        status: CardStatus;
    };
    currency: string;
};

type Action = 'spend' | 'load' | 'activate';

const PRESETS = ['10', '20', '50', '100'];

/** The submit button's word for each action, built at render time. */
function submitLabel(action: Action): string {
    switch (action) {
        case 'spend':
            return __('Charge', { context: 'verb: charge a card' });
        case 'load':
            return __('Add', { context: 'verb: add funds to a card' });
        case 'activate':
            return __('Activate', { context: 'verb: activate a card' });
    }
}

const routes = { spend, load, activate } as const;

/**
 * A scanned card: charge it or add funds, or, for a preissued card that is
 * not activated yet, activate it with an amount. Posts to the same ledger
 * routes as the card page, with `reader` set so a success returns to the
 * scanner.
 */
export default function ReaderCard({ card, currency }: Props) {
    const { currentOrganization } = usePage().props;
    const { formatMoney } = useMoneyFormat();
    const inactive = card.status === 'inactive';
    const [chosen, setChosen] = useState<Action>('spend');
    const action: Action = inactive ? 'activate' : chosen;
    const form = useForm({ amount: '', reader: true });

    useEffect(() => {
        recordRecentScan(card.code);
    }, [card.code]);

    const writable = currentOrganization?.status === 'active';
    const blockedReason = !writable
        ? suspendedMessage()
        : card.status === 'frozen'
          ? __('This card is frozen. It cannot be charged or loaded.')
          : card.status === 'cancelled'
            ? __('This card is cancelled.')
            : null;
    const disabled = blockedReason !== null;
    // CardLedger refusals come back on `card`, `organization`, or (when
    // activating at the plan limit) `card_limit`.
    const errors = form.errors as Partial<
        Record<'amount' | 'card' | 'organization' | 'card_limit', string>
    >;
    const formError = errors.card ?? errors.organization ?? errors.card_limit;
    const amount = form.data.amount.trim();

    function submit(event: React.FormEvent) {
        event.preventDefault();

        form.post(routes[action].url(card), {
            preserveScroll: true,
            onSuccess: () => playReaderSound('success'),
            onError: () => playReaderSound('error'),
        });
    }

    return (
        <>
            <Head title={`${__('Reader')} · ${card.code}`} />
            <div className="flex flex-1 flex-col gap-4">
                <Button
                    variant="ghost"
                    size="lg"
                    className="h-12 self-start"
                    nativeButton={false}
                    render={<Link href={scan()} />}
                >
                    <ArrowLeft data-icon="inline-start" />
                    {__('Back to scanner')}
                </Button>

                <Card>
                    <CardContent className="flex flex-col gap-1">
                        <div className="flex items-center justify-between gap-2">
                            <span className="font-mono text-lg font-semibold">
                                {card.code}
                            </span>
                            <CardStatusBadge status={card.status} />
                        </div>
                        {inactive ? (
                            <p className="text-2xl font-semibold">
                                {__('Not activated yet')}
                            </p>
                        ) : (
                            <>
                                <p className="text-sm text-muted-foreground">
                                    {__('Balance')}
                                </p>
                                <p className="text-4xl font-semibold tabular-nums">
                                    {formatMoney(card.balance, currency)}
                                </p>
                            </>
                        )}
                    </CardContent>
                </Card>

                {blockedReason && (
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertDescription>{blockedReason}</AlertDescription>
                    </Alert>
                )}

                {inactive ? (
                    <div className="flex flex-col gap-1">
                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                            <Power className="size-5" />
                            {__('Activate with amount')}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {__(
                                'Enter the amount the customer paid. The card can be used right after.',
                            )}
                        </p>
                    </div>
                ) : (
                    <Tabs
                        value={action}
                        onValueChange={(value: Action) => {
                            setChosen(value);
                            form.clearErrors();
                        }}
                    >
                        <TabsList className="h-12 w-full">
                            <TabsTrigger value="spend" className="text-base">
                                <Minus data-icon="inline-start" />
                                {__('Charge', {
                                    context: 'verb: charge a card',
                                })}
                            </TabsTrigger>
                            <TabsTrigger value="load" className="text-base">
                                <Plus data-icon="inline-start" />
                                {__('Add funds')}
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                )}

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
                            {__('Amount ({currency})', { currency })}
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
                        {submitLabel(action)}
                        {amount !== '' &&
                            !Number.isNaN(Number(amount)) &&
                            ` ${formatMoney(amount, currency)}`}
                    </Button>
                </form>
            </div>
        </>
    );
}
