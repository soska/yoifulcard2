import { Head } from '@inertiajs/react';
import { PackageOpen } from 'lucide-react';
import { BatchCreateForm } from '@/components/batches/batch-create-form';
import { BatchesTable } from '@/components/batches/batches-table';
import { CardStockNotice } from '@/components/cards/card-stock-notice';
import { ListPagination } from '@/components/cards/list-pagination';
import { suspendedMessage } from '@/components/organization/suspended-banner';
import Heading from '@/components/heading';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { index, show, store } from '@/routes/batches';
import type { CardBatchSummary, CardUsage, Paginated } from '@/types';
import { __ } from '@/i18n';

type Props = {
    batches: Paginated<CardBatchSummary>;
    usage: CardUsage;
    preissueLimit: number | null;
    maxBatchSize: number;
    canCreate: boolean;
};

export default function BatchesIndex({
    batches,
    usage,
    preissueLimit,
    maxBatchSize,
    canCreate,
}: Props) {
    return (
        <>
            <Head title={__('Card batches')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading
                    title={__('Card batches')}
                    description={
                        preissueLimit === null
                            ? __(
                                  {
                                      one: '{count} card in stock',
                                      other: '{count} cards in stock',
                                  },
                                  { count: usage.stock },
                              )
                            : __(
                                  {
                                      one: '{count} card in stock of {limit} allowed',
                                      other: '{count} cards in stock of {limit} allowed',
                                  },
                                  { count: usage.stock, limit: preissueLimit },
                              )
                    }
                />

                <CardStockNotice usage={usage} />

                <Card>
                    <CardHeader>
                        <CardTitle>{__('New batch')}</CardTitle>
                        <CardDescription>
                            {__(
                                'Preissue cards to print and sell. They stay inactive until you activate each one with the amount the customer paid.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <BatchCreateForm
                            form={store.form()}
                            usage={usage}
                            preissueLimit={preissueLimit}
                            maxBatchSize={maxBatchSize}
                            disabled={!canCreate}
                            disabledReason={suspendedMessage()}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex flex-col gap-4">
                        {batches.data.length === 0 ? (
                            <Empty className="border">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <PackageOpen />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {__('No batches yet')}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {__(
                                            'Batches of cards to print show up here.',
                                        )}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <BatchesTable
                                batches={batches.data}
                                href={(batch) => show(batch.id)}
                            />
                        )}
                        <ListPagination paginator={batches} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

BatchesIndex.layout = () => ({
    breadcrumbs: [{ title: __('Card batches'), href: index() }],
});
