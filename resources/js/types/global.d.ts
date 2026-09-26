import type { OneTimeCredentials } from '@/types/admin';
import type { Auth } from '@/types/auth';
import type {
    CurrentOrganization,
    SwitchableOrganization,
} from '@/types/organization';
import type { FlashToast } from '@/lib/flash';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            currentOrganization: CurrentOrganization | null;
            /** The user's businesses; empty unless there are two or more. */
            organizations: SwitchableOrganization[];
            sidebarOpen: boolean;
            /**
             * The language the server resolved for this request (see
             * App\Support\Locales): `current` is what to render, `available`
             * the switcher's choices, `intl` the Intl locale (`es-MX`).
             */
            locale: {
                current: string;
                available: string[];
                intl: string;
            };
            /** The `theme` cookie. */
            theme: 'light' | 'dark' | 'system';
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: FlashToast;
            /** A generated password, sent in one response only. */
            credentials?: OneTimeCredentials;
        };
    }
}
