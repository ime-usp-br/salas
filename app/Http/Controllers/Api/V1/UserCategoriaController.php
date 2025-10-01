<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\AttachCategoriaRequest;
use App\Http\Requests\Api\SyncCategoriasRequest;
use App\Http\Resources\V1\CategoriaResource;
use App\Models\Categoria;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserCategoriaController extends BaseApiController
{
    /**
     * Retorna todas as categorias associadas ao usuário.
     *
     * @param User $user
     * @return JsonResponse
     */
    public function index(User $user): JsonResponse
    {
        try {
            $categorias = $user->categorias()->withPivot('created_at')->get();

            return $this->successResponse([
                'data' => CategoriaResource::collection($categorias)
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar categorias do usuário', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return $this->internalServerErrorResponse('Erro ao listar categorias do usuário.');
        }
    }

    /**
     * Associa uma categoria ao usuário (operação idempotente).
     *
     * @param AttachCategoriaRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function attach(AttachCategoriaRequest $request, User $user): JsonResponse
    {
        try {
            $categoriaId = $request->validated()['categoria_id'];
            $categoria = Categoria::findOrFail($categoriaId);

            // Verifica se a associação já existe (idempotência)
            $existing = $user->categorias()->where('categoria_id', $categoriaId)->first();

            if ($existing) {
                // Associação já existe - retorna 200 OK em vez de 201
                return $this->successResponse([
                    'user_id' => $user->id,
                    'categoria_id' => $categoria->id,
                    'categoria_nome' => $categoria->nome,
                    'created_at' => $existing->pivot->created_at,
                ], 'Categoria já estava associada ao usuário.');
            }

            // Cria nova associação
            $user->categorias()->attach($categoriaId);

            // Busca a associação recém-criada para obter o timestamp
            $newAssociation = $user->categorias()->where('categoria_id', $categoriaId)->first();

            Log::info('Categoria associada ao usuário', [
                'user_id' => $user->id,
                'categoria_id' => $categoriaId,
                'created_by' => auth()->id()
            ]);

            return $this->createdResponse([
                'user_id' => $user->id,
                'categoria_id' => $categoria->id,
                'categoria_nome' => $categoria->nome,
                'created_at' => $newAssociation->pivot->created_at,
            ], 'Categoria associada com sucesso.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundErrorResponse('Categoria');
        } catch (\Exception $e) {
            Log::error('Erro ao associar categoria ao usuário', [
                'user_id' => $user->id,
                'categoria_id' => $request->input('categoria_id'),
                'error' => $e->getMessage()
            ]);

            return $this->internalServerErrorResponse('Erro ao associar categoria ao usuário.');
        }
    }

    /**
     * Remove a associação entre usuário e categoria.
     *
     * @param User $user
     * @param Categoria $categoria
     * @return JsonResponse
     */
    public function detach(User $user, Categoria $categoria): JsonResponse
    {
        try {
            // Verifica se a associação existe
            $exists = $user->categorias()->where('categoria_id', $categoria->id)->exists();

            if (!$exists) {
                return $this->notFoundErrorResponse('Associação', 'Associação não encontrada.');
            }

            // Remove a associação
            $user->categorias()->detach($categoria->id);

            Log::info('Categoria removida do usuário', [
                'user_id' => $user->id,
                'categoria_id' => $categoria->id,
                'removed_by' => auth()->id()
            ]);

            return $this->deletedResponse([], 'Categoria removida com sucesso.');
        } catch (\Exception $e) {
            Log::error('Erro ao remover categoria do usuário', [
                'user_id' => $user->id,
                'categoria_id' => $categoria->id,
                'error' => $e->getMessage()
            ]);

            return $this->internalServerErrorResponse('Erro ao remover categoria do usuário.');
        }
    }

    /**
     * Sincroniza as categorias do usuário (operação bulk).
     * Remove associações não listadas, adiciona novas, mantém existentes.
     *
     * @param SyncCategoriasRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function sync(SyncCategoriasRequest $request, User $user): JsonResponse
    {
        try {
            $categoriaIds = $request->validated()['categoria_ids'];

            DB::beginTransaction();

            // Sync retorna arrays com IDs attached, detached e updated
            $syncResult = $user->categorias()->sync($categoriaIds);

            DB::commit();

            Log::info('Categorias sincronizadas para usuário', [
                'user_id' => $user->id,
                'attached' => $syncResult['attached'],
                'detached' => $syncResult['detached'],
                'updated' => $syncResult['updated'],
                'synced_by' => auth()->id()
            ]);

            return $this->successResponse([
                'attached' => $syncResult['attached'],
                'detached' => $syncResult['detached'],
                'updated' => $syncResult['updated'],
            ], 'Categorias sincronizadas com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erro ao sincronizar categorias do usuário', [
                'user_id' => $user->id,
                'categoria_ids' => $request->input('categoria_ids'),
                'error' => $e->getMessage()
            ]);

            return $this->internalServerErrorResponse('Erro ao sincronizar categorias do usuário.');
        }
    }
}
