<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $base = rtrim(config('app.url'), '/');
            $email = urlencode($notifiable->getEmailForPasswordReset());

            return "{$base}/reset-password?token={$token}&email={$email}";
        });

        VerifyEmail::createUrlUsing(function (object $notifiable) {
            $signed = URL::temporarySignedRoute(
                'api.verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
            $query = parse_url($signed, PHP_URL_QUERY);

            return rtrim(config('app.url'), '/').'/verify-email?'.$query;
        });
    }
}
