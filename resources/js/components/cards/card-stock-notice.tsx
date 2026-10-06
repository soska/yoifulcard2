import { PackageOpen } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { CardUsage } from '@/types';
import { __ } from '@/i18n';

/**
 * Preissued cards count toward the card limit only once they are activated,
 * so activation can be refused at the limit. Warns when there are more cards
 * in stock than room left under the limit. Unlimited plans, and stock that
 * fits, show nothing.
 */
export function CardStockNotice({ usage }: { usage: CardUsage }) {
    if (usage.limit === null) {
        return null;
    }

    const room = Math.max(usage.limit - usage.used, 0);

    if (usage.stock <= room) {
        return null;
    }

    return (
        <Alert>
            <PackageOpen />
            <AlertTitle>{__('More cards in stock than room left')}</AlertTitle>
            <AlertDescription>
                {__(
                    {
                        one: 'You have {count} card in stock, but your plan has room to activate {room} more. Contact support to raise the limit.',
                        other: 'You have {count} cards in stock, but your plan has room to activate {room} more. Contact support to raise the limit.',
                    },
                    { count: usage.stock, room },
                )}
            </AlertDescription>
        </Alert>
    );
}
