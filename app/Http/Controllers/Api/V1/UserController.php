<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\StoreUserRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends BaseApiController
{
    /**
     * Busca usuário por codpes ou lista usuários.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Se codpes foi fornecido, busca por número USP
            if ($request->has('codpes')) {
                $codpes = $request->input('codpes');

                // Valida que codpes é um inteiro
                if (!is_numeric($codpes)) {
                    return $this->validationErrorResponse(
                        ['codpes' => ['O número USP (codpes) deve ser um número inteiro.']],
                        'Parâmetro inválido.'
                    );
                }

                $user = User::where('codpes', $codpes)->first();

                if (!$user) {
                    return $this->notFoundErrorResponse('Usuário', 'Usuário não encontrado.');
                }

                // Eager load categorias
                $user->load('categorias');

                Log::info('Usuário buscado por codpes via API', [
                    'codpes' => $codpes,
                    'user_id' => $user->id,
                    'searched_by' => auth()->id()
                ]);

                return $this->successResponse(new UserResource($user));
            }

            // Se não há filtro, retorna erro (podemos implementar listagem depois se necessário)
            return $this->validationErrorResponse(
                ['codpes' => ['O parâmetro codpes é obrigatório para busca de usuários.']],
                'Parâmetro obrigatório ausente.'
            );
        } catch (\Exception $e) {
            Log::error('Erro ao buscar usuário', [
                'codpes' => $request->input('codpes'),
                'error' => $e->getMessage()
            ]);

            return $this->internalServerErrorResponse('Erro ao buscar usuário.');
        }
    }

    /**
     * Cria um novo usuário no sistema.
     *
     * @param StoreUserRequest $request
     * @return JsonResponse
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Se a senha não foi fornecida, gera uma senha aleatória
            // (usuários podem redefinir via Senhaunica ou outro mecanismo)
            if (!isset($validated['password'])) {
                $validated['password'] = Hash::make(bin2hex(random_bytes(16)));
            } else {
                $validated['password'] = Hash::make($validated['password']);
            }

            $user = User::create($validated);

            Log::info('Usuário criado via API', [
                'user_id' => $user->id,
                'codpes' => $user->codpes,
                'created_by' => auth()->id()
            ]);

            return $this->createdResponse(
                new UserResource($user),
                'Usuário criado com sucesso.'
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Trata erros de duplicação que possam ter escapado da validação
            if ($e->getCode() === '23000') {
                return $this->validationErrorResponse(
                    ['codpes' => ['Este número USP ou e-mail já está cadastrado.']],
                    'Erro de duplicação de dados.'
                );
            }

            Log::error('Erro ao criar usuário', [
                'error' => $e->getMessage(),
                'data' => $request->except('password')
            ]);

            return $this->databaseErrorResponse('Erro ao criar usuário.');
        } catch (\Exception $e) {
            Log::error('Erro ao criar usuário', [
                'error' => $e->getMessage(),
                'data' => $request->except('password')
            ]);

            return $this->internalServerErrorResponse('Erro ao criar usuário.');
        }
    }

    /**
     * Retorna informações detalhadas do usuário.
     *
     * @param User $user
     * @return JsonResponse
     */
    public function show(User $user): JsonResponse
    {
        try {
            // Eager load categorias para incluir no response
            $user->load('categorias');

            return $this->successResponse(new UserResource($user));
        } catch (\Exception $e) {
            Log::error('Erro ao buscar usuário', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return $this->internalServerErrorResponse('Erro ao buscar informações do usuário.');
        }
    }
}
