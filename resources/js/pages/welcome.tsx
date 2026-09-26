import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { PreferenceSwitchers } from '@/components/preferences/preference-switchers';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard, login, register } from '@/routes';

/**
 * The marketing page, as in the reference app: what Yoiful is, and a way
 * to sign up or log in.
 */
export default function Welcome() {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Prepaid cards for small businesses')} />
            <main className="relative flex min-h-svh flex-col items-center justify-center gap-8 bg-background p-6 text-center text-foreground">
                <PreferenceSwitchers className="absolute top-4 right-4" />

                <div className="flex flex-col items-center gap-4">
                    <AppLogoIcon className="size-12 fill-current" />
                    <h1 className="text-4xl font-bold tracking-tight">
                        Yoiful
                    </h1>
                    <p className="max-w-md text-xl text-muted-foreground">
                        {t('Prepaid cards for small businesses')}
                    </p>
                </div>

                <div className="flex flex-wrap justify-center gap-3">
                    {auth.user ? (
                        <Button
                            size="lg"
                            nativeButton={false}
                            render={<Link href={dashboard()} />}
                        >
                            {t('Dashboard')}
                        </Button>
                    ) : (
                        <>
                            <Button
                                size="lg"
                                nativeButton={false}
                                render={<Link href={register()} />}
                            >
                                {t('Get started')}
                            </Button>
                            <Button
                                size="lg"
                                variant="outline"
                                nativeButton={false}
                                render={<Link href={login()} />}
                            >
                                {t('Log in')}
                            </Button>
                        </>
                    )}
                </div>
            </main>
        </>
    );
}
