import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/lib/flash';
import { flashMessage } from '@/lib/flash';

/**
 * Shows the server's flashed toast. The server sends a code
 * (App\Enums\FlashMessage); lib/flash.ts turns it into words.
 */
export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;
            const message = flashMessage(data);

            if (!data || message === null) {
                return;
            }

            toast[data.type](message);
        });
    }, []);
}
