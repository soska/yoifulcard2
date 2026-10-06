import { Form } from '@inertiajs/react';
import { AlertCircle, Power } from 'lucide-react';
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
import { activate } from '@/routes/cards';
import type { CardDetail } from '@/types';
import { __ } from '@/i18n';

type Props = {
    card: CardDetail;
    currency: string;
    writable: boolean;
};

/**
 * Activate a preissued card with its first amount. Shown on the card page in
 * place of the balance actions while the card is inactive. The reader has the
 * same action. Refusals from CardLedger (the plan's card limit among them)
 * come back as validation errors.
 */
export function CardActivation({ card, currency, writable }: Props) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{__('Activate with amount')}</CardTitle>
                <CardDescription>
                    {__(
                        'This card is not activated yet. Activating it loads the amount and the card can be used right after.',
                    )}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {!writable && (
                    <Alert>
                        <AlertCircle />
                        <AlertDescription>
                            {suspendedMessage()}
                        </AlertDescription>
                    </Alert>
                )}
                <Form
                    {...activate.form(card)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    disableWhileProcessing
                >
                    {({ processing, errors }) => {
                        const formError =
                            errors.card ??
                            errors.organization ??
                            errors.card_limit;

                        return (
                            <FieldGroup>
                                {formError && (
                                    <Alert variant="destructive">
                                        <AlertCircle />
                                        <AlertDescription>
                                            {formError}
                                        </AlertDescription>
                                    </Alert>
                                )}
                                <Field data-invalid={!!errors.amount}>
                                    <FieldLabel htmlFor="activate-amount">
                                        {__('Amount ({currency})', {
                                            currency,
                                        })}
                                    </FieldLabel>
                                    <Input
                                        id="activate-amount"
                                        name="amount"
                                        inputMode="decimal"
                                        autoComplete="off"
                                        placeholder="0.00"
                                        required
                                        disabled={!writable}
                                        aria-invalid={!!errors.amount}
                                    />
                                    <FieldDescription>
                                        {__(
                                            'Greater than 0, up to two decimals.',
                                        )}
                                    </FieldDescription>
                                    <FieldError>{errors.amount}</FieldError>
                                </Field>
                                <div>
                                    <Button
                                        type="submit"
                                        disabled={processing || !writable}
                                        title={
                                            writable
                                                ? undefined
                                                : suspendedMessage()
                                        }
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <Power data-icon="inline-start" />
                                        )}
                                        {__('Activate card')}
                                    </Button>
                                </div>
                            </FieldGroup>
                        );
                    }}
                </Form>
            </CardContent>
        </Card>
    );
}
