import type { OneTimeCredentials } from '@/types/admin';
import type { Auth } from '@/types/auth';
import type { CurrentOrganization } from '@/types/organization';
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
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: FlashToast;
            /** A generated password, sent in one response only. */
            credentials?: OneTimeCredentials;
        };
    }
}
