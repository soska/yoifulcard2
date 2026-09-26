import type { CardStatus } from './card';

/**
 * Everything the public card page receives. Only these fields: the server
 * selects nothing else (no id, code, token, or email).
 */
export type PublicCard = {
    balance: string;
    status: CardStatus;
};

export type PublicOrganization = {
    name: string;
    logo_url: string | null;
    primary_color: string;
    currency: string;
};
