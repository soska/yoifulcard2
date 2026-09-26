import { Head } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { PreferenceSwitchers } from '@/components/preferences/preference-switchers';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Shown for unknown and malformed card links alike, with the same status,
 * so the page does not reveal whether other cards exist.
 */
export default function PublicCardNotFound() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Card not found')}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <main className="relative flex min-h-svh items-center justify-center bg-muted/40 p-4">
                <PreferenceSwitchers className="absolute top-4 right-4" />
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <AlertCircle />
                        </EmptyMedia>
                        <EmptyTitle>{t('Card not found')}</EmptyTitle>
                        <EmptyDescription>
                            {t(
                                'This card link is invalid or has expired. Please check the QR code or contact the business.',
                            )}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            </main>
        </>
    );
}
