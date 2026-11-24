import { Form, Head, Link } from '@inertiajs/react';
import { Gift } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { __ } from '@/i18n';
import { edit as settingsEdit } from '@/routes/settings';
import { index, store, update } from '@/routes/rewards';
import type { Reward } from '@/types';

type Props = {
    rewards: Reward[];
    /** Whether the business has rewards on. Rewards can be prepared before. */
    rewardsEnabled: boolean;
    canEdit: boolean;
    maxCost: number;
};

function NewRewardForm({
    canEdit,
    maxCost,
}: {
    canEdit: boolean;
    maxCost: number;
}) {
    return (
        <Form
            {...store.form()}
            disableWhileProcessing
            options={{ preserveScroll: true }}
            resetOnSuccess
        >
            {({ processing, errors }) => (
                <FieldSet disabled={!canEdit}>
                    <FieldGroup>
                        {(errors.organization ?? errors.program) && (
                            <Alert variant="destructive">
                                <AlertDescription>
                                    {errors.organization ?? errors.program}
                                </AlertDescription>
                            </Alert>
                        )}
                        <div className="grid gap-4 sm:grid-cols-[1fr_10rem]">
                            <Field data-invalid={!!errors.name}>
                                <FieldLabel htmlFor="new-reward-name">
                                    {__('Reward name')}
                                </FieldLabel>
                                <Input
                                    id="new-reward-name"
                                    name="name"
                                    maxLength={120}
                                    required
                                    aria-invalid={!!errors.name}
                                />
                                <FieldDescription>
                                    {__('For example “Free coffee”.')}
                                </FieldDescription>
                                <FieldError>{errors.name}</FieldError>
                            </Field>
                            <Field data-invalid={!!errors.points_cost}>
                                <FieldLabel htmlFor="new-reward-cost">
                                    {__('Cost (points)')}
                                </FieldLabel>
                                <Input
                                    id="new-reward-cost"
                                    name="points_cost"
                                    inputMode="numeric"
                                    autoComplete="off"
                                    placeholder="10"
                                    maxLength={String(maxCost).length}
                                    required
                                    aria-invalid={!!errors.points_cost}
                                />
                                <FieldError>{errors.points_cost}</FieldError>
                            </Field>
                        </div>
                        <div>
                            <Button
                                type="submit"
                                disabled={processing || !canEdit}
                            >
                                {processing && <Spinner />}
                                {__('Add reward')}
                            </Button>
                        </div>
                    </FieldGroup>
                </FieldSet>
            )}
        </Form>
    );
}

function RewardRow({ reward, canEdit }: { reward: Reward; canEdit: boolean }) {
    const [active, setActive] = useState(reward.is_active);

    return (
        <Form
            {...update.form(reward)}
            disableWhileProcessing
            options={{ preserveScroll: true }}
        >
            {({ processing, errors }) => (
                <FieldSet disabled={!canEdit}>
                    <FieldGroup>
                        <div className="grid items-start gap-4 sm:grid-cols-[1fr_8rem_auto_auto]">
                            <Field data-invalid={!!errors.name}>
                                <FieldLabel
                                    htmlFor={`reward-name-${reward.id}`}
                                    className="sr-only"
                                >
                                    {__('Reward name')}
                                </FieldLabel>
                                <Input
                                    id={`reward-name-${reward.id}`}
                                    name="name"
                                    defaultValue={reward.name}
                                    maxLength={120}
                                    required
                                    aria-invalid={!!errors.name}
                                />
                                <FieldError>{errors.name}</FieldError>
                            </Field>
                            <Field data-invalid={!!errors.points_cost}>
                                <FieldLabel
                                    htmlFor={`reward-cost-${reward.id}`}
                                    className="sr-only"
                                >
                                    {__('Cost (points)')}
                                </FieldLabel>
                                <Input
                                    id={`reward-cost-${reward.id}`}
                                    name="points_cost"
                                    inputMode="numeric"
                                    autoComplete="off"
                                    defaultValue={reward.points_cost}
                                    required
                                    aria-invalid={!!errors.points_cost}
                                />
                                <FieldError>{errors.points_cost}</FieldError>
                            </Field>
                            <input
                                type="hidden"
                                name="is_active"
                                value={active ? '1' : '0'}
                            />
                            <Field orientation="horizontal" className="h-9">
                                <Checkbox
                                    id={`reward-active-${reward.id}`}
                                    checked={active}
                                    onCheckedChange={(checked) =>
                                        setActive(checked === true)
                                    }
                                    disabled={!canEdit}
                                />
                                <FieldLabel
                                    htmlFor={`reward-active-${reward.id}`}
                                    className="font-normal"
                                >
                                    {__('Active', {
                                        context: 'reward is on offer',
                                    })}
                                </FieldLabel>
                            </Field>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing || !canEdit}
                            >
                                {processing && <Spinner />}
                                {__('Save')}
                            </Button>
                        </div>
                    </FieldGroup>
                </FieldSet>
            )}
        </Form>
    );
}

/**
 * The reward catalog: what a card's points buy. Owners and managers add
 * rewards and switch them off; a switched-off reward stays in the history of
 * the redemptions made with it.
 */
export default function RewardsIndex({
    rewards,
    rewardsEnabled,
    canEdit,
    maxCost,
}: Props) {
    return (
        <>
            <Head title={__('Rewards')} />
            <div className="flex flex-1 flex-col gap-8 p-4">
                <Heading
                    title={__('Rewards')}
                    description={__(
                        'What a card’s points buy. The reader offers the active rewards.',
                    )}
                />

                {!rewardsEnabled && (
                    <Alert>
                        <AlertDescription>
                            {__(
                                'Rewards are off for your business, so customers do not earn points yet.',
                            )}{' '}
                            <Link
                                href={settingsEdit()}
                                className="font-medium underline"
                            >
                                {__('Turn them on in settings')}
                            </Link>
                        </AlertDescription>
                    </Alert>
                )}

                {!canEdit && (
                    <Alert>
                        <AlertDescription>
                            {__(
                                'You can see the rewards, but they cannot be changed right now.',
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="space-y-4">
                    <Heading variant="small" title={__('New reward')} />
                    <NewRewardForm canEdit={canEdit} maxCost={maxCost} />
                </div>

                <div className="space-y-4">
                    <div className="flex items-center gap-2">
                        <Heading variant="small" title={__('Your rewards')} />
                        {rewards.length > 0 && (
                            <Badge variant="secondary">{rewards.length}</Badge>
                        )}
                    </div>
                    {rewards.length === 0 ? (
                        <Empty className="border">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Gift />
                                </EmptyMedia>
                                <EmptyTitle>{__('No rewards yet')}</EmptyTitle>
                                <EmptyDescription>
                                    {__(
                                        'Add a reward, like a free coffee at 10 points, and staff can redeem it from the reader.',
                                    )}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <div className="flex flex-col gap-6">
                            {rewards.map((reward) => (
                                <RewardRow
                                    key={`${reward.id}-${reward.name}-${reward.points_cost}-${reward.is_active}`}
                                    reward={reward}
                                    canEdit={canEdit}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

RewardsIndex.layout = () => ({
    breadcrumbs: [{ title: __('Rewards'), href: index() }],
});
