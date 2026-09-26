import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Spinner } from '@/components/ui/spinner';
import { PreferenceSwitchers } from '@/components/preferences/preference-switchers';
import { dashboard, home } from '@/routes';
import { claim } from '@/routes/admin';
import { __ } from '@/i18n';

export default function Forbidden({
    canClaimSuperadmin = false,
}: {
    canClaimSuperadmin?: boolean;
}) {
    const { auth } = usePage().props;
    return (
        <>
            <Head title={__('Access denied')} />
            <div className="relative flex min-h-svh items-center justify-center bg-background p-6">
                <PreferenceSwitchers className="absolute top-4 right-4" />
                <Empty className="max-w-md">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <ShieldAlert />
                        </EmptyMedia>
                        <p className="text-5xl font-bold text-destructive">
                            403
                        </p>
                        <EmptyTitle>{__('Access denied')}</EmptyTitle>
                        <EmptyDescription>
                            {__(
                                'You do not have permission to access this page.',
                            )}
                        </EmptyDescription>
                    </EmptyHeader>
                    <EmptyContent>
                        <Button
                            nativeButton={false}
                            render={
                                <Link href={auth.user ? dashboard() : home()} />
                            }
                        >
                            {auth.user ? __('Go to dashboard') : __('Go home')}
                        </Button>
                        {canClaimSuperadmin && (
                            <Form {...claim.form()}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                        data-test="claim-superadmin-button"
                                    >
                                        {processing && <Spinner />}
                                        {__('Claim superadmin')}
                                    </Button>
                                )}
                            </Form>
                        )}
                    </EmptyContent>
                </Empty>
            </div>
        </>
    );
}
