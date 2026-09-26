import { Head, router } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { useState } from 'react';
import { QrScanner } from '@/components/reader/qr-scanner';
import { RecentScans } from '@/components/reader/recent-scans';
import { SoundToggle } from '@/components/reader/sound-toggle';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Spinner } from '@/components/ui/spinner';
import { playReaderSound } from '@/lib/reader-sound';
import { lookup } from '@/routes/scan';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The reader's home: point the camera at a card QR. The server finds the
 * card in the current organization and opens it; anything else comes back
 * as "Card not found."
 */
export default function Scan() {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const { t } = useTranslation();

    function handleDetect(payload: string) {
        if (processing) {
            return;
        }

        setProcessing(true);
        setError(null);
        playReaderSound('scan');

        router.post(
            lookup.url(),
            { payload },
            {
                // Keep this page (and its camera) mounted when the card is
                // not found.
                preserveState: true,
                preserveScroll: true,
                onError: (errors) => {
                    setError(errors.payload ?? t('Card not found.'));
                    playReaderSound('error');
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <>
            <Head title={t('Reader')} />
            <div className="flex flex-1 flex-col gap-4">
                <div>
                    <h1 className="text-xl font-semibold">
                        {t('Scan a card')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t("Hold the card's QR code inside the frame.")}
                    </p>
                </div>

                <div className="relative">
                    <QrScanner
                        onDetect={handleDetect}
                        paused={processing}
                        className="aspect-[3/4] w-full"
                    />
                    {processing && (
                        <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 rounded-xl bg-black/60 text-white">
                            <Spinner className="size-8" />
                            <p className="text-sm font-medium">
                                {t('Looking up card…')}
                            </p>
                        </div>
                    )}
                </div>

                {error && (
                    <Alert variant="destructive" role="alert">
                        <AlertCircle />
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                <div className="grid grid-cols-2 gap-3">
                    <RecentScans />
                    <SoundToggle />
                </div>
            </div>
        </>
    );
}
