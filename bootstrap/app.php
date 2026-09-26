<?php

use App\Http\Middleware\EnsureOrganizationWritable;
use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveCurrentOrganization;
use App\Http\Middleware\ResolveLocale;
use App\Models\Superadmin;
use App\Support\ErrorPage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Read by the browser too (theme switcher, first paint), and validated
        // against a fixed list wherever the server reads them.
        $middleware->encryptCookies(except: ['theme', 'locale', 'sidebar_state']);

        // ResolveLocale is the FIRST appended middleware on purpose: after
        // StartSession (it needs $request->user() for users.locale) and before
        // HandleInertiaRequests (whose shared props call __()). See its docblock.
        $middleware->web(append: [
            ResolveLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'organization' => ResolveCurrentOrganization::class,
            'organization.writable' => EnsureOrganizationWritable::class,
            'superadmin' => EnsureSuperadmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Translated Inertia pages for 403, 404, 419, 500 and 503 outside JSON
        // requests (500 keeps Laravel's debug page while APP_DEBUG is on).
        // The public card's not-found page is returned by its controller and
        // never reaches this.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! ErrorPage::handles($request, $response)) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status === 403) {
                return ErrorPage::render($request, 'errors/forbidden', 403, [
                    'canClaimSuperadmin' => rescue(
                        fn () => $request->user() !== null
                            && $request->is('admin', 'admin/*')
                            && ! Superadmin::query()->exists(),
                        false,
                        false,
                    ),
                ]);
            }

            return ErrorPage::render($request, 'errors/error', $status, ['status' => $status]);
        });
    })->create();
