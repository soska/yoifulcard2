import { usePage } from '@inertiajs/react';
import { Check, Copy, KeyRound } from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { __ } from '@/i18n';

/**
 * Shows a generated password that the server flashed for this one
 * response. It is not in the page props or browser history, so it is gone
 * after the next visit or a reload.
 */
export function OneTimeCredentials() {
    const { flash } = usePage();
    const credentials = flash.credentials;
    const [dismissed, setDismissed] = useState<string | null>(null);
    const [copied, copy] = useClipboard();
    if (!credentials || dismissed === credentials.password) {
        return null;
    }

    // One whole line per field, so a translation can reorder label and value.
    const text = [
        __('Email: {email}', { email: credentials.email }),
        __('Password: {password}', { password: credentials.password }),
    ].join('\n');

    return (
        <Alert>
            <KeyRound />
            <AlertTitle>{__('Save this password now')}</AlertTitle>
            <AlertDescription className="flex flex-col gap-3">
                <p>
                    {__(
                        'It is shown only once. Share it with the user over a safe channel and ask them to change it in their settings after signing in.',
                    )}
                </p>
                <dl className="grid gap-2 sm:grid-cols-[auto_1fr] sm:gap-x-4">
                    <dt className="text-muted-foreground">{__('Email')}</dt>
                    <dd className="font-mono break-all text-foreground">
                        {credentials.email}
                    </dd>
                    <dt className="text-muted-foreground">
                        {__('Temporary password')}
                    </dt>
                    <dd
                        className="font-mono break-all text-foreground"
                        data-test="one-time-password"
                    >
                        {credentials.password}
                    </dd>
                </dl>
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => void copy(text)}
                    >
                        {copied === text ? (
                            <Check data-icon="inline-start" />
                        ) : (
                            <Copy data-icon="inline-start" />
                        )}
                        {copied === text
                            ? __('Copied')
                            : __('Copy credentials')}
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setDismissed(credentials.password)}
                    >
                        {__('Done')}
                    </Button>
                </div>
            </AlertDescription>
        </Alert>
    );
}
