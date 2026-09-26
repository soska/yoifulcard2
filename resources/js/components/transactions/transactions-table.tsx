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
import { formatDateTime, formatSignedMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show } from '@/routes/cards';
import type { TransactionRow } from '@/types';

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
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Date</TableHead>
                    {showCard && <TableHead>Card</TableHead>}
                    <TableHead>Type</TableHead>
                    <TableHead className="text-right">Amount</TableHead>
                    <TableHead className="text-right">Balance after</TableHead>
                    <TableHead>Note</TableHead>
                    <TableHead>By</TableHead>
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
                                {formatSignedMoney(amount, currency)}
                            </TableCell>
                            <TableCell className="text-right whitespace-nowrap tabular-nums">
                                {formatSignedMoney(
                                    transaction.balance_after,
                                    currency,
                                    false,
                                )}
                            </TableCell>
                            <TableCell className="max-w-64 truncate text-muted-foreground">
                                {transaction.note ?? '—'}
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
