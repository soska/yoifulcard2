import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { useState } from 'react';
import { CardUsageNotice } from '@/components/cards/card-usage-notice';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/settings';
import { update as updateOrganization } from '@/routes/settings/organization';
import { update as updateProgram } from '@/routes/settings/program';
import { index as rewardsIndex } from '@/routes/rewards';
import { update as updateRewards } from '@/routes/settings/rewards';
import type { EarnMode } from '@/types/enums.generated';
import type {
    CardUsage,
    OrganizationSettings,
    ProgramSettings,
    RewardsSettings,
} from '@/types';
import { __ } from '@/i18n';

type Props = {
    organization: OrganizationSettings;
    program: ProgramSettings | null;
    usage: CardUsage;
    canEdit: boolean;
    currencies: string[];
    timezones: string[];
};

function ReadOnlyNotice() {
    const { currentOrganization } = usePage().props;
    const suspended = currentOrganization?.status !== 'active';
    return (
        <Alert>
            <Lock />
            <AlertTitle>{__('These settings are read-only')}</AlertTitle>
            <AlertDescription>
                {suspended
                    ? __('This business is suspended. Contact support.')
                    : __(
                          'Only owners and managers can change business settings.',
                      )}
            </AlertDescription>
        </Alert>
    );
}

function PlanUsage({ usage }: { usage: CardUsage }) {
    return (
        <div className="space-y-4">
            <Heading
                variant="small"
                title={__('Plan')}
                description={__("Cards used against your plan's limit.")}
            />
            <div className="flex items-end justify-between gap-4">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {__('Cards used')}
                    </p>
                    <p className="text-2xl font-semibold tabular-nums">
                        {usage.used}
                        {usage.limit !== null && (
                            <span className="text-base font-normal text-muted-foreground">
                                {' '}
                                / {usage.limit}
                            </span>
                        )}
                    </p>
                </div>
                <p className="text-sm text-muted-foreground">
                    {usage.limit === null
                        ? __('Unlimited')
                        : __('{percent}% used', {
                              percent: usage.percent ?? 0,
                          })}
                </p>
            </div>
            {usage.limit !== null && (
                <Progress
                    value={Math.min(usage.percent ?? 0, 100)}
                    aria-label={__('Cards used')}
                />
            )}
            <CardUsageNotice usage={usage} />
        </div>
    );
}

