import { Form, Head, Link } from '@inertiajs/react';
import { Ban, CircleCheck, ExternalLink } from 'lucide-react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { OneTimeCredentials } from '@/components/admin/one-time-credentials';
import { OrganizationStatusBadge } from '@/components/admin/organization-status-badge';
import { RoleBadge } from '@/components/admin/role-badge';
import { CardStockNotice } from '@/components/cards/card-stock-notice';
import { CardUsageNotice } from '@/components/cards/card-usage-notice';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { BatchCreateForm } from '@/components/batches/batch-create-form';
import { BatchesTable } from '@/components/batches/batches-table';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useDateFormat } from '@/hooks/use-date-format';
import { useMoneyFormat } from '@/hooks/use-money-format';
import { index as adminIndex } from '@/routes/admin';
import { show as batchShow } from '@/routes/admin/batches';
import {
    index,
    reactivate,
    show,
    suspend,
    update,
} from '@/routes/admin/organizations';
import { store as storeBatch } from '@/routes/admin/organizations/batches';
import { index as usersIndex } from '@/routes/admin/users';
import type { AdminOrganizationPage } from '@/types';
import { __ } from '@/i18n';

/** A program's type, in words. Unknown types are shown as sent. */
function programTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        prepaid: __('Prepaid', { context: 'program type' }),
    };

    return labels[type] ?? type;
}

