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

/**
 * Signed amount from the card's point of view: charges and redemptions are
 * negative.
 */
function signedAmount(transaction: TransactionRow): string {
    return transaction.type === 'spend' || transaction.type === 'redeem'
        ? `-${transaction.amount}`
        : transaction.amount;
}

/** A points entry's whole number, with its sign when asked ("+5", "-3"). */
function formatPoints(value: string, signed: boolean): string {
    const points = Math.trunc(Number(value));

    return signed && points > 0 ? `+${points}` : String(points);
}

export function TransactionsTable({
    transactions,
    currency,
    showCard = true,
}: Props) {
    const { formatDateTime } = useDateFormat();
    const { formatSignedMoney } = useMoneyFormat();
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>{__('Date')}</TableHead>
                    {showCard && <TableHead>{__('Card')}</TableHead>}
                    <TableHead>{__('Type')}</TableHead>
                    <TableHead className="text-right">{__('Amount')}</TableHead>
                    <TableHead className="text-right">
                        {__('Balance after')}
                    </TableHead>
                    <TableHead>{__('Note')}</TableHead>
                    <TableHead>{__('By')}</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {transactions.map((transaction) => {
                    const amount = signedAmount(transaction);

                    return (
                        <TableRow key={transaction.id}>
                            <TableCell className="whitespace-nowrap text-muted-foreground">
                                {formatDateTime(transaction.created_at)}
                            </TableCell>
                            {showCard && (
                                <TableCell>
                                    <Link
                                        href={show(transaction.card.id)}
                                        className="font-mono font-medium hover:underline"
                                    >
                                        {transaction.card.code}
                                    </Link>
                                </TableCell>
                            )}
                            <TableCell>
                                <TransactionTypeBadge type={transaction.type} />
                            </TableCell>
                            <TableCell
                                className={cn(
                                    'text-right whitespace-nowrap tabular-nums',
                                    amount.startsWith('-')
                                        ? 'text-destructive'
                                        : 'text-foreground',
                                )}
                            >
                                {transaction.unit === 'points'
                                    ? __('{points} pts', {
                                          points: formatPoints(amount, true),
                                      })
                                    : formatSignedMoney(amount, currency)}
                            </TableCell>
                            <TableCell className="text-right whitespace-nowrap tabular-nums">
                                {transaction.unit === 'points'
                                    ? __('{points} pts', {
                                          points: formatPoints(
                                              transaction.balance_after,
                                              false,
                                          ),
                                      })
                                    : formatSignedMoney(
                                          transaction.balance_after,
                                          currency,
                                          false,
                                      )}
                            </TableCell>
                            <TableCell className="max-w-64 truncate text-muted-foreground">
                                {[transaction.reward_name, transaction.note]
                                    .filter(Boolean)
                                    .join(' · ') || '—'}
                            </TableCell>
                            <TableCell className="whitespace-nowrap text-muted-foreground">
                                {transaction.performed_by ?? '—'}
                            </TableCell>
                        </TableRow>
                    );
                })}
            </TableBody>
        </Table>
    );
}
