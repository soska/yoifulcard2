import { Form, Head, Link, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, History, Snowflake, Sun } from 'lucide-react';
import { suspendedMessage } from '@/components/organization/suspended-banner';
import { CardActivation } from '@/components/cards/card-activation';
import { CardBalanceActions } from '@/components/cards/card-balance-actions';
import { CardHandoff } from '@/components/cards/card-handoff';
import { CardStatusBadge } from '@/components/cards/card-status-badge';
import { TransactionsTable } from '@/components/transactions/transactions-table';
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
    FieldLabel,
} from '@/components/ui/field';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useDateFormat } from '@/hooks/use-date-format';
import { useMoneyFormat } from '@/hooks/use-money-format';
import { email, freeze, index, show, unfreeze } from '@/routes/cards';
import { index as transactionsIndex } from '@/routes/transactions';
import type { CardDetail, TransactionRow } from '@/types';
import { __ } from '@/i18n';

type Props = {
    card: CardDetail;
    currency: string;
    transactions: TransactionRow[];
    transactionCount: number;
};

export default function ShowCard({
    card,
    currency,
    transactions,
    transactionCount,
}: Props) {
    const { currentOrganization, errors } = usePage().props;
    const { formatDateTime } = useDateFormat();
    const { formatMoney } = useMoneyFormat();
    const writable = currentOrganization?.status === 'active';
    const pageError =
        (errors as Record<string, string | undefined>).organization ??
        (errors as Record<string, string | undefined>).status;
    const inactive = card.status === 'inactive';

    return (
        <>
            <Head title={card.code} />
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

                {pageError && (
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertTitle>{__('That did not work')}</AlertTitle>
                        <AlertDescription>{pageError}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="flex flex-col gap-4 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between gap-3">
                                    <CardTitle className="font-mono text-2xl">
                                        {card.code}
                                    </CardTitle>
                                    <CardStatusBadge status={card.status} />
                                </div>
                                <CardDescription>
                                    {card.program}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid grid-cols-2 gap-4">
                                    <div className="col-span-2">
                                        <dt className="text-sm text-muted-foreground">
                                            {__('Balance')}
                                        </dt>
                                        <dd className="text-3xl font-semibold tabular-nums">
                                            {inactive
                                                ? __('Not activated yet')
                                                : formatMoney(
                                                      card.balance,
                                                      currency,
                                                  )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm text-muted-foreground">
                                            {__('Created')}
                                        </dt>
                                        <dd>
                                            {formatDateTime(card.created_at)}
                                        </dd>
                                    </div>
                                    {card.activated_at && (
                                        <div>
                                            <dt className="text-sm text-muted-foreground">
                                                {__('Activated')}
                                            </dt>
                                            <dd>
                                                {formatDateTime(
                                                    card.activated_at,
                                                )}
                                            </dd>
                                        </div>
                                    )}
                                    <div>
                                        <dt className="text-sm text-muted-foreground">
                                            {__('Last used')}
                                        </dt>
                                        <dd>
                                            {formatDateTime(
                                                card.last_used_at,
                                                __('Never'),
                                            )}
                                        </dd>
                                    </div>
                                </dl>
                            </CardContent>
                        </Card>

                        {inactive ? (
                            <CardActivation
                                card={card}
                                currency={currency}
                                writable={writable}
                            />
                        ) : (
                            <CardBalanceActions
                                card={card}
                                currency={currency}
                                writable={writable}
                            />
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>{__('Status')}</CardTitle>
                                <CardDescription>
                                    {__(
                                        'A frozen card cannot be charged or loaded.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {card.status === 'frozen' ? (
                                    <Form
                                        {...unfreeze.form(card)}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={
                                                    processing || !writable
                                                }
                                                title={
                                                    writable
                                                        ? undefined
                                                        : suspendedMessage()
                                                }
                                            >
                                                {processing ? (
                                                    <Spinner />
                                                ) : (
                                                    <Sun data-icon="inline-start" />
                                                )}
                                                {__('Unfreeze card')}
                                            </Button>
                                        )}
                                    </Form>
                                ) : (
                                    <Form
                                        {...freeze.form(card)}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={
                                                    processing ||
                                                    !writable ||
                                                    card.status ===
                                                        'cancelled' ||
                                                    inactive
                                                }
                                                title={
                                                    writable
                                                        ? undefined
                                                        : suspendedMessage()
                                                }
                                            >
                                                {processing ? (
                                                    <Spinner />
                                                ) : (
                                                    <Snowflake data-icon="inline-start" />
                                                )}
                                                {__('Freeze card')}
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{__('Cardholder email')}</CardTitle>
                                <CardDescription>
                                    {__(
                                        'Optional. Saved for balance updates later.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...email.form(card)}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing, errors: formErrors }) => (
                                        <Field
                                            data-invalid={!!formErrors.email}
                                        >
                                            <FieldLabel
                                                htmlFor="email"
                                                className="sr-only"
                                            >
                                                {__('Cardholder email')}
                                            </FieldLabel>
                                            <div className="flex gap-2">
                                                <Input
                                                    key={card.email ?? ''}
                                                    id="email"
                                                    name="email"
                                                    type="email"
                                                    defaultValue={
                                                        card.email ?? ''
                                                    }
                                                    placeholder={__(
                                                        'customer@example.com',
                                                    )}
                                                    disabled={!writable}
                                                    aria-invalid={
                                                        !!formErrors.email
                                                    }
                                                />
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        processing || !writable
                                                    }
                                                    title={
                                                        writable
                                                            ? undefined
                                                            : suspendedMessage()
                                                    }
                                                >
                                                    {processing && <Spinner />}
                                                    {__('Save')}
                                                </Button>
                                            </div>
                                            <FieldDescription>
                                                {__(
                                                    'Leave empty to remove it.',
                                                )}
                                            </FieldDescription>
                                            <FieldError>
                                                {formErrors.email}
                                            </FieldError>
                                        </Field>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    </div>

                    <CardHandoff card={card} writable={writable} />
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-col gap-1">
                                <CardTitle>{__('History')}</CardTitle>
                                <CardDescription>
                                    {transactionCount > transactions.length
                                        ? __(
                                              'The latest {count} of {total} transactions.',
                                              {
                                                  count: transactions.length,
                                                  total: transactionCount,
                                              },
                                          )
                                        : __(
                                              'Every balance change on this card.',
                                          )}
                                </CardDescription>
                            </div>
                            {transactionCount > transactions.length && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    render={
                                        <Link
                                            href={transactionsIndex({
                                                query: { card: card.code },
                                            })}
                                        />
                                    }
                                >
                                    <History data-icon="inline-start" />
                                    {__('See all')}
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {transactions.length === 0 ? (
                            <Empty className="border">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <History />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {__('No transactions yet')}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {__(
                                            'Loads, charges, and adjustments show up here.',
                                        )}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <TransactionsTable
                                transactions={transactions}
                                currency={currency}
                                showCard={false}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ShowCard.layout = (props: Props) => ({
    breadcrumbs: [
        { title: __('Cards'), href: index() },
        { title: props.card.code, href: show(props.card) },
    ],
});
