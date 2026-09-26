import { AlertTriangle, Ban } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';
import type { CardUsage } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Plan usage against the card limit. Shows a warning at 80% and a blocking
 * message at the limit. Unlimited plans and low usage show nothing.
 */
export function CardUsageNotice({ usage }: { usage: CardUsage }) {
    const { t } = useTranslation();

    if (usage.limit === null || (!usage.nearLimit && !usage.atLimit)) {
        return null;
    }

    const progress = (
        <Progress
            value={Math.min(usage.percent ?? 100, 100)}
            className="mt-2"
            aria-label={t('Cards used')}
        />
    );

    if (usage.atLimit) {
        return (
            <Alert variant="destructive">
                <Ban />
                <AlertTitle>{t('Card limit reached')}</AlertTitle>
                <AlertDescription>
                    <p>
                        {t(
                            'You are using :used of :limit cards and cannot create more. Contact support to raise the limit.',
                            { used: usage.used, limit: usage.limit },
                        )}
                    </p>
                    {progress}
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <Alert>
            <AlertTriangle />
            <AlertTitle>{t('Approaching your card limit')}</AlertTitle>
            <AlertDescription>
                <p>
                    {t(
                        'You are using :used of :limit cards (:percent%). Contact support to raise the limit.',
                        {
                            used: usage.used,
                            limit: usage.limit,
                            percent: usage.percent ?? 0,
                        },
                    )}
                </p>
                {progress}
            </AlertDescription>
        </Alert>
    );
}
