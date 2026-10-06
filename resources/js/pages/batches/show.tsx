import { Head, Link, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, List } from 'lucide-react';
import { BatchDetails } from '@/components/batches/batch-details';
import { BatchPrint } from '@/components/batches/batch-print';
import { VoidBatch } from '@/components/batches/void-batch';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { index, show, voidMethod } from '@/routes/batches';
import { download, store as storePdf } from '@/routes/batches/pdfs';
import { index as cardsIndex, show as cardShow } from '@/routes/cards';
import type {
    CardBatchPrint,
    CardBatchSummary,
    CardSummary,
    Paginated,
} from '@/types';
import { __ } from '@/i18n';

type Props = {
    batch: CardBatchSummary;
    cards: Paginated<CardSummary>;
    currency: string;
    /** Owner or manager of a writable business: can void and print. */
    canVoid: boolean;
    print: CardBatchPrint;
};

export default function BatchShow({ batch, cards, canVoid, print }: Props) {
    const { errors } = usePage().props;
    const pageError =
        (errors as Record<string, string | undefined>).batch ??
        (errors as Record<string, string | undefined>).organization;

    return (
        <>
            <Head title={__('Card batch')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <Button
                        variant="ghost"
                        size="sm"
                        render={<Link href={index()} />}
                    >
                        <ArrowLeft data-icon="inline-start" />
                        {__('Back to batches')}
                    </Button>
                </div>

                {pageError && (
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertTitle>{__('That did not work')}</AlertTitle>
                        <AlertDescription>{pageError}</AlertDescription>
                    </Alert>
                )}

                <BatchDetails
                    batch={batch}
                    cards={cards}
                    cardHref={(card) => cardShow(card)}
                    actions={
                        <>
                            <Button
                                variant="outline"
                                size="sm"
                                render={
                                    <Link
                                        href={cardsIndex({
                                            query: { batch: batch.id },
                                        })}
                                    />
                                }
                            >
                                <List data-icon="inline-start" />
                                {__('See in card list')}
                            </Button>
                            {canVoid && !batch.voided_at && (
                                <VoidBatch
                                    batch={batch}
                                    form={voidMethod.form(batch.id)}
                                />
                            )}
                        </>
                    }
                />

                <BatchPrint
                    print={print}
                    form={
                        canVoid && !batch.voided_at && batch.stock > 0
                            ? storePdf.form(batch.id)
                            : null
                    }
                    downloadHref={(pdf) =>
                        download.url({ batch: batch.id, pdf: pdf.id })
                    }
                />
            </div>
        </>
    );
}

BatchShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: __('Card batches'), href: index() },
        { title: __('Card batch'), href: show(props.batch.id) },
    ],
});
