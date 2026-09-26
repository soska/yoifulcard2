<?php

namespace App\Http\Middleware;

use App\Support\CurrentOrganization;
use App\Support\Locale;
use App\Support\Theme;
use App\Support\Translations;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'isSuperadmin' => fn () => $request->user()?->isSuperadmin() === true,
            ],
            'currentOrganization' => fn () => CurrentOrganization::toProp($request),
            'organizations' => fn () => CurrentOrganization::switchable($request),
            'locale' => $locale,
            'intlLocale' => Locale::intl($locale),
            'theme' => Theme::fromRequest($request),
            // Sent once per language (and per change to the files); the
            // browser keeps it across visits.
            'translations' => Inertia::once(fn () => Translations::for($locale))
                ->as('translations:'.$locale.':'.Translations::version($locale)),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
