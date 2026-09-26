<?php

namespace App\Enums;

/**
 * Every toast the app can show, as a CODE rather than a sentence.
 *
 * The server says WHAT HAPPENED; resources/js/lib/flash.ts says it in words,
 * so toast copy lives in the same duckalization catalog as the rest of the
 * screen. This enum is exported to TypeScript as a string union
 * (`php artisan types:enums` -> resources/js/types/enums.generated.ts), and
 * flash.ts keys an EXHAUSTIVE Record off it: add a case here, regenerate, and
 * `npm run types:check` fails until the sentence exists.
 *
 * Params are plain strings (see App\Support\Flash). The doc comment on each
 * case is the English sentence and its params.
 */
enum FlashMessage: string
{
    /** "Card {code} created." — params: code */
    case CardCreated = 'card.created';

    /** "Card frozen." */
    case CardFrozen = 'card.frozen';

    /** "Card unfrozen." */
    case CardUnfrozen = 'card.unfrozen';

    /** "Cardholder email saved." */
    case CardEmailSaved = 'card.email_saved';

    /** "Funds added to {code}." — params: code */
    case FundsAdded = 'ledger.loaded';

    /** "Charged {code}." — params: code */
    case CardCharged = 'ledger.charged';

    /** "Balance of {code} adjusted." — params: code */
    case BalanceAdjusted = 'ledger.adjusted';

    /** "Business settings saved." */
    case BusinessSettingsSaved = 'settings.business_saved';

    /** "Program settings saved." */
    case ProgramSettingsSaved = 'settings.program_saved';

    /** "Profile updated." */
    case ProfileUpdated = 'settings.profile_updated';

    /** "Password updated." */
    case PasswordUpdated = 'settings.password_updated';

    /** "Thanks! Your email is saved." — the public card's email capture. */
    case CardholderEmailSaved = 'public_card.email_saved';

    /** "Switched to {name}." — params: name */
    case OrganizationSwitched = 'organization.switched';

    /** "This business is suspended. Contact support." — an error toast. */
    case OrganizationNotWritable = 'organization.not_writable';

    /** "Organization created with a new owner account." */
    case OrganizationCreatedWithNewOwner = 'admin.organization_created_new_owner';

    /** "Organization created and linked to {email}." — params: email */
    case OrganizationCreatedForExistingOwner = 'admin.organization_created_existing_owner';

    /** "Plan saved." */
    case PlanSaved = 'admin.plan_saved';

    /** "Organization suspended." */
    case OrganizationSuspended = 'admin.organization_suspended';

    /** "Organization reactivated." */
    case OrganizationReactivated = 'admin.organization_reactivated';

    /** "Temporary password set for {email}." — params: email */
    case TemporaryPasswordSet = 'admin.temporary_password_set';

    /** "Superadmin privileges granted." — the first superadmin claimed the role. */
    case SuperadminClaimed = 'admin.superadmin_claimed';

    /** "Superadmin granted to {email}." — params: email */
    case SuperadminGranted = 'admin.superadmin_granted';

    /** "Superadmin revoked from {email}." — params: email */
    case SuperadminRevoked = 'admin.superadmin_revoked';
}
