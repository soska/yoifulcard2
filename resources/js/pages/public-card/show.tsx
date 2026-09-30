import { Head, usePage } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { CardFace } from '@/components/public-card/card-face';
import { PreferenceSwitchers } from '@/components/preferences/preference-switchers';
import { EmailCapture } from '@/components/public-card/email-capture';
import { WalletButtons } from '@/components/public-card/wallet-buttons';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import type { PublicCard, PublicOrganization } from '@/types';
import { __ } from '@/i18n';

type Props = {
    card: PublicCard;
    organization: PublicOrganization;
};

/** The card's token, taken from the page URL (`/c/{token}`). */
function tokenFromUrl(url: string): string {
    return url.split('?')[0].split('/').filter(Boolean).pop() ?? '';
}

/**
 * The cardholder's page. No login, no app layout. Shows the business
 * branding and the balance. A suspended business's card looks the same:
 * the balance belongs to the cardholder.
 */
export default function PublicCardShow({ card, organization }: Props) {
    const { url } = usePage();
    const initial = organization.name.trim().charAt(0).toUpperCase();
    return (
        <>
            <Head title={organization.name}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <main className="flex min-h-svh flex-col items-center bg-muted/40 px-4 py-10">
                <div className="flex w-full max-w-md flex-col gap-6">
                    <PreferenceSwitchers className="justify-end" />
                    <header className="flex flex-col items-center gap-3 text-center">
                        <Avatar className="size-16">
                            {organization.logo_url && (
                                <AvatarImage
                                    src={organization.logo_url}
                                    alt={organization.name}
                                    referrerPolicy="no-referrer"
                                />
                            )}
                            <AvatarFallback className="text-xl font-semibold">
                                {initial}
                            </AvatarFallback>
                        </Avatar>
                        <h1 className="text-2xl font-bold">
                            {organization.name}
                        </h1>
                    </header>

                    {card.status === 'frozen' && (
                        <Alert variant="destructive">
                            <AlertCircle />
                            <AlertTitle>{__('Card frozen')}</AlertTitle>
                            <AlertDescription>
                                {__(
                                    'This card is currently frozen and cannot be used. Please contact {business} for assistance.',
                                    { business: organization.name },
                                )}
                            </AlertDescription>
                        </Alert>
                    )}
                    {card.status === 'cancelled' && (
                        <Alert variant="destructive">
                            <AlertCircle />
                            <AlertTitle>{__('Card cancelled')}</AlertTitle>
                            <AlertDescription>
                                {__(
                                    'This card can no longer be used. Please contact {business} for assistance.',
                                    { business: organization.name },
                                )}
                            </AlertDescription>
                        </Alert>
                    )}

                    <CardFace card={card} organization={organization} />

                    <WalletButtons />

                    <EmailCapture
                        token={tokenFromUrl(url)}
                        hasEmail={card.has_email}
                    />

                    <p className="text-center text-sm text-muted-foreground">
                        {__('Powered by Yoiful')}
                    </p>
                </div>
            </main>
        </>
    );
}
