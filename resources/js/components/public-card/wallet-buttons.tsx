import { Wallet } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { __ } from '@/i18n';

/**
 * Wallet buttons, shown so cardholders know they are coming. They are
 * disabled and generate no pass until the wallet release.
 */
export function WalletButtons() {
    return (
        <div className="flex flex-col gap-3">
            {[__('Add to Apple Wallet'), __('Add to Google Wallet')].map(
                (label) => (
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
                            {label}
                        </span>
                        <Badge variant="secondary">{__('Coming soon')}</Badge>
                    </Button>
                ),
            )}
        </div>
    );
}
