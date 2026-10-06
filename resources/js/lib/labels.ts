import { __ } from '@/i18n';
import type { Appearance } from '@/hooks/use-appearance';
import type {
    CardBatchPdfAction,
    CardStatus,
    CardTemplate,
    MembershipRole,
    OrganizationStatus,
    TransactionType,
} from '@/types/enums.generated';

/**
 * What the app's closed vocabularies are CALLED.
 *
 * The server sends the enum VALUE; this file turns it into words. Each table is
 * a `Record` over the union generated from the PHP enum
 * (`php artisan types:enums`), so it is EXHAUSTIVE: add a case in PHP,
 * regenerate, and `npm run types:check` fails until the word exists here.
 *
 * Every table is built inside its function, at render time: `__()` at module
 * scope would run before the catalog loads and freeze English into the bundle.
 *
 * Every label carries a `context`, because the same English word is a
 * different Spanish word depending on what it describes ("Active" is a card,
 * a business; "Charge" is a verb on a button and a noun here).
 */

/** A card's status, as its badge and the status filter say it. */
export function cardStatusLabel(status: CardStatus): string {
    const labels: Record<CardStatus, string> = {
        inactive: __('Inactive', { context: 'card status' }),
        active: __('Active', { context: 'card status' }),
        frozen: __('Frozen', { context: 'card status' }),
        depleted: __('Depleted', { context: 'card status' }),
        cancelled: __('Cancelled', { context: 'card status' }),
    };

    return labels[status];
}

/** A layout a card batch is printed with (App\Enums\CardTemplate). */
export function cardTemplateLabel(template: CardTemplate): string {
    const labels: Record<CardTemplate, string> = {
        sheet_letter: __('Sheet of 10, Letter', { context: 'card template' }),
        sheet_a4: __('Sheet of 10, A4', { context: 'card template' }),
        print_shop: __('Print shop, one card per page', {
            context: 'card template',
        }),
    };

    return labels[template];
}

/** What happened to a batch PDF, as its log says it. */
export function cardBatchPdfActionLabel(action: CardBatchPdfAction): string {
    const labels: Record<CardBatchPdfAction, string> = {
        generated: __('Made', { context: 'batch PDF log action' }),
        downloaded: __('Downloaded', { context: 'batch PDF log action' }),
    };

    return labels[action];
}

/** A ledger entry's kind. Nouns: "Charge" here is the entry, not the button. */
export function transactionTypeLabel(type: TransactionType): string {
    const labels: Record<TransactionType, string> = {
        load: __('Load', { context: 'transaction type' }),
        spend: __('Charge', { context: 'transaction type' }),
        adjustment: __('Adjustment', { context: 'transaction type' }),
        refund: __('Refund', { context: 'transaction type' }),
    };

    return labels[type];
}

/** A member's role in a business. */
export function roleLabel(role: MembershipRole): string {
    const labels: Record<MembershipRole, string> = {
        owner: __('Owner', { context: 'membership role' }),
        manager: __('Manager', { context: 'membership role' }),
        employee: __('Employee', { context: 'membership role' }),
    };

    return labels[role];
}

/** A business's standing, as the admin area says it. */
export function organizationStatusLabel(status: OrganizationStatus): string {
    const labels: Record<OrganizationStatus, string> = {
        active: __('Active', { context: 'organization status' }),
        suspended: __('Suspended', { context: 'organization status' }),
        cancelled: __('Cancelled', { context: 'organization status' }),
    };

    return labels[status];
}

/** A theme choice, as the theme switcher and the appearance settings say it. */
export function appearanceLabel(appearance: Appearance): string {
    const labels: Record<Appearance, string> = {
        light: __('Light', { context: 'theme' }),
        dark: __('Dark', { context: 'theme' }),
        system: __('System', { context: 'theme' }),
    };

    return labels[appearance];
}
