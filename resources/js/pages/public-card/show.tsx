import { Head, usePage } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { CardFace } from '@/components/public-card/card-face';
import { CounterQr } from '@/components/public-card/counter-qr';
import { PreferenceSwitchers } from '@/components/preferences/preference-switchers';
import { EmailCapture } from '@/components/public-card/email-capture';
import { WalletButtons } from '@/components/public-card/wallet-buttons';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Progress } from '@/components/ui/progress';
import type { PublicCard, PublicOrganization, PublicReward } from '@/types';
import { __ } from '@/i18n';

type Props = {
    card: PublicCard;
    /** Whether the business has rewards on: points show only then. */
    rewards_enabled: boolean;
    /** What the points can buy, cheapest first. */
    rewards: PublicReward[];
    organization: PublicOrganization;
};

/** The card's token, taken from the page URL (`/c/{token}`). */
function tokenFromUrl(url: string): string {
    return url.split('?')[0].split('/').filter(Boolean).pop() ?? '';
}

/**
 * The cardholder's page. No login, no app layout. Shows the business
 * branding, the balance, and (while the card can be used) the QR to show at
 * the counter. A card that is not activated yet shows no balance, QR, or
 * email form. A suspended business's card looks the same:
 * the balance belongs to the cardholder.
 */
export default function PublicCardShow({
    card,
    rewards_enabled,
    rewards,
    organization,
}: Props) {
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
                    {card.status === 'inactive' && (
                        <Alert>
                            <AlertCircle />
                            <AlertTitle>{__('Not activated yet')}</AlertTitle>
                            <AlertDescription>
                                {__(
                                    '{business} activates this card when you buy it. Ask them if you already paid for it.',
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

                    {rewards_enabled && card.status !== 'inactive' && (
                        <div className="rounded-xl border bg-card px-4 py-3 text-center">
                            <p className="text-sm text-muted-foreground">
                                {__('Reward points')}
                            </p>
                            <p className="text-3xl font-semibold tabular-nums">
                                {card.points}
                            </p>
                        </div>
                    )}

                    {rewards_enabled &&
                        card.status !== 'inactive' &&
                        rewards.length > 0 && (
                            <section className="flex flex-col gap-3 rounded-xl border bg-card px-4 py-3">
                                <h2 className="text-sm font-medium">
                                    {__('Rewards')}
                                </h2>
                                <ul className="flex flex-col gap-4">
                                    {rewards.map((reward) => {
                                        const ready =
                                            card.points >= reward.points_cost;

                                        return (
                                            <li
                                                key={`${reward.name}-${reward.points_cost}`}
                                                className="flex flex-col gap-1.5"
                                            >
                                                <div className="flex items-baseline justify-between gap-3">
                                                    <span className="font-medium">
                                                        {reward.name}
                                                    </span>
                                                    <span className="text-sm text-muted-foreground tabular-nums">
                                                        {ready
                                                            ? __(
                                                                  'Ready to redeem',
                                                              )
                                                            : __(
                                                                  '{points} of {cost} points',
                                                                  {
                                                                      points: card.points,
                                                                      cost: reward.points_cost,
                                                                  },
                                                              )}
                                                    </span>
                                                </div>
                                                <Progress
                                                    value={Math.min(
                                                        100,
                                                        Math.round(
                                                            (card.points /
                                                                reward.points_cost) *
                                                                100,
                                                        ),
                                                    )}
                                                />
                                            </li>
                                        );
                                    })}
                                </ul>
                            </section>
                        )}

                    {(card.status === 'active' ||
                        card.status === 'depleted') && (
                        <CounterQr token={tokenFromUrl(url)} />
                    )}

                    <WalletButtons />

                    {card.status !== 'inactive' && (
                        <EmailCapture
                            token={tokenFromUrl(url)}
                            hasEmail={card.has_email}
                        />
                    )}

                    <p className="text-center text-sm text-muted-foreground">
                        {__('Powered by Yoiful')}
                    </p>
                </div>
            </main>
        </>
    );
}
