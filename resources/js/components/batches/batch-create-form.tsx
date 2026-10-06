import { Form } from '@inertiajs/react';
import { PackageOpen, Plus } from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { CardUsage } from '@/types';
import { __ } from '@/i18n';

type FormSpec = { action: string; method: 'get' | 'post' };

/**
 * How many inactive cards to preissue, with the stock limit and a warning
 * when the new stock won't fit under the card limit. The server checks the
 * stock limit again under a lock; the warning never blocks.
 */
export function BatchCreateForm({
    form,
    usage,
    preissueLimit,
    maxBatchSize,
    withNotes = false,
    disabled = false,
    disabledReason,
}: {
    form: FormSpec;
    usage: CardUsage;
    preissueLimit: number | null;
    maxBatchSize: number;
    /** Superadmins can note what was charged for printing. */
    withNotes?: boolean;
    disabled?: boolean;
    disabledReason?: string;
}) {
    const [count, setCount] = useState('');
    const requested = Math.max(Math.floor(Number(count) || 0), 0);
    const stockRoom =
        preissueLimit === null
            ? null
            : Math.max(preissueLimit - usage.stock, 0);
    const max = Math.max(Math.min(maxBatchSize, stockRoom ?? maxBatchSize), 1);
    const activationRoom =
        usage.limit === null ? null : Math.max(usage.limit - usage.used, 0);
    const beyondRoom =
        activationRoom === null
            ? 0
            : Math.max(usage.stock + requested - activationRoom, 0);
    const full = stockRoom === 0;

    return (
        <Form
            {...form}
            disableWhileProcessing
            options={{ preserveScroll: true }}
        >
            {({ processing, errors }) => (
                <FieldGroup>
                    <Field data-invalid={!!errors.count}>
                        <FieldLabel htmlFor="count">
                            {__('Number of cards')}
                        </FieldLabel>
                        <Input
                            id="count"
                            name="count"
                            type="number"
                            min={1}
                            max={max}
                            step={1}
                            inputMode="numeric"
                            required
                            value={count}
                            onChange={(event) => setCount(event.target.value)}
                            className="w-40"
                            disabled={disabled || full}
                            aria-invalid={!!errors.count}
                        />
                        <FieldDescription>
                            {stockRoom === null
                                ? __('Up to {max} cards per batch.', {
                                      max: maxBatchSize,
                                  })
                                : __(
                                      'Up to {max} cards per batch. Room for {room} more in stock.',
                                      { max: maxBatchSize, room: stockRoom },
                                  )}
                        </FieldDescription>
                        <FieldError>
                            {errors.count ??
                                errors.program ??
                                errors.organization}
                        </FieldError>
                    </Field>

                    {withNotes && (
                        <Field data-invalid={!!errors.notes}>
                            <FieldLabel htmlFor="notes">
                                {__('Notes')}
                            </FieldLabel>
                            <Textarea
                                id="notes"
                                name="notes"
                                rows={2}
                                maxLength={5000}
                                placeholder={__(
                                    'For example: printed 200 cards, charged $1,500',
                                )}
                                aria-invalid={!!errors.notes}
                            />
                            <FieldDescription>
                                {__('Only superadmins see the notes.')}
                            </FieldDescription>
                            <FieldError>{errors.notes}</FieldError>
                        </Field>
                    )}

                    {beyondRoom > 0 && (
                        <Alert>
                            <PackageOpen />
                            <AlertDescription>
                                {__(
                                    {
                                        one: '{count} card in stock would have no room under the card limit. Activating it will be refused until the limit is raised.',
                                        other: '{count} cards in stock would have no room under the card limit. Activating them will be refused until the limit is raised.',
                                    },
                                    { count: beyondRoom },
                                )}
                            </AlertDescription>
                        </Alert>
                    )}

                    <div>
                        <Button
                            type="submit"
                            disabled={processing || disabled || full}
                            title={
                                full
                                    ? __('Stock limit reached')
                                    : disabled
                                      ? disabledReason
                                      : undefined
                            }
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <Plus data-icon="inline-start" />
                            )}
                            {__('Create batch')}
                        </Button>
                    </div>
                </FieldGroup>
            )}
        </Form>
    );
}
