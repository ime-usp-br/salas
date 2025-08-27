<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

trait ApiResponseTrait
{
    /**
     * Return a successful JSON response with standardized format
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $statusCode
     * @param array $meta Additional metadata
     * @return JsonResponse
     */
    protected function successResponse($data = null, string $message = null, int $statusCode = 200, array $meta = []): JsonResponse
    {
        $response = [];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($message !== null) {
            $response['message'] = $message;
        }

        if (!empty($meta)) {
            $response['meta'] = array_merge(['success' => true], $meta);
        } elseif ($message !== null || !empty($meta)) {
            $response['meta'] = ['success' => true];
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return an error JSON response with standardized format
     *
     * @param string $error Error type/category
     * @param string $message Human-readable error message
     * @param array $details Additional error details
     * @param int $statusCode HTTP status code
     * @param array $meta Additional metadata
     * @return JsonResponse
     */
    protected function errorResponse(string $error, string $message, array $details = [], int $statusCode = 400, array $meta = []): JsonResponse
    {
        $response = [
            'error' => $error,
            'message' => $message,
        ];

        if (!empty($details)) {
            $response['details'] = $details;
        }

        if (!empty($meta)) {
            $response['meta'] = array_merge(['success' => false], $meta);
        }

        // Log error for debugging (excluding sensitive information)
        $logContext = [
            'error_type' => $error,
            'status_code' => $statusCode,
            'endpoint' => request()->getPathInfo(),
            'method' => request()->getMethod(),
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
        ];

        if (!empty($details)) {
            // Only log non-sensitive details
            $safeDetails = array_filter($details, function($key) {
                return !in_array(strtolower($key), ['password', 'token', 'key', 'secret']);
            }, ARRAY_FILTER_USE_KEY);
            
            if (!empty($safeDetails)) {
                $logContext['details'] = $safeDetails;
            }
        }

        Log::warning('API Error Response', $logContext);

        return response()->json($response, $statusCode);
    }

    /**
     * Return a validation error response
     *
     * @param array $errors Validation errors
     * @param string $message Custom message
     * @return JsonResponse
     */
    protected function validationErrorResponse(array $errors, string $message = 'Dados inválidos fornecidos.'): JsonResponse
    {
        return $this->errorResponse(
            'Validation failed',
            $message,
            [
                'type' => 'validation_error',
                'code' => 'invalid_input',
                'validation_errors' => $errors
            ],
            422
        );
    }

    /**
     * Return an authentication error response
     *
     * @param string $message Custom message
     * @return JsonResponse
     */
    protected function authenticationErrorResponse(string $message = 'Token de autenticação inválido ou expirado.'): JsonResponse
    {
        return $this->errorResponse(
            'Unauthorized',
            $message,
            [
                'type' => 'authentication_required',
                'code' => 'invalid_token'
            ],
            401
        );
    }

    /**
     * Return a forbidden error response
     *
     * @param string $message Custom message
     * @return JsonResponse
     */
    protected function forbiddenErrorResponse(string $message = 'Acesso negado. Você não tem permissão para esta ação.'): JsonResponse
    {
        return $this->errorResponse(
            'Forbidden',
            $message,
            [
                'type' => 'insufficient_permissions',
                'code' => 'unauthorized_access'
            ],
            403
        );
    }

    /**
     * Return a not found error response
     *
     * @param string $resource Resource name
     * @param string|null $message Custom message
     * @return JsonResponse
     */
    protected function notFoundErrorResponse(string $resource = 'Recurso', string $message = null): JsonResponse
    {
        $message = $message ?: "{$resource} não encontrado.";
        
        return $this->errorResponse(
            'Not Found',
            $message,
            [
                'type' => 'resource_not_found',
                'code' => 'not_found'
            ],
            404
        );
    }

    /**
     * Return a rate limit exceeded error response
     *
     * @param int $retryAfter Seconds until retry is allowed
     * @param string $message Custom message
     * @return JsonResponse
     */
    protected function rateLimitErrorResponse(int $retryAfter = 60, string $message = 'Muitas tentativas. Tente novamente mais tarde.'): JsonResponse
    {
        return $this->errorResponse(
            'Too Many Requests',
            $message,
            [
                'type' => 'rate_limit_exceeded',
                'code' => 'too_many_requests',
                'retry_after' => $retryAfter
            ],
            429,
            [
                'retry_after_seconds' => $retryAfter
            ]
        )->header('Retry-After', $retryAfter);
    }

    /**
     * Return a database error response
     *
     * @param string $message Custom message
     * @param array $details Additional details
     * @return JsonResponse
     */
    protected function databaseErrorResponse(string $message = 'Erro na base de dados. Tente novamente.', array $details = []): JsonResponse
    {
        return $this->errorResponse(
            'Database error',
            $message,
            array_merge([
                'type' => 'database_error',
                'code' => 'query_exception'
            ], $details),
            500
        );
    }

    /**
     * Return a general internal server error response
     *
     * @param string $message Custom message
     * @param array $details Additional details
     * @return JsonResponse
     */
    protected function internalServerErrorResponse(string $message = 'Erro interno do servidor. Tente novamente.', array $details = []): JsonResponse
    {
        return $this->errorResponse(
            'Internal server error',
            $message,
            array_merge([
                'type' => 'internal_error',
                'code' => 'unexpected_exception'
            ], $details),
            500
        );
    }
}