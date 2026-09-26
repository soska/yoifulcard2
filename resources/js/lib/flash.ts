import { __ } from '@/i18n';
import type { FlashMessage } from '@/types/enums.generated';

/**
 * The words behind every flash code (App\Enums\FlashMessage).
 *
 * The server says WHAT HAPPENED; this file says it, in the same catalog as the
 * rest of the UI. The table is a `Record<FlashMessage, …>` over the union
 * generated from the PHP enum, so it is EXHAUSTIVE: add a case in PHP, run
 * `php artisan types:enums`, and `npm run types:check` fails here until the
 * sentence exists.
 *
 * Every entry is a FUNCTION: `__()` at module scope would run before the
 * catalog loads (freezing English), and the ones with placeholders need the
 * params anyway. TS placeholders are `{name}`, not Laravel's `:name`.
 */
type FlashParams = Record<string, string>;

/** The toast as the server flashes it (App\Support\Flash). */
export type FlashToast = {
    type: 'success' | 'error';
    code: FlashMessage;
    params: FlashParams;
};

function messages(): Record<FlashMessage, (params: FlashParams) => string> {
    return {
        'card.created': (p) =>
            __('Card {code} created.', { code: p.code ?? '' }),
        'card.frozen': () => __('Card frozen.'),
        'card.unfrozen': () => __('Card unfrozen.'),
        'card.email_saved': () => __('Cardholder email saved.'),
        'ledger.loaded': (p) =>
            __('Funds added to {code}.', { code: p.code ?? '' }),
        'ledger.charged': (p) => __('Charged {code}.', { code: p.code ?? '' }),
        'ledger.adjusted': (p) =>
            __('Balance of {code} adjusted.', { code: p.code ?? '' }),
        'settings.business_saved': () => __('Business settings saved.'),
        'settings.program_saved': () => __('Program settings saved.'),
        'settings.profile_updated': () => __('Profile updated.'),
        'settings.password_updated': () => __('Password updated.'),
        'public_card.email_saved': () => __('Thanks! Your email is saved.'),
        'organization.switched': (p) =>
            __('Switched to {name}.', { name: p.name ?? '' }),
        'organization.not_writable': () =>
            __('This business is suspended. Contact support.'),
        'admin.organization_created_new_owner': () =>
            __('Organization created with a new owner account.'),
        'admin.organization_created_existing_owner': (p) =>
            __('Organization created and linked to {email}.', {
                email: p.email ?? '',
            }),
        'admin.plan_saved': () => __('Plan saved.'),
        'admin.organization_suspended': () => __('Organization suspended.'),
        'admin.organization_reactivated': () => __('Organization reactivated.'),
        'admin.temporary_password_set': (p) =>
            __('Temporary password set for {email}.', {
                email: p.email ?? '',
            }),
        'admin.superadmin_claimed': () => __('Superadmin privileges granted.'),
        'admin.superadmin_granted': (p) =>
            __('Superadmin granted to {email}.', { email: p.email ?? '' }),
        'admin.superadmin_revoked': (p) =>
            __('Superadmin revoked from {email}.', { email: p.email ?? '' }),
    };
}

/**
 * The sentence for a flashed code, or null when there is nothing to say.
 *
 * Null rather than a throw on an unknown code: a session outlives a deploy, so
 * someone can be redirected by the old build and land on the new one holding
 * a code that no longer exists. Missing one toast beats breaking the page.
 */
export function flashMessage(
    flash: Pick<FlashToast, 'code' | 'params'> | null | undefined,
): string | null {
    if (!flash) {
        return null;
    }

    const render = messages()[flash.code] as
        | ((params: FlashParams) => string)
        | undefined;

    return render === undefined ? null : render(flash.params ?? {});
}