export default function AdminOrganizationShow({
    organization,
    usage,
    stats,
    members,
    programs,
    batches,
    maxBatchSize,
}: AdminOrganizationPage) {
    const { formatMoney } = useMoneyFormat();
    const { formatDateTimeInZone } = useDateFormat();
    const timeZone = organization.timezone;
    const cards =
        stats.inactive +
        stats.active +
        stats.frozen +
        stats.depleted +
        stats.cancelled;

    const tiles = [
        {
            label: __('Members'),
            value: String(members.length),
            detail: __(
                { one: '{count} owner', other: '{count} owners' },
                {
                    count: members.filter((member) => member.role === 'owner')
                        .length,
                },
            ),
        },
        {
            label: __('Cards'),
            value: String(cards),
            detail: __(
                '{active} active, {frozen} frozen, {depleted} depleted',
                {
                    active: stats.active,
                    frozen: stats.frozen,
                    depleted: stats.depleted,
                },
            ),
        },
        {
            label: __('Outstanding balance'),
            value: formatMoney(stats.outstandingBalance, organization.currency),
            detail: organization.currency,
        },
        {
            label: __('Transactions'),
            value: String(stats.transactions),
            detail: __('{amount} loaded', {
                amount: formatMoney(stats.loaded, organization.currency),
            }),
        },
    ];

    return (
        <>
            <Head title={organization.name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
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
                                {organization.name.slice(0, 1).toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <div className="flex flex-col gap-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-xl font-semibold tracking-tight">
                                    {organization.name}
                                </h1>
                                <OrganizationStatusBadge
                                    status={organization.status}
                                />
                            </div>
                            <p className="text-sm text-muted-foreground">
                                /{organization.slug}
                            </p>
                        </div>
                    </div>

                    {organization.status === 'active' && (
                        <ConfirmAction
                            trigger={<Button variant="destructive" />}
                            triggerLabel={
                                <>
                                    <Ban data-icon="inline-start" />
                                    {__('Suspend')}
                                </>
                            }
                            title={__('Suspend {name}?', {
                                name: organization.name,
                            })}
                            description={__(
                                'Members can still sign in and read their cards, transactions, and settings, but they cannot create cards, change balances, or edit anything. The public card page keeps showing balances.',
                            )}
                            confirmLabel={__('Suspend organization')}
                            form={suspend.form(organization.id)}
                            destructive
                        />
                    )}
                    {organization.status === 'suspended' && (
                        <ConfirmAction
                            trigger={<Button />}
                            triggerLabel={
                                <>
                                    <CircleCheck data-icon="inline-start" />
                                    {__('Reactivate')}
                                </>
                            }
                            title={__('Reactivate {name}?', {
                                name: organization.name,
                            })}
                            description={__(
                                'Members can create cards and post transactions again.',
                            )}
                            confirmLabel={__('Reactivate organization')}
                            form={reactivate.form(organization.id)}
                        />
                    )}
                </div>

                <OneTimeCredentials />

                {organization.status === 'cancelled' && (
                    <Alert>
                        <AlertDescription>
                            {__(
                                'This organization is cancelled. Cancelled status is not managed from the admin area yet.',
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {tiles.map((tile) => (
                        <Card key={tile.label}>
                            <CardHeader>
                                <CardDescription>{tile.label}</CardDescription>
                                <CardTitle className="text-2xl tabular-nums">
                                    {tile.value}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm text-muted-foreground">
                                {tile.detail}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{__('Plan')}</CardTitle>
                            <CardDescription>
                                {__(
                                    'Card limit and internal notes. Only superadmins see the notes.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <div className="flex flex-col gap-2">
                                <div className="flex items-end justify-between gap-4">
                                    <p className="text-2xl font-semibold tabular-nums">
                                        {usage.used}
                                        <span className="text-base font-normal text-muted-foreground">
                                            {' '}
                                            /{' '}
                                            {usage.limit === null
                                                ? __('Unlimited')
                                                : usage.limit}
                                        </span>
                                    </p>
                                    {usage.limit !== null && (
                                        <p className="text-sm text-muted-foreground">
                                            {__('{percent}% used', {
                                                percent: usage.percent ?? 0,
                                            })}
                                        </p>
                                    )}
                                </div>
                                {usage.limit !== null && (
                                    <Progress
                                        value={Math.min(
                                            usage.percent ?? 0,
                                            100,
                                        )}
                                        aria-label={__('Cards used')}
                                    />
                                )}
                                <p className="text-sm text-muted-foreground">
                                    {organization.preissue_limit === null
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
                                              {
                                                  count: usage.stock,
                                                  limit: organization.preissue_limit,
                                              },
                                          )}
                                </p>
                                <CardUsageNotice usage={usage} />
                                <CardStockNotice usage={usage} />
                            </div>

                            <Form
                                {...update.form(organization.id)}
                                disableWhileProcessing
                                options={{ preserveScroll: true }}
                            >
                                {({ processing, errors }) => (
                                    <FieldGroup>
                                        <Field
                                            data-invalid={!!errors.card_limit}
                                        >
                                            <FieldLabel htmlFor="card_limit">
                                                {__('Card limit')}
                                            </FieldLabel>
                                            <Input
                                                id="card_limit"
                                                name="card_limit"
                                                type="number"
                                                min={1}
                                                step={1}
                                                inputMode="numeric"
                                                defaultValue={
                                                    organization.card_limit ??
                                                    ''
                                                }
                                                placeholder={__('Unlimited')}
                                                className="w-40"
                                                aria-invalid={
                                                    !!errors.card_limit
                                                }
                                            />
                                            <FieldDescription>
                                                {__(
                                                    'Leave empty for unlimited. Creation is refused at the limit.',
                                                )}
                                            </FieldDescription>
                                            <FieldError>
                                                {errors.card_limit}
                                            </FieldError>
                                        </Field>
                                        <Field
                                            data-invalid={
                                                !!errors.preissue_limit
                                            }
                                        >
                                            <FieldLabel htmlFor="preissue_limit">
                                                {__('Stock limit')}
                                            </FieldLabel>
                                            <Input
                                                id="preissue_limit"
                                                name="preissue_limit"
                                                type="number"
                                                min={0}
                                                step={1}
                                                inputMode="numeric"
                                                defaultValue={
                                                    organization.preissue_limit ??
                                                    ''
                                                }
                                                placeholder={__('Unlimited')}
                                                className="w-40"
                                                aria-invalid={
                                                    !!errors.preissue_limit
                                                }
                                            />
                                            <FieldDescription>
                                                {__(
                                                    'The most cards not activated yet that the business can hold at once. Leave empty for unlimited.',
                                                )}
                                            </FieldDescription>
                                            <FieldError>
                                                {errors.preissue_limit}
                                            </FieldError>
                                        </Field>
                                        <Field orientation="horizontal">
                                            <Checkbox
                                                id="can_preissue"
                                                name="can_preissue"
                                                value="1"
                                                defaultChecked={
                                                    organization.can_preissue
                                                }
                                            />
                                            <div className="flex flex-col gap-1">
                                                <FieldLabel
                                                    htmlFor="can_preissue"
                                                    className="font-normal"
                                                >
                                                    {__(
                                                        'The business can create card batches',
                                                    )}
                                                </FieldLabel>
                                                <FieldDescription>
                                                    {__(
                                                        'Owners and managers preissue cards themselves. Superadmins can always create batches here.',
                                                    )}
                                                </FieldDescription>
                                            </div>
                                        </Field>
                                        <Field
                                            data-invalid={!!errors.plan_notes}
                                        >
                                            <FieldLabel htmlFor="plan_notes">
                                                {__('Plan notes')}
                                            </FieldLabel>
                                            <Textarea
                                                id="plan_notes"
                                                name="plan_notes"
                                                rows={3}
                                                maxLength={5000}
                                                defaultValue={
                                                    organization.plan_notes ??
                                                    ''
                                                }
                                                placeholder={__(
                                                    'For example: Starter plan, billed annually',
                                                )}
                                                aria-invalid={
                                                    !!errors.plan_notes
                                                }
                                            />
                                            <FieldError>
                                                {errors.plan_notes}
                                            </FieldError>
                                        </Field>
                                        <div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                {__('Save plan')}
                                            </Button>
                                        </div>
                                    </FieldGroup>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between gap-4">
                                <div className="flex flex-col gap-1">
                                    <CardTitle>{__('Members')}</CardTitle>
                                    <CardDescription>
                                        {__(
                                            'Set a temporary password from the users page.',
                                        )}
                                    </CardDescription>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    render={<Link href={usersIndex()} />}
                                >
                                    {__('Users')}
                                    <ExternalLink data-icon="inline-end" />
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {members.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {__('No members.')}
                                    </p>
                                ) : (
                                    <ul className="divide-y">
                                        {members.map((member) => (
                                            <li
                                                key={member.id}
                                                className="flex items-center justify-between gap-4 py-2"
                                            >
                                                <div className="min-w-0">
                                                    <Link
                                                        href={usersIndex.url({
                                                            query: {
                                                                q:
                                                                    member.email ??
                                                                    undefined,
                                                            },
                                                        })}
                                                        className="truncate font-medium hover:underline"
                                                    >
                                                        {member.name}
                                                    </Link>
                                                    <p className="truncate text-sm text-muted-foreground">
                                                        {member.email}
                                                    </p>
                                                </div>
                                                <RoleBadge role={member.role} />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{__('Programs')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {programs.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {__('No programs.')}
                                    </p>
                                ) : (
                                    <ul className="divide-y">
                                        {programs.map((program) => (
                                            <li
                                                key={program.id}
                                                className="flex items-center justify-between gap-4 py-2"
                                            >
                                                <div>
                                                    <p className="font-medium">
                                                        {program.name}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {programTypeLabel(
                                                            program.type,
                                                        )}
                                                    </p>
                                                </div>
                                                <Badge
                                                    variant={
                                                        program.is_active
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {program.is_active
                                                        ? __('Active', {
                                                              context:
                                                                  'program status',
                                                          })
                                                        : __('Inactive', {
                                                              context:
                                                                  'program status',
                                                          })}
                                                </Badge>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{__('Card batches')}</CardTitle>
                        <CardDescription>
                            {__(
                                'Preissue inactive cards to print on behalf of the business. They count toward the stock limit, not the card limit, until activated.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-6">
                        <BatchCreateForm
                            form={storeBatch.form(organization.id)}
                            usage={usage}
                            preissueLimit={organization.preissue_limit}
                            maxBatchSize={maxBatchSize}
                            withNotes
                            disabled={organization.status !== 'active'}
                            disabledReason={__(
                                'This business is suspended. Contact support.',
                            )}
                        />
                        {batches.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {__('No batches yet.')}
                            </p>
                        ) : (
                            <BatchesTable
                                batches={batches}
                                href={(batch) => batchShow(batch.id)}
                                showNotes
                            />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <Heading
                            variant="small"
                            title={__('Details')}
                            description={__(
                                "Times are in the organization's timezone, {timezone}.",
                                { timezone: timeZone.replaceAll('_', ' ') },
                            )}
                        />
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <dt className="text-muted-foreground">
                                    {__('ID')}
                                </dt>
                                <dd className="font-mono text-xs break-all">
                                    {organization.id}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {__('Currency')}
                                </dt>
                                <dd>{organization.currency}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {__('Created')}
                                </dt>
                                <dd>
                                    {formatDateTimeInZone(
                                        organization.created_at,
                                        timeZone,
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {__('Last updated')}
                                </dt>
                                <dd>
                                    {formatDateTimeInZone(
                                        organization.updated_at,
                                        timeZone,
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminOrganizationShow.layout = (props: AdminOrganizationPage) => ({
    breadcrumbs: [
        { title: __('Admin'), href: adminIndex() },
        { title: __('Organizations'), href: index() },
        {
            title: props.organization.name,
            href: show(props.organization.id),
        },
    ],
});
