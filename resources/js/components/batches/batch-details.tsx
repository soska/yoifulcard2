import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { CardStatusBadge } from '@/components/cards/card-status-badge';
import { ListPagination } from '@/components/cards/list-pagination';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useDateFormat } from '@/hooks/use-date-format';
import type { CardBatchSummary, CardSummary, Paginated } from '@/types';
import type { RouteDefinition } from '@/wayfinder';
import { __ } from '@/i18n';

/**
 * One batch: its counts, who made it, and a page of its cards. The business
 * page links each card to its page; the admin page lists codes only.
 */
export function BatchDetails({
    batch,
    cards,
    cardHref,
    actions,
    children,
}: {
    batch: CardBatchSummary;
    cards: Paginated<CardSummary>;
    cardHref?: (card: CardSummary) => RouteDefinition<'get'>;
    /** Buttons for the header: void, see in the card list. */
    actions?: ReactNode;
    /** Extra details, such as the admin notes. */
    children?: ReactNode;
}) {
    const { formatDateTime } = useDateFormat();
    const cancelled = batch.count - batch.activated - batch.stock;

    const tiles = [
        { label: __('Cards'), value: batch.count },
        {
            label: __('Activated', { context: 'card count' }),
            value: batch.activated,
        },
        { label: __('In stock'), value: batch.stock },
        { label: __('Voided', { context: 'cards' }), value: cancelled },
    ];

    return (
        <>
            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-center gap-2">
                        <CardTitle className="text-xl">
                            {__('Batch of {date}', {
                                date: formatDateTime(batch.created_at),
                            })}
                        </CardTitle>
                        {batch.voided_at && (
                            <Badge variant="destructive">
                                {__('Voided', { context: 'batch status' })}
                            </Badge>
                        )}
                        {batch.issued_by_admin && (
                            <Badge variant="outline">Yoiful</Badge>
                        )}
                    </div>
                    <CardDescription>
                        {batch.voided_at
                            ? __('Created by {name}. Voided {date}.', {
                                  name: batch.created_by,
                                  date: formatDateTime(batch.voided_at),
                              })
                            : __('Created by {name}.', {
                                  name: batch.created_by,
                              })}
                    </CardDescription>
                    {actions && (
                        <CardAction className="flex flex-wrap gap-2">
                            {actions}
                        </CardAction>
                    )}
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        {tiles.map((tile) => (
                            <div key={tile.label}>
                                <dt className="text-sm text-muted-foreground">
                                    {tile.label}
                                </dt>
                                <dd className="text-2xl font-semibold tabular-nums">
                                    {tile.value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    {children}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{__('Cards')}</CardTitle>
                    <CardDescription>
                        {__(
                            'A voided card was never activated; activated cards keep working when a batch is voided.',
                        )}
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{__('Code')}</TableHead>
                                <TableHead>{__('Status')}</TableHead>
                                <TableHead>{__('Activated')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {cards.data.map((card) => (
                                <TableRow key={card.id}>
                                    <TableCell>
                                        {cardHref ? (
                                            <Link
                                                href={cardHref(card)}
                                                className="font-mono font-medium hover:underline"
                                            >
                                                {card.code}
                                            </Link>
                                        ) : (
                                            <span className="font-mono font-medium">
                                                {card.code}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <CardStatusBadge status={card.status} />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(
                                            card.activated_at,
                                            __('Not yet'),
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    {cards.total > 0 && (
                        <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                            <p className="text-sm text-muted-foreground">
                                {__('Showing {from} to {to} of {total} cards', {
                                    from: cards.from ?? 0,
                                    to: cards.to ?? 0,
                                    total: cards.total,
                                })}
                            </p>
                            <div>
                                <ListPagination paginator={cards} />
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </>
    );
}
