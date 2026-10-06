import { Form, usePage } from '@inertiajs/react';
import { Copy, Download, Mail, Maximize2, Share2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { suspendedMessage } from '@/components/organization/suspended-banner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { FieldError } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { link } from '@/routes/cards';
import { email as emailLink } from '@/routes/cards/link';
import { png, svg } from '@/routes/cards/qr';
import type { CardDetail } from '@/types';
import { __ } from '@/i18n';

/**
 * The card's public link, fetched from its own members-only route: the token
 * never travels in page props. Null until it loads, or if it fails.
 */
function usePublicLink(cardId: string): string | null {
    const [url, setUrl] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();

        fetch(link.url(cardId), {
            credentials: 'same-origin',
            headers: { Accept: 'text/plain' },
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.text() : null))
            .then((text) => setUrl(text?.trim() || null))
            .catch(() => {});

        return () => controller.abort();
    }, [cardId]);

    return url;
}

/**
 * Everything staff need to hand a card to its customer: show the QR for the
 * customer's phone camera, copy or share the link, email it, or download the
 * QR to print.
 */
export function CardHandoff({
    card,
    writable,
}: {
    card: CardDetail;
    writable: boolean;
}) {
    const { currentOrganization } = usePage().props;
    const url = usePublicLink(card.id);
    const canShare =
        typeof navigator !== 'undefined' &&
        typeof navigator.share === 'function';
    const qrAlt = __('QR code for card {code}', { code: card.code });

    async function copy() {
        if (url === null) {
            return;
        }

        try {
            await navigator.clipboard.writeText(url);
            toast.success(__('Link copied.'));
        } catch {
            toast.error(__('The link could not be copied.'));
        }
    }

    async function share() {
        if (url === null) {
            return;
        }

        try {
            await navigator.share({
                title: __('Your {business} gift card', {
                    business: currentOrganization?.name ?? '',
                }),
                url,
            });
        } catch {
            // Closing the share sheet rejects too; there is nothing to say.
        }
    }

    const emailBlocked =
        !writable ||
        card.email === null ||
        card.status === 'cancelled' ||
        card.status === 'inactive';

    return (
        <Card className="h-fit">
            <CardHeader>
                <CardTitle>{__('Give to customer')}</CardTitle>
                <CardDescription>
                    {__(
                        'The customer opens the card on their phone. No app or account needed.',
                    )}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <Dialog>
                    <DialogTrigger
                        render={
                            <button
                                type="button"
                                className="mx-auto rounded-lg border bg-white p-2 focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            />
                        }
                    >
                        <img
                            src={svg.url(card)}
                            alt={qrAlt}
                            width={240}
                            height={240}
                            className="size-60"
                        />
                    </DialogTrigger>
                    <DialogTrigger render={<Button />}>
                        <Maximize2 data-icon="inline-start" />
                        {__('Show QR to customer')}
                    </DialogTrigger>
                    <DialogContent className="flex flex-col items-center gap-4 sm:max-w-lg">
                        <DialogTitle className="text-center text-xl">
                            {__('Scan to open your card')}
                        </DialogTitle>
                        <DialogDescription className="text-center">
                            {__(
                                "Point your phone's camera at the code and tap the link.",
                            )}
                        </DialogDescription>
                        <div className="w-full max-w-sm rounded-xl bg-white p-3">
                            <img
                                src={svg.url(card)}
                                alt={qrAlt}
                                width={480}
                                height={480}
                                className="aspect-square w-full"
                            />
                        </div>
                        <p className="font-mono text-sm text-muted-foreground">
                            {card.code}
                        </p>
                    </DialogContent>
                </Dialog>

                <div className="grid grid-cols-2 gap-2">
                    <Button
                        variant="outline"
                        onClick={copy}
                        disabled={url === null}
                    >
                        <Copy data-icon="inline-start" />
                        {__('Copy link')}
                    </Button>
                    {canShare ? (
                        <Button
                            variant="outline"
                            onClick={share}
                            disabled={url === null}
                        >
                            <Share2 data-icon="inline-start" />
                            {__('Share')}
                        </Button>
                    ) : (
                        <Button
                            variant="outline"
                            nativeButton={false}
                            render={
                                <a
                                    href={png.url(card)}
                                    download={`${card.code}.png`}
                                />
                            }
                        >
                            <Download data-icon="inline-start" />
                            {__('Download PNG')}
                        </Button>
                    )}
                </div>

                <Form
                    {...emailLink.form(card)}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing || emailBlocked}
                                title={
                                    writable ? undefined : suspendedMessage()
                                }
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <Mail data-icon="inline-start" />
                                )}
                                {__('Email link')}
                            </Button>
                            <p className="text-sm text-muted-foreground">
                                {card.status === 'inactive'
                                    ? __(
                                          'Activate this card before sending it.',
                                      )
                                    : card.email === null
                                      ? __(
                                            'Save a cardholder email to send the link.',
                                        )
                                      : __('Sends to {email}.', {
                                            email: card.email,
                                        })}
                            </p>
                            <FieldError>{errors.link}</FieldError>
                        </>
                    )}
                </Form>

                {canShare && (
                    <Button
                        variant="ghost"
                        size="sm"
                        nativeButton={false}
                        render={
                            <a
                                href={png.url(card)}
                                download={`${card.code}.png`}
                            />
                        }
                    >
                        <Download data-icon="inline-start" />
                        {__('Download PNG')}
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}
