<?php

namespace App\Support;

use App\Http\Middleware\HandleInertiaRequests;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders HTTP error pages (403, 404, 419, 500, 503) as translated Inertia
 * pages from the exception handler.
 *
 * The web middleware may not have run: an unknown URL matches no route,
 * maintenance mode stops the request before routing, and a server error
 * can happen anywhere. So this sets the language and theme itself and
 * shares a fixed set of props that need neither the database nor a
 * started session, replacing whatever the middleware shared (a lazy prop
 * could query a database that is the reason for the error).
 */
class ErrorPage
{
    /** Statuses that get an Inertia page instead of Laravel's default view. */
    public const STATUSES = [403, 404, 419, 500, 503];

    public static function handles(Request $request, Response $response): bool
    {
        $status = $response->getStatusCode();

        if (! in_array($status, self::STATUSES, true) || $request->is('api/*') || $request->expectsJson()) {
            return false;
        }

        // Keep Laravel's debug page for server errors while developing.
        return ! ($status === 500 && config('app.debug'));
    }

    /**
     * @param  array<string, mixed>  $props
     */
    public static function render(Request $request, string $component, int $status, array $props = []): Response
    {
        $locale = Locale::fromRequest($request);
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        View::share('appearance', Theme::fromRequest($request));

        $middleware = app(HandleInertiaRequests::class);
        Inertia::version(fn () => $middleware->version($request));
        Inertia::setRootView($middleware->rootView($request));

        Inertia::flushShared();
        Inertia::share(self::sharedProps($request, $locale));

        return Inertia::render($component, $props)
            ->toResponse($request)
            ->setStatusCode($status);
    }

    /**
     * The same keys HandleInertiaRequests shares, without database lookups.
     *
     * @return array<string, mixed>
     */
    private static function sharedProps(Request $request, string $locale): array
    {
        $user = rescue(fn () => $request->user(), null, false);

        return [
            'errors' => (object) [],
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'isSuperadmin' => false,
            ],
            'currentOrganization' => null,
            'locale' => $locale,
            'intlLocale' => Locale::intl($locale),
            'theme' => Theme::fromRequest($request),
            'translations' => Translations::for($locale),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
