import { Ban } from 'lucide-react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { Button } from '@/components/ui/button';
import type { CardBatchSummary } from '@/types';
import { __ } from '@/i18n';

/**
 * Asks before voiding: the cards in stock are cancelled for good.
 */
export function VoidBatch({
    batch,
    form,
}: {
    batch: CardBatchSummary;
    form: { action: string; method: 'get' | 'post' };
}) {
    return (
        <ConfirmAction
            trigger={<Button variant="destructive" size="sm" />}
            triggerLabel={
                <>
                    <Ban data-icon="inline-start" />
                    {__('Void batch')}
                </>
            }
            title={__('Void this batch?')}
            description={__(
                {
                    one: '{count} card still in stock is cancelled and can never be activated. Cards already activated keep working.',
                    other: '{count} cards still in stock are cancelled and can never be activated. Cards already activated keep working.',
                },
                { count: batch.stock },
            )}
            confirmLabel={__('Void batch')}
            form={form}
            destructive
        />
    );
}
