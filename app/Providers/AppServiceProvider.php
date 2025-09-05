<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();
        if (\App::environment('production')) {
          \URL::forceScheme('https');
        }

        // Configure API Rate Limiters
        $this->configureRateLimiters();

        if (config('mail.copiarRemetente'))
            // faz com que todo e qualquer e-mail enviado para os diversos atores seja copiado para o e-mail de envio do sistema
            // desta forma, na caixa de entrada do e-mail de envio do sistema, teremos um histórico de todos os e-mails enviados
            Event::listen(MessageSending::class, function (MessageSending $event) {
                $event->message->addBcc(config('mail.from.address'));
            });
    }

    /**
     * Configure API rate limiters for different endpoint categories
     *
     * @return void
     */
    protected function configureRateLimiters(): void
    {
        // Authentication endpoints - stricter limits
        RateLimiter::for('auth', function (Request $request) {
            $email = $request->input('email', 'unknown');
            return [
                // General limit for auth endpoints
                Limit::perMinute(config('api.rate_limits.auth.general.max_attempts'))
                    ->by('auth:general:' . $request->ip()),
                // Specific limit per email to prevent targeted attacks
                Limit::perMinute(config('api.rate_limits.auth.per_email.max_attempts'))
                    ->by('auth:email:' . $email . ':' . $request->ip()),
                // Hourly limit to prevent sustained attacks
                Limit::perHour(config('api.rate_limits.auth.hourly.max_attempts'))
                    ->by('auth:hourly:' . $request->ip()),
            ];
        });

        // API endpoints for authenticated users
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            
            if ($user) {
                // Higher limits for authenticated users
                return [
                    Limit::perMinute(config('api.rate_limits.api.authenticated.per_minute.max_attempts'))
                        ->by('api:user:' . $user->id),
                    Limit::perHour(config('api.rate_limits.api.authenticated.hourly.max_attempts'))
                        ->by('api:user:hourly:' . $user->id),
                ];
            } else {
                // Lower limits for unauthenticated requests
                return [
                    Limit::perMinute(config('api.rate_limits.api.guest.per_minute.max_attempts'))
                        ->by('api:guest:' . $request->ip()),
                    Limit::perHour(config('api.rate_limits.api.guest.hourly.max_attempts'))
                        ->by('api:guest:hourly:' . $request->ip()),
                ];
            }
        });

        // Public endpoints (read-only, no auth required)
        RateLimiter::for('public', function (Request $request) {
            return [
                Limit::perMinute(config('api.rate_limits.public.per_minute.max_attempts'))
                    ->by('public:' . $request->ip()),
                Limit::perHour(config('api.rate_limits.public.hourly.max_attempts'))
                    ->by('public:hourly:' . $request->ip()),
            ];
        });

        // Upload endpoints - more restrictive
        RateLimiter::for('uploads', function (Request $request) {
            $user = $request->user();
            $identifier = $user ? 'user:' . $user->id : 'ip:' . $request->ip();
            
            return [
                Limit::perMinute(config('api.rate_limits.uploads.per_minute.max_attempts'))
                    ->by('uploads:' . $identifier),
                Limit::perHour(config('api.rate_limits.uploads.hourly.max_attempts'))
                    ->by('uploads:hourly:' . $identifier),
                // Daily limit to prevent abuse
                Limit::perDay(config('api.rate_limits.uploads.daily.max_attempts'))
                    ->by('uploads:daily:' . $identifier),
            ];
        });

        // Admin endpoints - very restrictive
        RateLimiter::for('admin', function (Request $request) {
            $user = $request->user();
            
            return [
                Limit::perMinute(config('api.rate_limits.admin.per_minute.max_attempts'))
                    ->by('admin:user:' . ($user?->id ?? 'guest')),
                Limit::perHour(config('api.rate_limits.admin.hourly.max_attempts'))
                    ->by('admin:user:hourly:' . ($user?->id ?? 'guest')),
            ];
        });

        // Reservation endpoints - balanced limits
        RateLimiter::for('reservations', function (Request $request) {
            $user = $request->user();
            
            if ($user) {
                // Check if user has a special role for bulk operations
                $isBulkUser = false;
                if (method_exists($user, 'hasRole')) {
                    try {
                        $bulkRoles = explode(',', config('api.rate_limits.reservations.bulk_roles'));
                        foreach ($bulkRoles as $role) {
                            if ($user->hasRole(trim($role))) {
                                $isBulkUser = true;
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        // Role check failed, continue with normal limits
                    }
                }
                
                if ($isBulkUser) {
                    // Higher limits for system integration (semester reservations)
                    return [
                        Limit::perMinute(config('api.rate_limits.reservations.bulk_user.per_minute.max_attempts'))
                            ->by('reservations:bulk:' . $user->id),
                        Limit::perHour(config('api.rate_limits.reservations.bulk_user.hourly.max_attempts'))
                            ->by('reservations:bulk:hourly:' . $user->id),
                        Limit::perDay(config('api.rate_limits.reservations.bulk_user.daily.max_attempts'))
                            ->by('reservations:bulk:daily:' . $user->id),
                    ];
                } else {
                    // Normal limits for regular users
                    return [
                        Limit::perMinute(config('api.rate_limits.reservations.regular_user.per_minute.max_attempts'))
                            ->by('reservations:user:' . $user->id),
                        Limit::perHour(config('api.rate_limits.reservations.regular_user.hourly.max_attempts'))
                            ->by('reservations:user:hourly:' . $user->id),
                    ];
                }
            } else {
                // Public reservation queries
                return [
                    Limit::perMinute(config('api.rate_limits.reservations.guest.per_minute.max_attempts'))
                        ->by('reservations:guest:' . $request->ip()),
                    Limit::perHour(config('api.rate_limits.reservations.guest.hourly.max_attempts'))
                        ->by('reservations:guest:hourly:' . $request->ip()),
                ];
            }
        });

        // Bulk operations rate limiter (for dedicated bulk endpoints)
        RateLimiter::for('bulk', function (Request $request) {
            $user = $request->user();
            $identifier = $user ? 'user:' . $user->id : 'ip:' . $request->ip();
            
            return [
                Limit::perMinute(config('api.rate_limits.bulk.per_minute.max_attempts'))
                    ->by('bulk:' . $identifier),
                Limit::perHour(config('api.rate_limits.bulk.hourly.max_attempts'))
                    ->by('bulk:hourly:' . $identifier),
                Limit::perDay(config('api.rate_limits.bulk.daily.max_attempts'))
                    ->by('bulk:daily:' . $identifier),
            ];
        });
    }
}
