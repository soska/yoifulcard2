import { Form, Head, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
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
import type { CardUsage, OrganizationSettings, ProgramSettings } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

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
    const { t } = useTranslation();

    return (
        <Alert>
            <Lock />
            <AlertTitle>{t('These settings are read-only')}</AlertTitle>
            <AlertDescription>
                {suspended
                    ? t('This business is suspended. Contact support.')
                    : t(
                          'Only owners and managers can change business settings.',
                      )}
            </AlertDescription>
        </Alert>
    );
}

function PlanUsage({ usage }: { usage: CardUsage }) {
    const { t } = useTranslation();

    return (
        <div className="space-y-4">
            <Heading
                variant="small"
                title={t('Plan')}
                description={t("Cards used against your plan's limit.")}
            />
            <div className="flex items-end justify-between gap-4">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {t('Cards used')}
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
                        ? t('Unlimited')
                        : t(':percent% used', { percent: usage.percent ?? 0 })}
                </p>
            </div>
            {usage.limit !== null && (
                <Progress
                    value={Math.min(usage.percent ?? 0, 100)}
                    aria-label={t('Cards used')}
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
    const { t } = useTranslation();
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
                title={t('Business')}
                description={t(
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
                                    {t('Business name')}
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
                                    {t('Logo')}
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
                                    {t('PNG, JPG, or WebP, up to 2 MB.')}
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
                                        {t('Remove the current logo')}
                                    </FieldLabel>
                                </Field>
                            )}

                            <Field data-invalid={!!errors.primary_color}>
                                <FieldLabel htmlFor="primary_color">
                                    {t('Brand color')}
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
                                    {t('Used on the public card page.')}
                                </FieldDescription>
                                <FieldError>{errors.primary_color}</FieldError>
                            </Field>

                            <Field data-invalid={!!errors.currency}>
                                <FieldLabel htmlFor="currency">
                                    {t('Currency')}
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
                                    {t(
                                        'Balances and transactions are shown in this currency.',
                                    )}
                                </FieldDescription>
                                <FieldError>{errors.currency}</FieldError>
                            </Field>

                            <Field data-invalid={!!errors.timezone}>
                                <FieldLabel htmlFor="timezone">
                                    {t('Timezone')}
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
                                    {t(
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
                                    {t('Save business')}
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
    const { t } = useTranslation();

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title={t('Program')}
                description={t('The card program your cards are issued from.')}
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
                                    {t('Program name')}
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
                                    {t('For example “Gift Card”.')}
                                </FieldDescription>
                                <FieldError>{errors.name}</FieldError>
                            </Field>

                            <Field data-invalid={!!errors.terms_url}>
                                <FieldLabel htmlFor="terms_url">
                                    {t('Terms URL (optional)')}
                                </FieldLabel>
                                <Input
                                    id="terms_url"
                                    name="terms_url"
                                    type="url"
                                    defaultValue={program.terms_url ?? ''}
                                    placeholder={t(
                                        'https://yourbusiness.com/terms',
                                    )}
                                    aria-invalid={!!errors.terms_url}
                                />
                                <FieldDescription>
                                    {t('A link to your terms and conditions.')}
                                </FieldDescription>
                                <FieldError>{errors.terms_url}</FieldError>
                            </Field>

                            <div>
                                <Button
                                    type="submit"
                                    disabled={processing || !canEdit}
                                >
                                    {processing && <Spinner />}
                                    {t('Save program')}
                                </Button>
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
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Business settings')} />

            <h1 className="sr-only">{t('Business settings')}</h1>

            {!canEdit && <ReadOnlyNotice />}

            <PlanUsage usage={usage} />

            <OrganizationForm
                organization={organization}
                canEdit={canEdit}
                currencies={currencies}
                timezones={timezones}
            />

            {program && <ProgramForm program={program} canEdit={canEdit} />}
        </>
    );
}

BusinessSettings.layout = {
    breadcrumbs: [
        {
            title: 'Business settings',
            href: edit(),
        },
    ],
};
