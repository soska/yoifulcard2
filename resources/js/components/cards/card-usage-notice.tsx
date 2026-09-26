import { AlertTriangle, Ban } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';
import type { CardUsage } from '@/types';

/**
 * Plan usage against the card limit. Shows a warning at 80% and a blocking
 * message at the limit. Unlimited plans and low usage show nothing.
 */
export function CardUsageNotice({ usage }: { usage: CardUsage }) {
    if (usage.limit === null || (!usage.nearLimit && !usage.atLimit)) {
        return null;
    }

    const progress = (
        <Progress
            value={Math.min(usage.percent ?? 100, 100)}
            className="mt-2"
            aria-label="Cards used"
        />
    );

    if (usage.atLimit) {
        return (
            <Alert variant="destructive">
                <Ban />
                <AlertTitle>Card limit reached</AlertTitle>
                <AlertDescription>
                    <p>
                        You are using {usage.used} of {usage.limit} cards and
                        cannot create more. Contact support to raise the limit.
                    </p>
                    {progress}
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <Alert>
            <AlertTriangle />
            <AlertTitle>Approaching your card limit</AlertTitle>
            <AlertDescription>
                <p>
                    You are using {usage.used} of {usage.limit} cards (
                    {usage.percent}%). Contact support to raise the limit.
                </p>
                {progress}
            </AlertDescription>
        </Alert>
    );
}
