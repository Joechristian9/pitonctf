<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
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
        $this->configureRateLimiting();

        Gate::define('admin', fn (User $user): bool => $user->isAdmin());
    }

    /**
     * Limit flag guessing. Over the limit, the student sees a message on the card instead of an error page.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('flag-submissions', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->user()->id)
            ->response(function (Request $request): RedirectResponse {
                // The throttle runs before route model binding, so this is still the raw id from the URL.
                Inertia::flash('flagResult', [
                    'challenge_id' => (int) $request->route('challenge'),
                    'ok' => false,
                    'message' => 'Too many tries. Take a breath and try again in a minute.',
                ]);

                return back();
            }));

        // A team opens a hint once per challenge and uploads a write-up now and then, so these limits only stop scripts.
        RateLimiter::for('hints', fn (Request $request): Limit => Limit::perMinute(10)->by($request->user()->id)->response($this->slowDown(...)));
        RateLimiter::for('writeups', fn (Request $request): Limit => Limit::perMinute(5)->by($request->user()->id)->response($this->slowDown(...)));
    }

    private function slowDown(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => 'Too many requests. Try again in a minute.']);

        return back();
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
