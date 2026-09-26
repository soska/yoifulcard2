<?php

use App\Http\Middleware\EnsureOrganizationWritable;
use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveCurrentOrganization;
use App\Http\Middleware\SetLocale;
use App\Models\Superadmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
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

        $middleware->web(append: [
            SetLocale::class,
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

        // Show the Inertia forbidden page for any 403 outside JSON requests.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() !== 403 || $request->expectsJson()) {
                return $response;
            }

            return Inertia::render('errors/forbidden', [
                'canClaimSuperadmin' => $request->user() !== null
                    && $request->is('admin', 'admin/*')
                    && ! Superadmin::query()->exists(),
            ])->toResponse($request)->setStatusCode(403);
        });
    })->create();
