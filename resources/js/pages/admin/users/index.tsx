import { Head, Link, router, usePage } from '@inertiajs/react';
import { Crown, Ellipsis, KeyRound, Search, Users } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { OneTimeCredentials } from '@/components/admin/one-time-credentials';
import { RoleBadge } from '@/components/admin/role-badge';
import { ListPagination } from '@/components/cards/list-pagination';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useDateFormat } from '@/hooks/use-date-format';
import { index as adminIndex } from '@/routes/admin';
import { show as showOrganization } from '@/routes/admin/organizations';
import { index, password } from '@/routes/admin/users';
import { destroy, store } from '@/routes/admin/users/superadmin';
import type { AdminUserRow, Paginated } from '@/types';
import { __ } from '@/i18n';

type Props = {
    users: Paginated<AdminUserRow>;
    filters: { q: string };
};

export default function AdminUsersIndex({ users, filters }: Props) {
    const { auth, errors } = usePage().props;
    const { formatDate } = useDateFormat();
    const [search, setSearch] = useState(filters.q);

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();

        router.get(
            index.url({ query: { q: search.trim() || undefined } }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title={__('Users')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading
                    title={__('Users')}
                    description={__(
                        'Everyone with an account. Set a temporary password when someone is locked out, and manage superadmins.',
                    )}
                />

                <OneTimeCredentials />

                {errors.superadmin && (
                    <Alert variant="destructive">
                        <AlertDescription>{errors.superadmin}</AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardContent className="flex flex-col gap-4">
                        <form
                            onSubmit={submitSearch}
                            className="flex gap-2"
                            role="search"
                        >
                            <Input
                                type="search"
                                name="q"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={__('Search by name or email')}
                                aria-label={__('Search by name or email')}
                            />
                            <Button type="submit" variant="secondary">
                                <Search data-icon="inline-start" />
                                {__('Search')}
                            </Button>
                        </form>

                        {users.data.length === 0 ? (
                            <Empty className="border">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Users />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {__('No users found')}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {__('Try another name or email.')}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{__('User')}</TableHead>
                                        <TableHead>
                                            {__('Organizations')}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {__('Actions')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.data.map((user) => {
                                        const isYou = user.id === auth.user.id;

                                        return (
                                            <TableRow key={user.id}>
                                                <TableCell>
                                                    <div className="flex flex-wrap items-center gap-2 font-medium">
                                                        {user.name}
                                                        {user.is_superadmin && (
                                                            <span
                                                                role="img"
                                                                aria-label={__(
                                                                    'Superadmin',
                                                                )}
                                                                title={__(
                                                                    'Superadmin',
                                                                )}
                                                                className="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-800 ring-1 ring-amber-300/60 dark:bg-amber-400/15 dark:text-amber-300 dark:ring-amber-400/30"
                                                            >
                                                                <Crown
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                            </span>
                                                        )}
                                                        {isYou && (
                                                            <span className="text-muted-foreground">
                                                                {' '}
                                                                {__('(you)')}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {user.email}
                                                    </div>
                                                    <details className="mt-1 text-xs text-muted-foreground">
                                                        <summary className="cursor-pointer">
                                                            {__('Details')}
                                                        </summary>
                                                        <p className="mt-1">
                                                            {__('Joined')}:{' '}
                                                            {formatDate(
                                                                user.created_at,
                                                            )}
                                                        </p>
                                                    </details>
                                                </TableCell>
                                                <TableCell>
                                                    {user.memberships.length ===
                                                    0 ? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    ) : (
                                                        <ul className="flex flex-col gap-1">
                                                            {user.memberships.map(
                                                                (
                                                                    membership,
                                                                ) => (
                                                                    <li
                                                                        key={
                                                                            membership.organization_id
                                                                        }
                                                                        className="flex items-center gap-2"
                                                                    >
                                                                        <Link
                                                                            href={showOrganization(
                                                                                membership.organization_id,
                                                                            )}
                                                                            className="hover:underline"
                                                                        >
                                                                            {
                                                                                membership.organization
                                                                            }
                                                                        </Link>
                                                                        <RoleBadge
                                                                            role={
                                                                                membership.role
                                                                            }
                                                                        />
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            render={
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label={__(
                                                                        'Actions for {email}',
                                                                        {
                                                                            email: user.email,
                                                                        },
                                                                    )}
                                                                />
                                                            }
                                                        >
                                                            <Ellipsis />
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent
                                                            align="end"
                                                            className="min-w-60"
                                                        >
                                                            <ConfirmAction
                                                                trigger={
                                                                    <DropdownMenuItem
                                                                        closeOnClick={
                                                                            false
                                                                        }
                                                                    />
                                                                }
                                                                triggerLabel={
                                                                    <>
                                                                        <KeyRound data-icon="inline-start" />
                                                                        {__(
                                                                            'Temporary password',
                                                                        )}
                                                                    </>
                                                                }
                                                                title={__(
                                                                    'Set a temporary password for {email}?',
                                                                    {
                                                                        email: user.email,
                                                                    },
                                                                )}
                                                                description={__(
                                                                    'Their current password stops working and they are signed out on other devices. The new password is shown once.',
                                                                )}
                                                                confirmLabel={__(
                                                                    'Set password',
                                                                )}
                                                                form={password.form(
                                                                    user.id,
                                                                )}
                                                            />
                                                            {user.is_superadmin ? (
                                                                <ConfirmAction
                                                                    trigger={
                                                                        <DropdownMenuItem
                                                                            closeOnClick={
                                                                                false
                                                                            }
                                                                        />
                                                                    }
                                                                    triggerLabel={
                                                                        <>
                                                                            <Crown data-icon="inline-start" />
                                                                            {__(
                                                                                'Revoke superadmin',
                                                                            )}
                                                                        </>
                                                                    }
                                                                    title={
                                                                        isYou
                                                                            ? __(
                                                                                  'Revoke your own superadmin role?',
                                                                              )
                                                                            : __(
                                                                                  'Revoke superadmin from {email}?',
                                                                                  {
                                                                                      email: user.email,
                                                                                  },
                                                                              )
                                                                    }
                                                                    description={
                                                                        isYou
                                                                            ? __(
                                                                                  'You will lose access to the admin area right away. The last superadmin cannot be revoked.',
                                                                              )
                                                                            : __(
                                                                                  'They lose access to the admin area. The last superadmin cannot be revoked.',
                                                                              )
                                                                    }
                                                                    confirmLabel={__(
                                                                        'Revoke superadmin',
                                                                    )}
                                                                    confirmationEmail={
                                                                        user.email
                                                                    }
                                                                    form={destroy.form(
                                                                        user.id,
                                                                    )}
                                                                    destructive
                                                                />
                                                            ) : (
                                                                <ConfirmAction
                                                                    trigger={
                                                                        <DropdownMenuItem
                                                                            closeOnClick={
                                                                                false
                                                                            }
                                                                        />
                                                                    }
                                                                    triggerLabel={
                                                                        <>
                                                                            <Crown data-icon="inline-start" />
                                                                            {__(
                                                                                'Grant superadmin',
                                                                            )}
                                                                        </>
                                                                    }
                                                                    title={__(
                                                                        'Make {email} a superadmin?',
                                                                        {
                                                                            email: user.email,
                                                                        },
                                                                    )}
                                                                    description={__(
                                                                        'They can manage every organization, plan, and user.',
                                                                    )}
                                                                    confirmLabel={__(
                                                                        'Grant superadmin',
                                                                    )}
                                                                    confirmationEmail={
                                                                        user.email
                                                                    }
                                                                    form={store.form(
                                                                        user.id,
                                                                    )}
                                                                />
                                                            )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}

                        {users.total > 0 && (
                            <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                                <p className="text-sm text-muted-foreground">
                                    {__(
                                        'Showing {from} to {to} of {total} users',
                                        {
                                            from: users.from ?? 0,
                                            to: users.to ?? 0,
                                            total: users.total,
                                        },
                                    )}
                                </p>
                                <div>
                                    <ListPagination paginator={users} />
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminUsersIndex.layout = () => ({
    breadcrumbs: [
        { title: __('Admin'), href: adminIndex() },
        { title: __('Users'), href: index() },
    ],
});
