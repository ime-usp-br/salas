<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ThrottleRequestsException;
use Throwable;
use App\Http\Traits\ApiResponseTrait;

class Handler extends ExceptionHandler
{
    use ApiResponseTrait;

    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            // Enhanced logging for API errors
            if (request()->expectsJson()) {
                \Log::error('API Exception', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'endpoint' => request()->getPathInfo(),
                    'method' => request()->getMethod(),
                    'user_id' => auth()->id(),
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param Request $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e)
    {
        // IMPORTANT: Only handle API exceptions if they are NOT already handled
        // and only for specific exception types to maintain compatibility
        if ($request->expectsJson() || $request->is('api/*')) {
            // Only handle specific exceptions that need standardization
            // Let Laravel handle ValidationException and others to maintain compatibility
            if ($e instanceof AuthenticationException) {
                return $this->renderApiException($request, $e);
            }
            
            if ($e instanceof ThrottleRequestsException) {
                return $this->renderApiException($request, $e);
            }
            
            // For other exceptions, let Laravel handle them normally
            // This preserves existing test expectations and response formats
        }

        // For web requests and unhandled API exceptions, use default Laravel handling
        return parent::render($request, $e);
    }

    /**
     * Render API exceptions with standardized format
     *
     * @param Request $request
     * @param Throwable $e
     * @return \Illuminate\Http\JsonResponse
     */
    protected function renderApiException(Request $request, Throwable $e)
    {
        // Handle specific exception types
        if ($e instanceof ValidationException) {
            return $this->validationErrorResponse(
                $e->errors(),
                'Os dados fornecidos são inválidos.'
            );
        }

        if ($e instanceof AuthenticationException) {
            return $this->authenticationErrorResponse(
                'Token de autenticação inválido ou expirado.'
            );
        }

        if ($e instanceof AuthorizationException) {
            return $this->forbiddenErrorResponse(
                'Você não tem permissão para acessar este recurso.'
            );
        }

        if ($e instanceof NotFoundHttpException) {
            return $this->notFoundErrorResponse(
                'Recurso',
                'O recurso solicitado não foi encontrado.'
            );
        }

        if ($e instanceof ThrottleRequestsException) {
            $retryAfter = $e->getHeaders()['Retry-After'] ?? 60;
            return $this->rateLimitErrorResponse(
                (int) $retryAfter,
                'Muitas tentativas. Tente novamente mais tarde.'
            );
        }

        if ($e instanceof QueryException) {
            // Don't expose database details in production
            $message = app()->environment('production') 
                ? 'Erro na base de dados. Tente novamente.'
                : $e->getMessage();
                
            return $this->databaseErrorResponse($message);
        }

        if ($e instanceof HttpException) {
            return $this->errorResponse(
                'HTTP Error',
                $e->getMessage() ?: 'Ocorreu um erro HTTP.',
                [
                    'type' => 'http_error',
                    'code' => 'http_' . $e->getStatusCode(),
                    'status_code' => $e->getStatusCode()
                ],
                $e->getStatusCode()
            );
        }

        // For any other exception, return generic internal server error
        $message = app()->environment('production')
            ? 'Erro interno do servidor. Tente novamente.'
            : $e->getMessage();

        return $this->internalServerErrorResponse(
            $message,
            app()->environment('production') ? [] : [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]
        );
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param Request $request
     * @param AuthenticationException $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return $this->authenticationErrorResponse();
        }

        return redirect()->guest($exception->redirectTo() ?? route('login'));
    }
}
