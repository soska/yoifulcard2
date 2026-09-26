import { Wallet } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Wallet buttons, shown so cardholders know they are coming. They are
 * disabled and generate no pass until the wallet release.
 */
export function WalletButtons() {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col gap-3">
            {['Add to Apple Wallet', 'Add to Google Wallet'].map((label) => (
                <Button
                    key={label}
                    type="button"
                    variant="outline"
                    size="lg"
                    className="h-12 justify-between"
                    disabled
                >
                    <span className="flex items-center gap-2">
                        <Wallet data-icon="inline-start" />
                        {t(label)}
                    </span>
                    <Badge variant="secondary">{t('Coming soon')}</Badge>
                </Button>
            ))}
        </div>
    );
}
