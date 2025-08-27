<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;

/**
 * Base API Controller with standardized response methods
 * 
 * This controller can be extended by other API controllers to ensure
 * consistent response formatting across the entire API.
 */
abstract class BaseApiController extends Controller
{
    use ApiResponseTrait;

    /**
     * Common method to handle successful resource creation
     *
     * @param mixed $data
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createdResponse($data, string $message = 'Recurso criado com sucesso.'): \Illuminate\Http\JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Common method to handle successful resource updates
     *
     * @param mixed $data
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function updatedResponse($data, string $message = 'Recurso atualizado com sucesso.'): \Illuminate\Http\JsonResponse
    {
        return $this->successResponse($data, $message);
    }

    /**
     * Common method to handle successful resource deletion
     *
     * @param array $meta
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function deletedResponse(array $meta = [], string $message = 'Recurso removido com sucesso.'): \Illuminate\Http\JsonResponse
    {
        return $this->successResponse(null, $message, 200, $meta);
    }

    /**
     * Common method to handle paginated responses
     *
     * @param mixed $paginatedData
     * @param string|null $message
     * @return mixed
     */
    protected function paginatedResponse($paginatedData, string $message = null)
    {
        // For paginated responses, we typically return the resource collection directly
        // as it already includes pagination meta data
        return $paginatedData;
    }

    /**
     * Handle common validation errors
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleValidationErrors(\Illuminate\Contracts\Validation\Validator $validator): \Illuminate\Http\JsonResponse
    {
        return $this->validationErrorResponse($validator->errors()->toArray());
    }

    /**
     * Handle common model not found scenarios
     *
     * @param string $modelName
     * @return \Illuminate\Http\JsonResponse
     */
    protected function modelNotFoundResponse(string $modelName = 'Recurso'): \Illuminate\Http\JsonResponse
    {
        return $this->notFoundErrorResponse($modelName);
    }

    /**
     * Handle unauthorized access attempts
     *
     * @param string|null $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthorizedResponse(string $message = null): \Illuminate\Http\JsonResponse
    {
        return $this->authenticationErrorResponse($message);
    }

    /**
     * Handle forbidden access attempts
     *
     * @param string|null $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function forbiddenResponse(string $message = null): \Illuminate\Http\JsonResponse
    {
        return $this->forbiddenErrorResponse($message);
    }
}