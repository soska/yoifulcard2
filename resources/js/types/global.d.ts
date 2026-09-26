import type { Auth } from '@/types/auth';
import type { CurrentOrganization } from '@/types/organization';

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
    }
}
