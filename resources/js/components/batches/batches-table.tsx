import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useDateFormat } from '@/hooks/use-date-format';
import type { AdminCardBatch, CardBatchSummary } from '@/types';
import type { RouteDefinition } from '@/wayfinder';
import { __ } from '@/i18n';

/**
 * Card batches, newest first: when, how many cards, how many were activated,
 * who made it. The admin area passes batches with notes and shows them.
 */
export function BatchesTable({
    batches,
    href,
    showNotes = false,
}: {
    batches: (CardBatchSummary | AdminCardBatch)[];
    href: (batch: CardBatchSummary) => RouteDefinition<'get'>;
    showNotes?: boolean;
}) {
    const { formatDateTime } = useDateFormat();

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>{__('Created', { context: 'batch' })}</TableHead>
                    <TableHead className="text-right">{__('Cards')}</TableHead>
                    <TableHead className="text-right">
                        {__('Activated', { context: 'card count' })}
                    </TableHead>
                    <TableHead>{__('Created by')}</TableHead>
                    {showNotes && <TableHead>{__('Notes')}</TableHead>}
                </TableRow>
            </TableHeader>
            <TableBody>
                {batches.map((batch) => (
                    <TableRow key={batch.id}>
                        <TableCell>
                            <div className="flex flex-wrap items-center gap-2">
                                <Link
                                    href={href(batch)}
                                    className="font-medium hover:underline"
                                >
                                    {formatDateTime(batch.created_at)}
                                </Link>
                                {batch.voided_at && (
                                    <Badge variant="destructive">
                                        {__('Voided', {
                                            context: 'batch status',
                                        })}
                                    </Badge>
                                )}
                            </div>
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {batch.count}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {batch.activated}
                        </TableCell>
                        <TableCell>
                            <div className="flex flex-wrap items-center gap-2">
                                <span>{batch.created_by}</span>
                                {batch.issued_by_admin && (
                                    <Badge variant="outline">Yoiful</Badge>
                                )}
                            </div>
                        </TableCell>
                        {showNotes && (
                            <TableCell className="max-w-64 truncate text-muted-foreground">
                                {'notes' in batch ? batch.notes : null}
                            </TableCell>
                        )}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
