import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Clock, FileQuestion, ServerCrash, Wrench } from 'lucide-react';
import { PreferenceSwitchers } from '@/components/preferences/preference-switchers';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { __ } from '@/i18n';
import { dashboard, home } from '@/routes';

type Status = 404 | 419 | 500 | 503;

/**
 * 404, 419, 500 and 503 pages, rendered by the exception handler
 * (`App\Support\ErrorPage`). 403 has its own page (`errors/forbidden`).
 */
export default function ErrorPage({ status }: { status: Status }) {
    const { auth } = usePage().props;
    const copy: Record<
        Status,
        { icon: LucideIcon; title: string; description: string }
    > = {
        404: {
            icon: FileQuestion,
            title: __('Page not found'),
            description: __(
                'The page you are looking for does not exist or was moved.',
            ),
        },
        419: {
            icon: Clock,
            title: __('Page expired'),
            description: __(
                'Your session expired. Reload the page and try again.',
            ),
        },
        500: {
            icon: ServerCrash,
            title: __('Something went wrong'),
            description: __(
                'An unexpected error happened on our side. Try again in a moment.',
            ),
        },
        503: {
            icon: Wrench,
            title: __('Down for maintenance'),
            description: __(
                'We are making some improvements. Check back in a few minutes.',
            ),
        },
    };

    const { icon: Icon, title, description } = copy[status] ?? copy[500];

    return (
        <>
            <Head title={title} />
            <div className="relative flex min-h-svh items-center justify-center bg-background p-6">
                <PreferenceSwitchers className="absolute top-4 right-4" />
                <Empty className="max-w-md">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <Icon />
                        </EmptyMedia>
                        <p className="text-5xl font-bold text-muted-foreground">
                            {status}
                        </p>
                        <EmptyTitle>{title}</EmptyTitle>
                        <EmptyDescription>{description}</EmptyDescription>
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
                    </EmptyContent>
                </Empty>
            </div>
        </>
    );
}
