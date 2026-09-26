import type { OneTimeCredentials } from '@/types/admin';
import type { Auth } from '@/types/auth';
import type {
    CurrentOrganization,
    SwitchableOrganization,
} from '@/types/organization';
import type { Translations } from '@/lib/i18n';
import type { FlashToast } from '@/types/ui';

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
            /** App locale from the `locale` cookie. */
            locale: 'en' | 'es';
            /** Intl locale for dates and money, `en-US` or `es-MX`. */
            intlLocale: string;
            /** The `theme` cookie. */
            theme: 'light' | 'dark' | 'system';
            /** JSON lines for `locale` whose text differs from the key. */
            translations: Translations;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: FlashToast;
            /** A generated password, sent in one response only. */
            credentials?: OneTimeCredentials;
        };
    }
}
