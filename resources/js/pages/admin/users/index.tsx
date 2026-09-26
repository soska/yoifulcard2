import { Head, Link, router, usePage } from '@inertiajs/react';
import { KeyRound, Search, ShieldCheck, ShieldOff, Users } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { OneTimeCredentials } from '@/components/admin/one-time-credentials';
import { RoleBadge } from '@/components/admin/role-badge';
import { ListPagination } from '@/components/cards/list-pagination';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
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
            <Head title="Users" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading
                    title="Users"
                    description="Everyone with an account. Set a temporary password when someone is locked out, and manage superadmins."
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
                                placeholder="Search by name or email"
                                aria-label="Search by name or email"
                            />
                            <Button type="submit" variant="secondary">
                                <Search data-icon="inline-start" />
                                Search
                            </Button>
                        </form>

                        {users.data.length === 0 ? (
                            <Empty className="border">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Users />
                                    </EmptyMedia>
                                    <EmptyTitle>No users found</EmptyTitle>
                                    <EmptyDescription>
                                        Try another name or email.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>User</TableHead>
                                        <TableHead>Organizations</TableHead>
                                        <TableHead>Joined</TableHead>
                                        <TableHead>Superadmin</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.data.map((user) => {
                                        const isYou = user.id === auth.user.id;

                                        return (
                                            <TableRow key={user.id}>
                                                <TableCell>
                                                    <div className="font-medium">
                                                        {user.name}
                                                        {isYou && (
                                                            <span className="text-muted-foreground">
                                                                {' '}
                                                                (you)
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {user.email}
                                                    </div>
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
                                                <TableCell className="text-muted-foreground">
                                                    {formatDate(
                                                        user.created_at,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {user.is_superadmin ? (
                                                        <Badge>
                                                            <ShieldCheck data-icon="inline-start" />
                                                            Superadmin
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex justify-end gap-2">
                                                        <ConfirmAction
                                                            trigger={
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                />
                                                            }
                                                            triggerLabel={
                                                                <>
                                                                    <KeyRound data-icon="inline-start" />
                                                                    Temporary
                                                                    password
                                                                </>
                                                            }
                                                            title={`Set a temporary password for ${user.email}?`}
                                                            description="Their current password stops working and they are signed out on other devices. The new password is shown once."
                                                            confirmLabel="Set password"
                                                            form={password.form(
                                                                user.id,
                                                            )}
                                                        />
                                                        {user.is_superadmin ? (
                                                            <ConfirmAction
                                                                trigger={
                                                                    <Button
                                                                        variant="outline"
                                                                        size="sm"
                                                                    />
                                                                }
                                                                triggerLabel={
                                                                    <>
                                                                        <ShieldOff data-icon="inline-start" />
                                                                        Revoke
                                                                    </>
                                                                }
                                                                title={
                                                                    isYou
                                                                        ? 'Revoke your own superadmin role?'
                                                                        : `Revoke superadmin from ${user.email}?`
                                                                }
                                                                description={
                                                                    isYou
                                                                        ? 'You will lose access to the admin area right away. The last superadmin cannot be revoked.'
                                                                        : 'They lose access to the admin area. The last superadmin cannot be revoked.'
                                                                }
                                                                confirmLabel="Revoke superadmin"
                                                                form={destroy.form(
                                                                    user.id,
                                                                )}
                                                                destructive
                                                            />
                                                        ) : (
                                                            <ConfirmAction
                                                                trigger={
                                                                    <Button
                                                                        variant="outline"
                                                                        size="sm"
                                                                    />
                                                                }
                                                                triggerLabel={
                                                                    <>
                                                                        <ShieldCheck data-icon="inline-start" />
                                                                        Grant
                                                                    </>
                                                                }
                                                                title={`Make ${user.email} a superadmin?`}
                                                                description="They can manage every organization, plan, and user."
                                                                confirmLabel="Grant superadmin"
                                                                form={store.form(
                                                                    user.id,
                                                                )}
                                                            />
                                                        )}
                                                    </div>
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
                                    Showing {users.from} to {users.to} of{' '}
                                    {users.total} users
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

AdminUsersIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: adminIndex() },
        { title: 'Users', href: index() },
    ],
};
