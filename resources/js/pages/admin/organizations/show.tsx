import { Form, Head, Link } from '@inertiajs/react';
import { Ban, CircleCheck, ExternalLink } from 'lucide-react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { OneTimeCredentials } from '@/components/admin/one-time-credentials';
import { OrganizationStatusBadge } from '@/components/admin/organization-status-badge';
import { RoleBadge } from '@/components/admin/role-badge';
import { CardUsageNotice } from '@/components/cards/card-usage-notice';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { formatDateTime, formatMoney } from '@/lib/format';
import { index as adminIndex } from '@/routes/admin';
import {
    index,
    reactivate,
    show,
    suspend,
    update,
} from '@/routes/admin/organizations';
import { index as usersIndex } from '@/routes/admin/users';
import type { AdminOrganizationPage } from '@/types';

export default function AdminOrganizationShow({
    organization,
    usage,
    stats,
    members,
    programs,
}: AdminOrganizationPage) {
    const timeZone = organization.timezone;
    const cards =
        stats.active + stats.frozen + stats.depleted + stats.cancelled;

    const tiles = [
        {
            label: 'Members',
            value: String(members.length),
            detail: `${members.filter((member) => member.role === 'owner').length} owner`,
        },
        {
            label: 'Cards',
            value: String(cards),
            detail: `${stats.active} active, ${stats.frozen} frozen, ${stats.depleted} depleted`,
        },
        {
            label: 'Outstanding balance',
            value: formatMoney(stats.outstandingBalance, organization.currency),
            detail: organization.currency,
        },
        {
            label: 'Transactions',
            value: String(stats.transactions),
            detail: `${formatMoney(stats.loaded, organization.currency)} loaded`,
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
                                    Suspend
                                </>
                            }
                            title={`Suspend ${organization.name}?`}
                            description="Members can still sign in and read their cards, transactions, and settings, but they cannot create cards, change balances, or edit anything. The public card page keeps showing balances."
                            confirmLabel="Suspend organization"
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
                                    Reactivate
                                </>
                            }
                            title={`Reactivate ${organization.name}?`}
                            description="Members can create cards and post transactions again."
                            confirmLabel="Reactivate organization"
                            form={reactivate.form(organization.id)}
                        />
                    )}
                </div>

                <OneTimeCredentials />

                {organization.status === 'cancelled' && (
                    <Alert>
                        <AlertDescription>
                            This organization is cancelled. Cancelled status is
                            not managed from the admin area yet.
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
                            <CardTitle>Plan</CardTitle>
                            <CardDescription>
                                Card limit and internal notes. Only superadmins
                                see the notes.
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
                                                ? 'Unlimited'
                                                : usage.limit}
                                        </span>
                                    </p>
                                    {usage.limit !== null && (
                                        <p className="text-sm text-muted-foreground">
                                            {usage.percent ?? 0}% used
                                        </p>
                                    )}
                                </div>
                                {usage.limit !== null && (
                                    <Progress
                                        value={Math.min(
                                            usage.percent ?? 0,
                                            100,
                                        )}
                                        aria-label="Cards used"
                                    />
                                )}
                                <CardUsageNotice usage={usage} />
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
                                                Card limit
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
                                                placeholder="Unlimited"
                                                className="w-40"
                                                aria-invalid={
                                                    !!errors.card_limit
                                                }
                                            />
                                            <FieldDescription>
                                                Leave empty for unlimited.
                                                Creation is refused at the
                                                limit.
                                            </FieldDescription>
                                            <FieldError>
                                                {errors.card_limit}
                                            </FieldError>
                                        </Field>
                                        <Field
                                            data-invalid={!!errors.plan_notes}
                                        >
                                            <FieldLabel htmlFor="plan_notes">
                                                Plan notes
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
                                                placeholder="For example: Starter plan, billed annually"
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
                                                Save plan
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
                                    <CardTitle>Members</CardTitle>
                                    <CardDescription>
                                        Set a temporary password from the users
                                        page.
                                    </CardDescription>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    render={<Link href={usersIndex()} />}
                                >
                                    Users
                                    <ExternalLink data-icon="inline-end" />
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {members.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No members.
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
                                <CardTitle>Programs</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {programs.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No programs.
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
                                                    <p className="text-sm text-muted-foreground capitalize">
                                                        {program.type}
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
                                                        ? 'Active'
                                                        : 'Inactive'}
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
                        <Heading
                            variant="small"
                            title="Details"
                            description={`Times are in the organization's timezone, ${timeZone.replaceAll('_', ' ')}.`}
                        />
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <dt className="text-muted-foreground">ID</dt>
                                <dd className="font-mono text-xs break-all">
                                    {organization.id}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Currency
                                </dt>
                                <dd>{organization.currency}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Created
                                </dt>
                                <dd>
                                    {formatDateTime(organization.created_at, {
                                        timeZone,
                                    })}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Last updated
                                </dt>
                                <dd>
                                    {formatDateTime(organization.updated_at, {
                                        timeZone,
                                    })}
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
        { title: 'Admin', href: adminIndex() },
        { title: 'Organizations', href: index() },
        {
            title: props.organization.name,
            href: show(props.organization.id),
        },
    ],
});
