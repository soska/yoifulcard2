<?php

namespace App\Providers;

use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\Locales;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /** Email form posts allowed per minute from one IP address. */
    public const PUBLIC_CARD_EMAIL_PER_MINUTE = 5;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->rememberOrganizationOnLogin();
        $this->configureRateLimiting();
    }

    /**
     * The public card's email form: a few tries per minute per address, so a
     * script cannot hammer card links. Over the limit, the form shows an error
     * instead of a bare 429 page.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('public-card-email', fn (Request $request) => Limit::perMinute(self::PUBLIC_CARD_EMAIL_PER_MINUTE)
            ->by((string) $request->ip())
            ->response(fn (Request $request, array $headers) => back()
                ->withErrors(['email' => __('Too many attempts. Please try again in a minute.')])
                ->withHeaders($headers)));
    }

    /**
     * After login, keep the user's first organization in the session.
     */
    protected function rememberOrganizationOnLogin(): void
    {
        Event::listen(function (Login $event): void {
            if ($event->user instanceof User && app()->bound('session')) {
                CurrentOrganization::remember($event->user, app('session')->driver());
            }
        });

        // A language picked on the login or register page (the guest cookie)
        // is a choice the person made, so it becomes their saved language the
        // first time they sign in without one. A saved language is never
        // overwritten.
        Event::listen(function (Login $event): void {
            if (! $event->user instanceof User || $event->user->preferredLocale() !== null || ! app()->bound('request')) {
                return;
            }

            $chosen = Locales::fromCookie(request());

            if ($chosen !== null) {
                $event->user->forceFill(['locale' => $chosen])->save();
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
