import type { CardStatus } from './card';

/**
 * Everything the public card page receives. Only these fields: the server
 * selects nothing else (no id, code, token, or email).
 */
export type PublicCard = {
    balance: string;
    points: number;
    status: CardStatus;
    has_email: boolean;
};

/** What a card's points can buy, as the public page shows it. */
export type PublicReward = {
    name: string;
    points_cost: number;
};

export type PublicOrganization = {
    name: string;
    logo_url: string | null;
    primary_color: string;
    currency: string;
};
