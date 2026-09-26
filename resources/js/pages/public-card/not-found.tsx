import { Head } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';

/**
 * Shown for unknown and malformed card links alike, with the same status,
 * so the page does not reveal whether other cards exist.
 */
export default function PublicCardNotFound() {
    return (
        <>
            <Head title="Card not found">
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <main className="flex min-h-svh items-center justify-center bg-muted/40 p-4">
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <AlertCircle />
                        </EmptyMedia>
                        <EmptyTitle>Card not found</EmptyTitle>
                        <EmptyDescription>
                            This card link is invalid or has expired. Please
                            check the QR code or contact the business.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            </main>
        </>
    );
}
