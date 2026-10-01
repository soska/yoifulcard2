import { QrCode } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { qr } from '@/routes/public-card';
import { __ } from '@/i18n';

/**
 * The card's QR, for the cardholder to show at the counter. The server
 * renders it (`public-card.qr`), so it encodes exactly what the reader
 * expects. The panel stays white in dark mode: scanners need dark modules on
 * a light background.
 */
export function CounterQr({ token }: { token: string }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <QrCode className="size-4" />
                    {__('Pay at the counter')}
                </CardTitle>
                <CardDescription>
                    {__('Show this code to the staff when you pay.')}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex justify-center">
                <img
                    src={qr.url(token)}
                    alt={__('QR code for this card')}
                    width={224}
                    height={224}
                    className="size-56 rounded-lg bg-white p-2"
                />
            </CardContent>
        </Card>
    );
}
