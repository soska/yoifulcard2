import { Form, Head, Link, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowLeft } from 'lucide-react';
import { CardUsageNotice } from '@/components/cards/card-usage-notice';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { create, index, store } from '@/routes/cards';
import type { CardUsage } from '@/types';
import { __ } from '@/i18n';

type Props = {
    currency: string;
    usage: CardUsage;
};

export default function CreateCard({ currency, usage }: Props) {
    const { currentOrganization } = usePage().props;
    const writable = currentOrganization?.status === 'active';
    const blocked = !writable || usage.atLimit;

    return (
        <>
            <Head title={__('Create card')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <Button
                        variant="ghost"
                        size="sm"
                        render={<Link href={index()} />}
                    >
                        <ArrowLeft data-icon="inline-start" />
                        {__('Back to cards')}
                    </Button>
                </div>

                <div className="mx-auto flex w-full max-w-lg flex-col gap-4">
                    <CardUsageNotice usage={usage} />

                    <Card>
                        <CardHeader>
                            <CardTitle>{__('Create a card')}</CardTitle>
                            <CardDescription>
                                {__(
                                    'The card gets a code and a QR code right away.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form {...store.form()} disableWhileProcessing>
                                {({ processing, errors }) => {
                                    const formError =
                                        errors.card_limit ??
                                        errors.organization ??
                                        errors.program;

                                    return (
                                        <FieldGroup>
                                            {formError && (
                                                <Alert variant="destructive">
                                                    <AlertCircle />
                                                    <AlertTitle>
                                                        {__(
                                                            'The card was not created',
                                                        )}
                                                    </AlertTitle>
                                                    <AlertDescription>
                                                        {formError}
                                                    </AlertDescription>
                                                </Alert>
                                            )}

                                            <Field
                                                data-invalid={
                                                    !!errors.initial_balance
                                                }
                                            >
                                                <FieldLabel htmlFor="initial_balance">
                                                    {__(
                                                        'Initial balance ({currency})',
                                                        {
                                                            currency,
                                                        },
                                                    )}
                                                </FieldLabel>
                                                <Input
                                                    id="initial_balance"
                                                    name="initial_balance"
                                                    type="number"
                                                    inputMode="decimal"
                                                    min="0"
                                                    step="0.01"
                                                    defaultValue="0"
                                                    required
                                                    aria-invalid={
                                                        !!errors.initial_balance
                                                    }
                                                />
                                                <FieldDescription>
                                                    {__(
                                                        '0 or more. An amount above 0 is recorded as a load.',
                                                    )}
                                                </FieldDescription>
                                                <FieldError>
                                                    {errors.initial_balance}
                                                </FieldError>
                                            </Field>

                                            <Field
                                                data-invalid={!!errors.email}
                                            >
                                                <FieldLabel htmlFor="email">
                                                    {__(
                                                        'Cardholder email (optional)',
                                                    )}
                                                </FieldLabel>
                                                <Input
                                                    id="email"
                                                    name="email"
                                                    type="email"
                                                    autoComplete="off"
                                                    placeholder={__(
                                                        'customer@example.com',
                                                    )}
                                                    aria-invalid={
                                                        !!errors.email
                                                    }
                                                />
                                                <FieldDescription>
                                                    {__(
                                                        'Saved on the card for balance updates later.',
                                                    )}
                                                </FieldDescription>
                                                <FieldError>
                                                    {errors.email}
                                                </FieldError>
                                            </Field>

                                            <Button
                                                type="submit"
                                                disabled={processing || blocked}
                                                title={
                                                    writable
                                                        ? undefined
                                                        : __(
                                                              'This business is suspended. Contact support.',
                                                          )
                                                }
                                            >
                                                {processing && <Spinner />}
                                                {__('Create card')}
                                            </Button>
                                        </FieldGroup>
                                    );
                                }}
                            </Form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

CreateCard.layout = () => ({
    breadcrumbs: [
        { title: __('Cards'), href: index() },
        { title: __('Create card'), href: create() },
    ],
});