function OrganizationForm({
    organization,
    canEdit,
    currencies,
    timezones,
}: Pick<Props, 'organization' | 'canEdit' | 'currencies' | 'timezones'>) {
    const currencyItems = currencies.map((code) => ({
        value: code,
        label: code,
    }));
    const timezoneItems = timezones.map((zone) => ({
        value: zone,
        label: zone.replaceAll('_', ' '),
    }));

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title={__('Business')}
                description={__(
                    'Your name, logo, and color appear on the public card page.',
                )}
            />
            <Form
                {...updateOrganization.form()}
                disableWhileProcessing
                resetOnSuccess={['logo', 'remove_logo']}
                options={{ preserveScroll: true }}
            >
                {({ processing, errors }) => (
                    <FieldSet disabled={!canEdit}>
                        <FieldGroup>
                            {errors.organization && (
                                <Alert variant="destructive">
                                    <AlertDescription>
                                        {errors.organization}
                                    </AlertDescription>
                                </Alert>
                            )}

                            <Field data-invalid={!!errors.name}>
                                <FieldLabel htmlFor="name">
                                    {__('Business name')}
                                </FieldLabel>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={organization.name}
                                    required
                                    maxLength={255}
                                    aria-invalid={!!errors.name}
                                />
                                <FieldError>{errors.name}</FieldError>
                            </Field>

                            <Field data-invalid={!!errors.logo}>
                                <FieldLabel htmlFor="logo">
                                    {__('Logo')}
                                </FieldLabel>
                                <div className="flex items-center gap-4">
                                    <Avatar className="size-14 rounded-lg">
                                        {organization.logo_url && (
                                            <AvatarImage
                                                src={organization.logo_url}
                                                alt=""
                                                className="object-contain"
                                            />
                                        )}
                                        <AvatarFallback className="rounded-lg">
                                            {organization.name
                                                .slice(0, 1)
                                                .toUpperCase()}
                                        </AvatarFallback>
                                    </Avatar>
                                    <Input
                                        id="logo"
                                        name="logo"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        aria-invalid={!!errors.logo}
                                    />
                                </div>
                                <FieldDescription>
                                    {__('PNG, JPG, or WebP, up to 2 MB.')}
                                </FieldDescription>
                                <FieldError>{errors.logo}</FieldError>
                            </Field>

                            {organization.logo_url && (
                                <Field orientation="horizontal">
                                    <Checkbox
                                        id="remove_logo"
                                        name="remove_logo"
                                        value="1"
                                        disabled={!canEdit}
                                    />
                                    <FieldLabel
                                        htmlFor="remove_logo"
                                        className="font-normal"
                                    >
                                        {__('Remove the current logo')}
                                    </FieldLabel>
                                </Field>
                            )}

                            <Field data-invalid={!!errors.primary_color}>
                                <FieldLabel htmlFor="primary_color">
                                    {__('Brand color')}
                                </FieldLabel>
                                <Input
                                    id="primary_color"
                                    name="primary_color"
                                    type="color"
                                    defaultValue={organization.primary_color}
                                    className="h-10 w-20 cursor-pointer p-1"
                                    aria-invalid={!!errors.primary_color}
                                />
                                <FieldDescription>
                                    {__('Used on the public card page.')}
                                </FieldDescription>
                                <FieldError>{errors.primary_color}</FieldError>
                            </Field>

                            <Field data-invalid={!!errors.currency}>
                                <FieldLabel htmlFor="currency">
                                    {__('Currency')}
                                </FieldLabel>
                                <Select
                                    name="currency"
                                    defaultValue={organization.currency}
                                    items={currencyItems}
                                    disabled={!canEdit}
                                >
                                    <SelectTrigger
                                        id="currency"
                                        className="w-full sm:w-40"
                                        aria-invalid={!!errors.currency}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {currencyItems.map((item) => (
                                            <SelectItem
                                                key={item.value}
                                                value={item.value}
                                            >
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FieldDescription>
                                    {__(
                                        'Balances and transactions are shown in this currency.',
                                    )}
                                </FieldDescription>
                                <FieldError>{errors.currency}</FieldError>
                            </Field>

                            <Field data-invalid={!!errors.timezone}>
                                <FieldLabel htmlFor="timezone">
                                    {__('Timezone')}
                                </FieldLabel>
                                <Select
                                    name="timezone"
                                    defaultValue={organization.timezone}
                                    items={timezoneItems}
                                    disabled={!canEdit}
                                >
                                    <SelectTrigger
                                        id="timezone"
                                        className="w-full"
                                        aria-invalid={!!errors.timezone}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-80">
                                        {timezoneItems.map((item) => (
                                            <SelectItem
                                                key={item.value}
                                                value={item.value}
                                            >
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FieldDescription>
                                    {__(
                                        'Days in reports, filters, and analytics start at midnight here.',
                                    )}
                                </FieldDescription>
                                <FieldError>{errors.timezone}</FieldError>
                            </Field>

                            <div>
                                <Button
                                    type="submit"
                                    disabled={processing || !canEdit}
                                >
                                    {processing && <Spinner />}
                                    {__('Save business')}
                                </Button>
                            </div>
                        </FieldGroup>
                    </FieldSet>
                )}
            </Form>
        </div>
    );
}

function ProgramForm({
    program,
    canEdit,
}: {
    program: ProgramSettings;
    canEdit: boolean;
}) {
    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title={__('Program')}
                description={__('The card program your cards are issued from.')}
            />
            <Form
                {...updateProgram.form()}
                disableWhileProcessing
                options={{ preserveScroll: true }}
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

                            <Field data-invalid={!!errors.name}>
                                <FieldLabel htmlFor="program_name">
                                    {__('Program name')}
                                </FieldLabel>
                                <Input
                                    id="program_name"
                                    name="name"
                                    defaultValue={program.name}
                                    required
                                    maxLength={255}
                                    aria-invalid={!!errors.name}
                                />
                                <FieldDescription>
                                    {__('For example “Gift Card”.')}
                                </FieldDescription>
                                <FieldError>{errors.name}</FieldError>
                            </Field>

                            <Field data-invalid={!!errors.terms_url}>
                                <FieldLabel htmlFor="terms_url">
                                    {__('Terms URL (optional)')}
                                </FieldLabel>
                                <Input
                                    id="terms_url"
                                    name="terms_url"
                                    type="url"
                                    defaultValue={program.terms_url ?? ''}
                                    placeholder={__(
                                        'https://yourbusiness.com/terms',
                                    )}
                                    aria-invalid={!!errors.terms_url}
                                />
                                <FieldDescription>
                                    {__('A link to your terms and conditions.')}
                                </FieldDescription>
                                <FieldError>{errors.terms_url}</FieldError>
                            </Field>

                            <div>
                                <Button
                                    type="submit"
                                    disabled={processing || !canEdit}
                                >
                                    {processing && <Spinner />}
                                    {__('Save program')}
                                </Button>
                            </div>
                        </FieldGroup>
                    </FieldSet>
                )}
            </Form>
        </div>
    );
}

function RewardsForm({
    rewards,
    canEdit,
}: {
    rewards: RewardsSettings;
    canEdit: boolean;
}) {
    const [enabled, setEnabled] = useState(rewards.enabled);
    const [mode, setMode] = useState<EarnMode>(rewards.mode ?? 'spend');
    const modeItems: { value: EarnMode; label: string }[] = [
        { value: 'spend', label: __('Points for each amount spent') },
        { value: 'visits', label: __('One point per visit') },
    ];

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title={__('Rewards')}
                description={__(
                    'Give customers points when they buy, and let them redeem rewards later.',
                )}
            />
            <Form
                {...updateRewards.form()}
                disableWhileProcessing
                options={{ preserveScroll: true }}
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

                            <input
                                type="hidden"
                                name="rewards_enabled"
                                value={enabled ? '1' : '0'}
                            />
                            <Field orientation="horizontal">
                                <Checkbox
                                    id="rewards_enabled"
                                    checked={enabled}
                                    onCheckedChange={(checked) =>
                                        setEnabled(checked === true)
                                    }
                                    disabled={!canEdit}
                                />
                                <FieldLabel
                                    htmlFor="rewards_enabled"
                                    className="font-normal"
                                >
                                    {__('Turn on rewards')}
                                </FieldLabel>
                            </Field>

                            {enabled && (
                                <>
                                    <Field data-invalid={!!errors.earn_mode}>
                                        <FieldLabel htmlFor="earn_mode">
                                            {__('How points are earned')}
                                        </FieldLabel>
                                        <Select
                                            name="earn_mode"
                                            value={mode}
                                            onValueChange={(value) =>
                                                setMode(value as EarnMode)
                                            }
                                            items={modeItems}
                                            disabled={!canEdit}
                                        >
                                            <SelectTrigger
                                                id="earn_mode"
                                                className="w-full sm:w-72"
                                                aria-invalid={
                                                    !!errors.earn_mode
                                                }
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {modeItems.map((item) => (
                                                    <SelectItem
                                                        key={item.value}
                                                        value={item.value}
                                                    >
                                                        {item.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <FieldError>
                                            {errors.earn_mode}
                                        </FieldError>
                                    </Field>

                                    {mode === 'spend' ? (
                                        <Field
                                            data-invalid={!!errors.earn_rate}
                                        >
                                            <FieldLabel htmlFor="earn_rate">
                                                {__('Points per 1.00 spent')}
                                            </FieldLabel>
                                            <Input
                                                id="earn_rate"
                                                name="earn_rate"
                                                inputMode="decimal"
                                                autoComplete="off"
                                                defaultValue={
                                                    rewards.rate ?? '1.00'
                                                }
                                                className="w-full sm:w-40"
                                                aria-invalid={
                                                    !!errors.earn_rate
                                                }
                                                required
                                            />
                                            <FieldDescription>
                                                {__(
                                                    'Customers earn whole points: a part of a point is dropped.',
                                                )}
                                            </FieldDescription>
                                            <FieldError>
                                                {errors.earn_rate}
                                            </FieldError>
                                        </Field>
                                    ) : (
                                        <FieldDescription>
                                            {__(
                                                {
                                                    one: 'A card earns points for up to {count} visit a day.',
                                                    other: 'A card earns points for up to {count} visits a day.',
                                                },
                                                { count: rewards.visit_cap },
                                            )}
                                        </FieldDescription>
                                    )}
                                </>
                            )}

                            <div>
                                <Button
                                    type="submit"
                                    disabled={processing || !canEdit}
                                >
                                    {processing && <Spinner />}
                                    {__('Save rewards')}
                                </Button>
                                {rewards.enabled && canEdit && (
                                    <Button
                                        type="button"
                                        variant="link"
                                        nativeButton={false}
                                        render={<Link href={rewardsIndex()} />}
                                    >
                                        {__('Manage rewards')}
                                    </Button>
                                )}
                            </div>
                        </FieldGroup>
                    </FieldSet>
                )}
            </Form>
        </div>
    );
}

export default function BusinessSettings({
    organization,
    program,
    usage,
    canEdit,
    currencies,
    timezones,
}: Props) {
    return (
        <>
            <Head title={__('Business settings')} />

            <h1 className="sr-only">{__('Business settings')}</h1>

            {!canEdit && <ReadOnlyNotice />}

            <PlanUsage usage={usage} />

            <OrganizationForm
                organization={organization}
                canEdit={canEdit}
                currencies={currencies}
                timezones={timezones}
            />

            {program && <ProgramForm program={program} canEdit={canEdit} />}

            {program && (
                <RewardsForm rewards={program.rewards} canEdit={canEdit} />
            )}
        </>
    );
}

BusinessSettings.layout = () => ({
    breadcrumbs: [
        {
            title: __('Business settings'),
            href: edit(),
        },
    ],
});
