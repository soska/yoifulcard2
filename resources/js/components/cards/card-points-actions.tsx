import { Form } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { suspendedMessage } from '@/components/organization/suspended-banner';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { adjust as adjustPoints } from '@/routes/cards/points';
import type { CardDetail } from '@/types';
import { __ } from '@/i18n';

type Props = {
    card: CardDetail;
    writable: boolean;
};

/**
 * The card's reward points and, for owners and managers, a correction with a
 * required note. Refusals from CardLedger come back as validation errors.
 */
export function CardPointsActions({ card, writable }: Props) {
    const blockedReason = !writable
        ? suspendedMessage()
        : card.status === 'frozen'
          ? __('This card is frozen. Unfreeze it to change its points.')
          : card.status === 'cancelled'
            ? __('This card is cancelled.')
            : null;

    return (
        <Card>
            <CardHeader>
                <CardTitle>{__('Reward points')}</CardTitle>
                <CardDescription>
                    {__(
                        {
                            one: 'This card has {count} point.',
                            other: 'This card has {count} points.',
                        },
                        { count: card.points },
                    )}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {blockedReason && (
                    <Alert>
                        <AlertCircle />
                        <AlertDescription>{blockedReason}</AlertDescription>
                    </Alert>
                )}
                <Form
                    {...adjustPoints.form(card)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                >
                    {({ processing, errors }) => {
                        const disabled = processing || blockedReason !== null;
                        const formError = errors.card ?? errors.rewards;

                        return (
                            <FieldGroup>
                                {formError && (
                                    <Alert variant="destructive">
                                        <AlertCircle />
                                        <AlertDescription>
                                            {formError}
                                        </AlertDescription>
                                    </Alert>
                                )}
                                <Field data-invalid={!!errors.points}>
                                    <FieldLabel htmlFor="points-adjustment">
                                        {__('Adjustment (points)')}
                                    </FieldLabel>
                                    <Input
                                        id="points-adjustment"
                                        name="points"
                                        inputMode="numeric"
                                        autoComplete="off"
                                        placeholder="5"
                                        disabled={blockedReason !== null}
                                        aria-invalid={!!errors.points}
                                        required
                                    />
                                    <FieldDescription>
                                        {__(
                                            'Use a negative number to remove points, for example -5.',
                                        )}
                                    </FieldDescription>
                                    <FieldError>{errors.points}</FieldError>
                                </Field>
                                <Field data-invalid={!!errors.note}>
                                    <FieldLabel htmlFor="points-note">
                                        {__('Note')}
                                    </FieldLabel>
                                    <Textarea
                                        id="points-note"
                                        name="note"
                                        maxLength={500}
                                        disabled={blockedReason !== null}
                                        aria-invalid={!!errors.note}
                                        required
                                    />
                                    <FieldError>{errors.note}</FieldError>
                                </Field>
                                <div>
                                    <Button
                                        type="submit"
                                        disabled={disabled}
                                        title={blockedReason ?? undefined}
                                    >
                                        {processing && <Spinner />}
                                        {__('Adjust points')}
                                    </Button>
                                </div>
                            </FieldGroup>
                        );
                    }}
                </Form>
            </CardContent>
        </Card>
    );
}
