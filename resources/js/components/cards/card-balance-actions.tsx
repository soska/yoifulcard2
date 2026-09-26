import { Form } from '@inertiajs/react';
import { AlertCircle, Minus, Plus, SlidersHorizontal } from 'lucide-react';
import { suspendedMessage } from '@/components/organization/suspended-banner';
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
import { useMoneyFormat } from '@/hooks/use-money-format';
import { adjust, load, spend } from '@/routes/cards';
import type { CardDetail } from '@/types';
import { __ } from '@/i18n';

type Props = {
    card: CardDetail;
    currency: string;
    writable: boolean;
};

type Action = 'load' | 'spend' | 'adjust';

const actions: Record<
    Action,
    { route: typeof load; noteRequired: boolean; signed: boolean }
> = {
    load: { route: load, noteRequired: false, signed: false },
    spend: { route: spend, noteRequired: false, signed: false },
    adjust: { route: adjust, noteRequired: true, signed: true },
};

/**
 * Each form's words, built at render time (never at module scope). The
 * currency and the available balance are part of whole sentences, so Spanish
 * can put them where it needs to.
 */
function actionCopy(
    action: Action,
    { balance, currency }: { balance: string; currency: string },
): { submit: string; amountLabel: string; amountHelp: string } {
    switch (action) {
        case 'load':
            return {
                submit: __('Add funds'),
                amountLabel: __('Amount to add ({currency})', { currency }),
                amountHelp: __('Greater than 0, up to two decimals.'),
            };
        case 'spend':
            return {
                submit: __('Charge card'),
                amountLabel: __('Amount to charge ({currency})', { currency }),
                amountHelp: __('Up to the available {balance}.', { balance }),
            };
        case 'adjust':
            return {
                submit: __('Adjust balance'),
                amountLabel: __('Adjustment ({currency})', { currency }),
                amountHelp: __(
                    'Use a negative amount to lower the balance, for example -5.00.',
                ),
            };
    }
}

/**
 * Add funds, charge, and adjust-with-note. Errors from CardLedger come back
 * as validation errors and show next to the field they belong to.
 */
export function CardBalanceActions({ card, currency, writable }: Props) {
    const { formatMoney } = useMoneyFormat();
    const blockedReason = !writable
        ? suspendedMessage()
        : card.status === 'frozen'
          ? __('This card is frozen. Unfreeze it to add funds or charge it.')
          : card.status === 'cancelled'
            ? __('This card is cancelled.')
            : null;

    const balance = formatMoney(card.balance, currency);

    return (
        <Card>
            <CardHeader>
                <CardTitle>{__('Balance')}</CardTitle>
                <CardDescription>
                    {__('Every change is recorded in the card history.')}
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
                            {__('Add funds')}
                        </TabsTrigger>
                        <TabsTrigger value="spend">
                            <Minus data-icon="inline-start" />
                            {__('Charge', { context: 'verb: charge a card' })}
                        </TabsTrigger>
                        <TabsTrigger value="adjust">
                            <SlidersHorizontal data-icon="inline-start" />
                            {__('Adjust')}
                        </TabsTrigger>
                    </TabsList>
                    {(Object.keys(actions) as Action[]).map((action) => {
                        const config = actions[action];
                        const copy = actionCopy(action, { balance, currency });
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
                                                        {copy.amountLabel}
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
                                                        {copy.amountHelp}
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
                                                            ? __('Reason')
                                                            : __(
                                                                  'Note (optional)',
                                                              )}
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
                                                            {__(
                                                                'Required. Say why the balance is being corrected.',
                                                            )}
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
                                                        {copy.submit}
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
