<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use App\Http\Traits\ApiResponseTrait;

class ApiRateLimitMiddleware
{
    use ApiResponseTrait;

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $limiterName
     * @param int|null $maxAttempts
     * @param int|null $decaySeconds
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $limiterName = 'api', int $maxAttempts = null, int $decaySeconds = null)
    {
        // Get the rate limiting key based on request context
        $key = $this->resolveRateLimitKey($request, $limiterName);
        
        // Use defined rate limiter or fallback to parameters
        if (RateLimiter::limiter($limiterName)) {
            $limiter = RateLimiter::for($limiterName);
        } else {
            // Fallback to manual rate limiting if limiter not defined
            $maxAttempts = $maxAttempts ?: 60;
            $decaySeconds = $decaySeconds ?: 60;
            
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                return $this->buildRateLimitResponse($request, $key, $maxAttempts);
            }
            
            RateLimiter::increment($key, $decaySeconds);
            
            $response = $next($request);
            
            return $this->addRateLimitHeaders($response, $key, $maxAttempts);
        }

        // Check if rate limit exceeded using RateLimiter::attempt
        $executed = RateLimiter::attempt(
            $key,
            $this->getMaxAttempts($request, $limiterName),
            function() use ($request, $next) {
                return $next($request);
            },
            $this->getDecayTime($request, $limiterName)
        );

        if (!$executed) {
            return $this->buildRateLimitResponse($request, $key, $this->getMaxAttempts($request, $limiterName));
        }

        return $executed;
    }

    /**
     * Resolve the rate limiting key for the given request
     *
     * @param Request $request
     * @param string $limiterName
     * @return string
     */
    protected function resolveRateLimitKey(Request $request, string $limiterName): string
    {
        $user = $request->user();
        
        // Key generation strategy based on limiter name
        switch ($limiterName) {
            case 'auth':
                // For authentication, use email or IP
                $email = $request->input('email', 'unknown');
                return "auth:{$email}:{$request->ip()}";
                
            case 'api':
                // For API, prefer user ID, fallback to IP
                return $user 
                    ? "api:user:{$user->id}" 
                    : "api:ip:{$request->ip()}";
                    
            case 'public':
                // For public endpoints, use IP only
                return "public:ip:{$request->ip()}";
                
            case 'uploads':
                // For uploads, use user ID or IP with endpoint
                $identifier = $user ? "user:{$user->id}" : "ip:{$request->ip()}";
                return "uploads:{$identifier}";
                
            default:
                // Generic key generation
                $identifier = $user ? "user:{$user->id}" : "ip:{$request->ip()}";
                return "{$limiterName}:{$identifier}";
        }
    }

    /**
     * Get max attempts for the given limiter
     *
     * @param Request $request
     * @param string $limiterName
     * @return int
     */
    protected function getMaxAttempts(Request $request, string $limiterName): int
    {
        switch ($limiterName) {
            case 'auth':
                return 10; // 10 auth attempts per minute
            case 'api':
                return $request->user() ? 100 : 30; // 100 for authenticated, 30 for anonymous
            case 'public':
                return 30; // 30 requests per minute for public endpoints
            case 'uploads':
                return 20; // 20 upload requests per minute
            default:
                return 60; // Default fallback
        }
    }

    /**
     * Get decay time in seconds for the given limiter
     *
     * @param Request $request
     * @param string $limiterName
     * @return int
     */
    protected function getDecayTime(Request $request, string $limiterName): int
    {
        switch ($limiterName) {
            case 'auth':
                return 300; // 5 minutes for auth attempts
            case 'api':
            case 'public':
            case 'uploads':
            default:
                return 60; // 1 minute default
        }
    }

    /**
     * Build the rate limit exceeded response
     *
     * @param Request $request
     * @param string $key
     * @param int $maxAttempts
     * @return \Illuminate\Http\JsonResponse
     */
    protected function buildRateLimitResponse(Request $request, string $key, int $maxAttempts)
    {
        $retryAfter = RateLimiter::availableIn($key);
        
        // Log rate limit hit
        Log::warning('Rate limit exceeded', [
            'key' => $key,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'endpoint' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'retry_after' => $retryAfter,
            'user_agent' => $request->userAgent(),
        ]);

        return $this->rateLimitErrorResponse(
            $retryAfter,
            "Limite de tentativas excedido. Tente novamente em {$retryAfter} segundos."
        );
    }

    /**
     * Add rate limit headers to response
     *
     * @param mixed $response
     * @param string $key
     * @param int $maxAttempts
     * @return mixed
     */
    protected function addRateLimitHeaders($response, string $key, int $maxAttempts)
    {
        if (method_exists($response, 'headers')) {
            $remaining = RateLimiter::remaining($key, $maxAttempts);
            $response->headers->add([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => max(0, $remaining),
            ]);
        }
        
        return $response;
    }
}