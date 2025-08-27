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
                Limit::perMinute(20)->by('auth:general:' . $request->ip()),
                // Specific limit per email to prevent targeted attacks
                Limit::perMinute(5)->by('auth:email:' . $email . ':' . $request->ip()),
                // Hourly limit to prevent sustained attacks
                Limit::perHour(50)->by('auth:hourly:' . $request->ip()),
            ];
        });

        // API endpoints for authenticated users
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            
            if ($user) {
                // Higher limits for authenticated users
                return [
                    Limit::perMinute(100)->by('api:user:' . $user->id),
                    Limit::perHour(2000)->by('api:user:hourly:' . $user->id),
                ];
            } else {
                // Lower limits for unauthenticated requests
                return [
                    Limit::perMinute(30)->by('api:guest:' . $request->ip()),
                    Limit::perHour(500)->by('api:guest:hourly:' . $request->ip()),
                ];
            }
        });

        // Public endpoints (read-only, no auth required)
        RateLimiter::for('public', function (Request $request) {
            return [
                Limit::perMinute(60)->by('public:' . $request->ip()),
                Limit::perHour(1000)->by('public:hourly:' . $request->ip()),
            ];
        });

        // Upload endpoints - more restrictive
        RateLimiter::for('uploads', function (Request $request) {
            $user = $request->user();
            $identifier = $user ? 'user:' . $user->id : 'ip:' . $request->ip();
            
            return [
                Limit::perMinute(10)->by('uploads:' . $identifier),
                Limit::perHour(100)->by('uploads:hourly:' . $identifier),
                // Daily limit to prevent abuse
                Limit::perDay(500)->by('uploads:daily:' . $identifier),
            ];
        });

        // Admin endpoints - very restrictive
        RateLimiter::for('admin', function (Request $request) {
            $user = $request->user();
            
            return [
                Limit::perMinute(30)->by('admin:user:' . ($user?->id ?? 'guest')),
                Limit::perHour(300)->by('admin:user:hourly:' . ($user?->id ?? 'guest')),
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
                        $bulkRoles = ['bulk-importer', 'system-integration', 'admin'];
                        foreach ($bulkRoles as $role) {
                            if ($user->hasRole($role)) {
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
                        Limit::perMinute(60)->by('reservations:bulk:' . $user->id),
                        Limit::perHour(500)->by('reservations:bulk:hourly:' . $user->id),
                        Limit::perDay(2000)->by('reservations:bulk:daily:' . $user->id),
                    ];
                } else {
                    // Normal limits for regular users
                    return [
                        Limit::perMinute(30)->by('reservations:user:' . $user->id),
                        Limit::perHour(500)->by('reservations:user:hourly:' . $user->id),
                    ];
                }
            } else {
                // Public reservation queries
                return [
                    Limit::perMinute(20)->by('reservations:guest:' . $request->ip()),
                    Limit::perHour(200)->by('reservations:guest:hourly:' . $request->ip()),
                ];
            }
        });

        // Bulk operations rate limiter (for dedicated bulk endpoints)
        RateLimiter::for('bulk', function (Request $request) {
            $user = $request->user();
            $identifier = $user ? 'user:' . $user->id : 'ip:' . $request->ip();
            
            return [
                Limit::perMinute(100)->by('bulk:' . $identifier),
                Limit::perHour(1000)->by('bulk:hourly:' . $identifier),
                Limit::perDay(5000)->by('bulk:daily:' . $identifier),
            ];
        });
    }
}
