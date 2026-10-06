import { Head, Link, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowLeft } from 'lucide-react';
import { BatchDetails } from '@/components/batches/batch-details';
import { BatchPrint } from '@/components/batches/batch-print';
import { VoidBatch } from '@/components/batches/void-batch';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { index as adminIndex } from '@/routes/admin';
import { show, voidMethod } from '@/routes/admin/batches';
import { download, store as storePdf } from '@/routes/admin/batches/pdfs';
import {
    index as organizationsIndex,
    show as organizationShow,
} from '@/routes/admin/organizations';
import type {
    AdminCardBatch,
    CardBatchPrint,
    CardSummary,
    OrganizationStatus,
    Paginated,
} from '@/types';
import { __ } from '@/i18n';

type Props = {
    batch: AdminCardBatch;
    cards: Paginated<CardSummary>;
    currency: string;
    organization: { id: string; name: string; status: OrganizationStatus };
    print: CardBatchPrint;
};

export default function AdminBatchShow({
    batch,
    cards,
    organization,
    print,
}: Props) {
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
                        render={
                            <Link href={organizationShow(organization.id)} />
                        }
                    >
                        <ArrowLeft data-icon="inline-start" />
                        {__('Back to {name}', { name: organization.name })}
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
                    actions={
                        organization.status === 'active' &&
                        !batch.voided_at && (
                            <VoidBatch
                                batch={batch}
                                form={voidMethod.form(batch.id)}
                            />
                        )
                    }
                >
                    {batch.notes && (
                        <div>
                            <p className="text-sm text-muted-foreground">
                                {__('Notes')}
                            </p>
                            <p className="text-sm whitespace-pre-line">
                                {batch.notes}
                            </p>
                        </div>
                    )}
                </BatchDetails>

                <BatchPrint
                    print={print}
                    form={
                        !batch.voided_at && batch.stock > 0
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

AdminBatchShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: __('Admin'), href: adminIndex() },
        { title: __('Organizations'), href: organizationsIndex() },
        {
            title: props.organization.name,
            href: organizationShow(props.organization.id),
        },
        { title: __('Card batch'), href: show(props.batch.id) },
    ],
});
