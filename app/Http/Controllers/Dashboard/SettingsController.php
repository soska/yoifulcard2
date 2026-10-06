<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\FlashMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateOrganizationSettingsRequest;
use App\Http\Requests\Settings\UpdateProgramSettingsRequest;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use App\Support\Flash;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Business settings: the organization's branding, currency and timezone, the
 * default program, and plan usage. Every member can read them; owners and
 * managers can change them while the organization is active.
 */
class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $organization = $this->organization($request);
        $program = $organization->defaultProgram();

        return Inertia::render('settings/business', [
            'organization' => [
                'name' => $organization->name,
                'logo_url' => $organization->logo_url,
                'primary_color' => $organization->primary_color,
                'currency' => $organization->currency,
                'timezone' => $organization->timezone,
            ],
            'program' => $program === null ? null : [
                'name' => $program->name,
                'terms_url' => $program->terms_url,
            ],
            'usage' => $organization->cardUsage(),
            'canEdit' => Gate::allows('update', $organization),
            'currencies' => array_values(array_unique([...Organization::CURRENCIES, $organization->currency])),
            'timezones' => DateTimeZone::listIdentifiers(DateTimeZone::ALL),
        ]);
    }

    /**
     * Save name, brand color, currency, timezone, and the logo. A new logo
     * replaces the old file on the public disk.
     */
    public function updateOrganization(UpdateOrganizationSettingsRequest $request): RedirectResponse
    {
        $organization = $this->organization($request);

        $attributes = $request->safe()->only(['name', 'primary_color', 'currency', 'timezone']);
        $previousLogo = $organization->logo_url;

        $logo = $request->file('logo');

        if ($logo instanceof UploadedFile) {
            $attributes['logo_url'] = $this->storeLogo($organization, $logo);
        } elseif ($request->removeLogo()) {
            $attributes['logo_url'] = null;
        }

        $organization->update($attributes);

        if (array_key_exists('logo_url', $attributes) && $previousLogo !== $attributes['logo_url']) {
            $this->deleteLogo($previousLogo);
        }

        Flash::success(FlashMessage::BusinessSettingsSaved);

        return to_route('settings.edit');
    }

    /**
     * Save the default program's name and terms URL.
     */
    public function updateProgram(UpdateProgramSettingsRequest $request): RedirectResponse
    {
        $program = $this->organization($request)->defaultProgram();

        if ($program === null) {
            throw ValidationException::withMessages([
                'program' => __('This business has no active program to issue cards from.'),
            ]);
        }

        $program->update($request->safe()->only(['name', 'terms_url']));

        Flash::success(FlashMessage::ProgramSettingsSaved);

        return to_route('settings.edit');
    }

    /**
     * Store the file under a random name and return its public URL.
     */
    private function storeLogo(Organization $organization, UploadedFile $file): string
    {
        $disk = Storage::disk('public');
        $extension = $file->guessExtension() ?? 'png';
        $path = $file->storeAs(
            Organization::LOGO_DIRECTORY.'/'.$organization->id,
            Str::random(40).'.'.$extension,
            'public',
        );

        abort_if($path === false, 500, __('The logo could not be stored.'));

        return $disk->url($path);
    }

    /**
     * Delete a logo this app stored. URLs pointing anywhere else are left
     * alone.
     */
    private function deleteLogo(?string $url): void
    {
        $path = Organization::storedLogoPath($url);

        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }

    private function organization(Request $request): Organization
    {
        $organization = CurrentOrganization::organization($request);

        abort_if($organization === null, 403);

        return $organization;
    }
}
