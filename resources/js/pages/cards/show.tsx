import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Download,
    History,
    Snowflake,
    Sun,
} from 'lucide-react';
import { CardBalanceActions } from '@/components/cards/card-balance-actions';
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
import { formatMoney } from '@/lib/format';
import { email, freeze, index, show, unfreeze } from '@/routes/cards';
import { png, svg } from '@/routes/cards/qr';
import { index as transactionsIndex } from '@/routes/transactions';
import type { CardDetail, TransactionRow } from '@/types';

type Props = {
    card: CardDetail;
    currency: string;
    transactions: TransactionRow[];
    transactionCount: number;
};

const SUSPENDED = 'This business is suspended. Contact support.';

export default function ShowCard({
    card,
    currency,
    transactions,
    transactionCount,
}: Props) {
    const { currentOrganization, errors } = usePage().props;
    const { formatDateTime } = useDateFormat();
    const writable = currentOrganization?.status === 'active';
    const pageError =
        (errors as Record<string, string | undefined>).organization ??
        (errors as Record<string, string | undefined>).status;

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
                        Back to cards
                    </Button>
                </div>

                {pageError && (
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertTitle>That did not work</AlertTitle>
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
                                            Balance
                                        </dt>
                                        <dd className="text-3xl font-semibold tabular-nums">
                                            {formatMoney(
                                                card.balance,
                                                currency,
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm text-muted-foreground">
                                            Created
                                        </dt>
                                        <dd>
                                            {formatDateTime(card.created_at)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm text-muted-foreground">
                                            Last used
                                        </dt>
                                        <dd>
                                            {formatDateTime(
                                                card.last_used_at,
                                                'Never',
                                            )}
                                        </dd>
                                    </div>
                                </dl>
                            </CardContent>
                        </Card>

                        <CardBalanceActions
                            card={card}
                            currency={currency}
                            writable={writable}
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle>Status</CardTitle>
                                <CardDescription>
                                    A frozen card cannot be charged or loaded.
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
                                                        : SUSPENDED
                                                }
                                            >
                                                {processing ? (
                                                    <Spinner />
                                                ) : (
                                                    <Sun data-icon="inline-start" />
                                                )}
                                                Unfreeze card
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
                                                    card.status === 'cancelled'
                                                }
                                                title={
                                                    writable
                                                        ? undefined
                                                        : SUSPENDED
                                                }
                                            >
                                                {processing ? (
                                                    <Spinner />
                                                ) : (
                                                    <Snowflake data-icon="inline-start" />
                                                )}
                                                Freeze card
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Cardholder email</CardTitle>
                                <CardDescription>
                                    Optional. Saved for balance updates later.
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
                                                Cardholder email
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
                                                    placeholder="customer@example.com"
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
                                                            : SUSPENDED
                                                    }
                                                >
                                                    {processing && <Spinner />}
                                                    Save
                                                </Button>
                                            </div>
                                            <FieldDescription>
                                                Leave empty to remove it.
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

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle>QR code</CardTitle>
                            <CardDescription>
                                Scan it to open the card.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col items-center gap-4">
                            <div className="rounded-lg border bg-white p-2">
                                <img
                                    src={svg.url(card)}
                                    alt={`QR code for card ${card.code}`}
                                    width={240}
                                    height={240}
                                    className="size-60"
                                />
                            </div>
                            <Button
                                variant="outline"
                                className="w-full"
                                nativeButton={false}
                                render={
                                    <a
                                        href={png.url(card)}
                                        download={`${card.code}.png`}
                                    />
                                }
                            >
                                <Download data-icon="inline-start" />
                                Download PNG
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-col gap-1">
                                <CardTitle>History</CardTitle>
                                <CardDescription>
                                    {transactionCount > transactions.length
                                        ? `The latest ${transactions.length} of ${transactionCount} transactions.`
                                        : 'Every balance change on this card.'}
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
                                    See all
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
                                    <EmptyTitle>No transactions yet</EmptyTitle>
                                    <EmptyDescription>
                                        Loads, charges, and adjustments show up
                                        here.
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
        { title: 'Cards', href: index() },
        { title: props.card.code, href: show(props.card) },
    ],
});
