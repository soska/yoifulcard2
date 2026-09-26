import { Form } from '@inertiajs/react';
import { AlertCircle, Minus, Plus, SlidersHorizontal } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { formatMoney } from '@/lib/format';
import { adjust, load, spend } from '@/routes/cards';
import type { CardDetail } from '@/types';

type Props = {
    card: CardDetail;
    currency: string;
    writable: boolean;
};

type Action = 'load' | 'spend' | 'adjust';

const SUSPENDED = 'This business is suspended. Contact support.';

const actions: Record<
    Action,
    {
        route: typeof load;
        submit: string;
        amountLabel: string;
        amountHelp: (balance: string) => string;
        noteRequired: boolean;
        signed: boolean;
    }
> = {
    load: {
        route: load,
        submit: 'Add funds',
        amountLabel: 'Amount to add',
        amountHelp: () => 'Greater than 0, up to two decimals.',
        noteRequired: false,
        signed: false,
    },
    spend: {
        route: spend,
        submit: 'Charge card',
        amountLabel: 'Amount to charge',
        amountHelp: (balance) => `Up to the available ${balance}.`,
        noteRequired: false,
        signed: false,
    },
    adjust: {
        route: adjust,
        submit: 'Adjust balance',
        amountLabel: 'Adjustment',
        amountHelp: () =>
            'Use a negative amount to lower the balance, for example -5.00.',
        noteRequired: true,
        signed: true,
    },
};

/**
 * Add funds, charge, and adjust-with-note. Errors from CardLedger come back
 * as validation errors and show next to the field they belong to.
 */
export function CardBalanceActions({ card, currency, writable }: Props) {
    const blockedReason = !writable
        ? SUSPENDED
        : card.status === 'frozen'
          ? 'This card is frozen. Unfreeze it to add funds or charge it.'
          : card.status === 'cancelled'
            ? 'This card is cancelled.'
            : null;

    const balance = formatMoney(card.balance, currency);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Balance</CardTitle>
                <CardDescription>
                    Every change is recorded in the card history.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {blockedReason && (
                    <Alert>
                        <AlertCircle />
                        <AlertDescription>{blockedReason}</AlertDescription>
                    </Alert>
                )}
                <Tabs defaultValue="load">
                    <TabsList className="w-full">
                        <TabsTrigger value="load">
                            <Plus data-icon="inline-start" />
                            Add funds
                        </TabsTrigger>
                        <TabsTrigger value="spend">
                            <Minus data-icon="inline-start" />
                            Charge
                        </TabsTrigger>
                        <TabsTrigger value="adjust">
                            <SlidersHorizontal data-icon="inline-start" />
                            Adjust
                        </TabsTrigger>
                    </TabsList>
                    {(Object.keys(actions) as Action[]).map((action) => {
                        const config = actions[action];
                        const disabled = blockedReason !== null;

                        return (
                            <TabsContent key={action} value={action}>
                                <Form
                                    {...config.route.form(card)}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    disableWhileProcessing
                                >
                                    {({ processing, errors }) => {
                                        const formError =
                                            errors.card ?? errors.organization;

                                        return (
                                            <FieldGroup className="pt-2">
                                                {formError && (
                                                    <Alert variant="destructive">
                                                        <AlertCircle />
                                                        <AlertDescription>
                                                            {formError}
                                                        </AlertDescription>
                                                    </Alert>
                                                )}
                                                <Field
                                                    data-invalid={
                                                        !!errors.amount
                                                    }
                                                >
                                                    <FieldLabel
                                                        htmlFor={`${action}-amount`}
                                                    >
                                                        {config.amountLabel} (
                                                        {currency})
                                                    </FieldLabel>
                                                    <Input
                                                        id={`${action}-amount`}
                                                        name="amount"
                                                        inputMode="decimal"
                                                        autoComplete="off"
                                                        placeholder={
                                                            config.signed
                                                                ? '-5.00'
                                                                : '0.00'
                                                        }
                                                        required
                                                        disabled={disabled}
                                                        aria-invalid={
                                                            !!errors.amount
                                                        }
                                                    />
                                                    <FieldDescription>
                                                        {config.amountHelp(
                                                            balance,
                                                        )}
                                                    </FieldDescription>
                                                    <FieldError>
                                                        {errors.amount}
                                                    </FieldError>
                                                </Field>
                                                <Field
                                                    data-invalid={!!errors.note}
                                                >
                                                    <FieldLabel
                                                        htmlFor={`${action}-note`}
                                                    >
                                                        {config.noteRequired
                                                            ? 'Reason'
                                                            : 'Note (optional)'}
                                                    </FieldLabel>
                                                    <Textarea
                                                        id={`${action}-note`}
                                                        name="note"
                                                        rows={2}
                                                        maxLength={500}
                                                        required={
                                                            config.noteRequired
                                                        }
                                                        disabled={disabled}
                                                        aria-invalid={
                                                            !!errors.note
                                                        }
                                                    />
                                                    {config.noteRequired && (
                                                        <FieldDescription>
                                                            Required. Say why
                                                            the balance is being
                                                            corrected.
                                                        </FieldDescription>
                                                    )}
                                                    <FieldError>
                                                        {errors.note}
                                                    </FieldError>
                                                </Field>
                                                <div>
                                                    <Button
                                                        type="submit"
                                                        disabled={
                                                            processing ||
                                                            disabled
                                                        }
                                                        title={
                                                            blockedReason ??
                                                            undefined
                                                        }
                                                    >
                                                        {processing && (
                                                            <Spinner />
                                                        )}
                                                        {config.submit}
                                                    </Button>
                                                </div>
                                            </FieldGroup>
                                        );
                                    }}
                                </Form>
                            </TabsContent>
                        );
                    })}
                </Tabs>
            </CardContent>
        </Card>
    );
}
