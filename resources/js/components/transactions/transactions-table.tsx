import { Copy } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { TransactionTypeBadge } from '@/components/transactions/transaction-type-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useDateFormat } from '@/hooks/use-date-format';
import { useMoneyFormat } from '@/hooks/use-money-format';
import { cn } from '@/lib/utils';
import { show } from '@/routes/cards';
import type { TransactionRow } from '@/types';
import { __ } from '@/i18n';

type Props = {
    transactions: TransactionRow[];
    currency: string;
    /** Hide the card column on a card's own page. */
    showCard?: boolean;
};

/** Signed amount from the card's point of view: charges are negative. */
function signedAmount(transaction: TransactionRow): string {
    return transaction.type === 'spend'
        ? `-${transaction.amount}`
        : transaction.amount;
}

export function TransactionsTable({
    transactions,
    currency,
    showCard = true,
}: Props) {
    const { formatDateTime } = useDateFormat();
    const { formatSignedMoney } = useMoneyFormat();
    async function copyAmount(amount: string) {
        try {
            await navigator.clipboard.writeText(amount);
            toast.success(__('Copied'));
        } catch {
            toast.error(__('The amount could not be copied.'));
        }
    }

    const amountButton = (transaction: TransactionRow) => (
        <Button
            variant="ghost"
            className={cn(
                'h-auto gap-2 px-2 py-2 font-semibold tabular-nums',
                signedAmount(transaction).startsWith('-')
                    ? 'text-destructive'
                    : 'text-foreground',
            )}
            title={__('Copy amount')}
            aria-label={`${__('Copy amount')}: ${formatSignedMoney(signedAmount(transaction), currency)}`}
            onClick={() => void copyAmount(transaction.amount)}
        >
            {formatSignedMoney(signedAmount(transaction), currency)}
            <Copy className="size-3 text-muted-foreground" />
        </Button>
    );

    return (
        <>
            <ul className="divide-y md:hidden">
                {transactions.map((transaction) => (
                    <li
                        key={transaction.id}
                        className="space-y-2 py-4 first:pt-0"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <TransactionTypeBadge type={transaction.type} />
                                {showCard && (
                                    <Link
                                        href={show(transaction.card.id)}
                                        className="font-mono text-sm font-medium hover:underline"
                                    >
                                        {transaction.card.code}
                                    </Link>
                                )}
                            </div>
                            {amountButton(transaction)}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {formatDateTime(transaction.created_at)}
                        </p>
                        <details className="text-sm">
                            <summary className="cursor-pointer text-muted-foreground">
                                {__('Details')}
                            </summary>
                            <dl className="mt-2 space-y-2">
                                <div>
                                    <dt className="text-muted-foreground">
                                        {__('Balance after')}
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatSignedMoney(
                                            transaction.balance_after,
                                            currency,
                                            false,
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {__('By')}
                                    </dt>
                                    <dd>{transaction.performed_by ?? '—'}</dd>
                                </div>
                                {transaction.note && (
                                    <div>
                                        <dt className="text-muted-foreground">
                                            {__('Note')}
                                        </dt>
                                        <dd className="break-words whitespace-pre-wrap">
                                            {transaction.note}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </details>
                    </li>
                ))}
            </ul>
            <div className="hidden md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{__('Date')}</TableHead>
                            {showCard && <TableHead>{__('Card')}</TableHead>}
                            <TableHead>{__('Type')}</TableHead>
                            <TableHead className="text-right">
                                {__('Amount')}
                            </TableHead>
                            <TableHead className="text-right">
                                {__('Balance after')}
                            </TableHead>
                            {!showCard && <TableHead>{__('Note')}</TableHead>}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {transactions.map((transaction) => {
                            const amount = signedAmount(transaction);

                            return (
                                <TableRow
                                    key={transaction.id}
                                    className="[&>td]:py-3.5"
                                >
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        <div>
                                            {formatDateTime(
                                                transaction.created_at,
                                            )}
                                        </div>
                                        <div className="mt-1 text-xs">
                                            {__('By')}:{' '}
                                            {transaction.performed_by ?? '—'}
                                        </div>
                                    </TableCell>
                                    {showCard && (
                                        <TableCell>
                                            <Link
                                                href={show(transaction.card.id)}
                                                className="inline-flex min-h-8 items-center font-mono font-medium hover:underline"
                                            >
                                                {transaction.card.code}
                                            </Link>
                                            {transaction.note && (
                                                <p className="max-w-64 text-xs break-words whitespace-pre-wrap text-muted-foreground">
                                                    {transaction.note}
                                                </p>
                                            )}
                                        </TableCell>
                                    )}
                                    <TableCell>
                                        <TransactionTypeBadge
                                            type={transaction.type}
                                        />
                                    </TableCell>
                                    <TableCell
                                        className={cn(
                                            'text-right whitespace-nowrap tabular-nums',
                                            amount.startsWith('-')
                                                ? 'text-destructive'
                                                : 'text-foreground',
                                        )}
                                    >
                                        {amountButton(transaction)}
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap tabular-nums">
                                        {formatSignedMoney(
                                            transaction.balance_after,
                                            currency,
                                            false,
                                        )}
                                    </TableCell>
                                    {!showCard && (
                                        <TableCell className="max-w-64 break-words whitespace-pre-wrap text-muted-foreground">
                                            {transaction.note ?? '—'}
                                        </TableCell>
                                    )}
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}
