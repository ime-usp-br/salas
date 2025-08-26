<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReservaRequest;
use App\Http\Requests\Api\UpdateReservaRequest;
use App\Http\Resources\V1\ReservaResource;
use App\Models\Finalidade;
use App\Models\Reserva;
use App\Models\ResponsavelReserva;
use App\Models\Sala;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Uspdev\Replicado\Pessoa;

class ReservaController extends Controller
{
    /**
     * Retorna todas as reservas com base nos filtros passados pelo método GET.
     * Se nenhum filtro for passado será retornado todas as reservas do dia corrente.
     * 
     * Três filtros estão disponíveis:
     * - sala        // Recebe o id da sala.
     * - finalidade  // Recebe o id da finalidade.
     * - data        // Deve estar no formato 'Y-m-d'
     * 
     * @param Request $request
     * 
     * @return object
     */
    public function getReservas(Request $request) : object {
       $data = is_null($request->input('data')) ? Carbon::now()->format('Y-m-d') : $request->input('data');

       // Convert API date format to web format for database query
       $dataFormatted = Carbon::createFromFormat('Y-m-d', $data)->format('d/m/Y');

       $reservas = Reserva::where('data', $dataFormatted)->where('status', 'aprovada')->get();

       if(!is_null($request->input('finalidade'))){
         $reservas = $reservas->where('finalidade_id', $request->input('finalidade'));
       }

       if(!is_null($request->input('sala'))){
         $reservas = $reservas->where('sala_id', $request->input('sala'));
       }

       return ReservaResource::collection($reservas);
    }

    /**
     * Store a newly created reserva in storage.
     *
     * @param StoreReservaRequest $request
     * @return JsonResponse
     */
    public function store(StoreReservaRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();
            $user = $request->user();
            $sala = Sala::findOrFail($validatedData['sala_id']);

            // Determine initial status based on room approval rules
            $status = 'aprovada'; // Default
            if ($sala->restricao && $sala->restricao->aprovacao) {
                $status = 'pendente';
            }

            // Handle recurrent reservations
            $reservas_created = [];
            $parent_id = null;

            if (!empty($validatedData['repeat_days']) && !empty($validatedData['repeat_until'])) {
                // Create recurrent reservations
                $start_date = Carbon::createFromFormat('Y-m-d', $validatedData['data']);
                $end_date = Carbon::createFromFormat('Y-m-d', $validatedData['repeat_until']);
                $repeat_days = $validatedData['repeat_days'];

                $current_date = $start_date->copy();
                $first_reserva = null;

                while ($current_date->lte($end_date)) {
                    if (in_array($current_date->dayOfWeek, $repeat_days)) {
                        $reserva_data = array_merge($validatedData, [
                            'data' => $current_date->format('Y-m-d'),
                            'user_id' => $user->id,
                            'status' => $status,
                        ]);

                        // Remove API-specific fields that shouldn't be stored
                        unset($reserva_data['repeat_days'], $reserva_data['repeat_until']);

                        $reserva = Reserva::create($reserva_data);

                        if ($first_reserva === null) {
                            $first_reserva = $reserva;
                            $parent_id = $reserva->id;
                            
                            // Update parent_id for the first reservation
                            $reserva->update(['parent_id' => $parent_id]);
                        } else {
                            $reserva->update(['parent_id' => $parent_id]);
                        }

                        $reservas_created[] = $reserva;

                        // Handle automatic approval job scheduling
                        if ($status === 'pendente') {
                            $reserva->reagendarTarefa_AprovacaoAutomatica();
                        }
                    }
                    $current_date->addDay();
                }
            } else {
                // Create single reservation
                $reserva_data = array_merge($validatedData, [
                    'user_id' => $user->id,
                    'status' => $status,
                ]);

                // Remove API-specific fields
                unset($reserva_data['repeat_days'], $reserva_data['repeat_until']);

                $reserva = Reserva::create($reserva_data);
                $reservas_created[] = $reserva;

                // Handle automatic approval job scheduling
                if ($status === 'pendente') {
                    $reserva->reagendarTarefa_AprovacaoAutomatica();
                }
            }

            // Handle responsaveis if needed
            $this->handleResponsaveis($reservas_created, $validatedData);

            DB::commit();

            $response_data = [
                'data' => [
                    'id' => $reservas_created[0]->id,
                    'nome' => $reservas_created[0]->nome,
                    'sala' => $reservas_created[0]->sala->nome,
                    'data' => $reservas_created[0]->data,
                    'horario_inicio' => $reservas_created[0]->horario_inicio,
                    'horario_fim' => $reservas_created[0]->horario_fim,
                    'status' => $reservas_created[0]->status,
                    'instances_created' => count($reservas_created),
                ]
            ];

            if (count($reservas_created) > 1) {
                $response_data['data']['parent_id'] = $parent_id;
                $response_data['data']['recurrent'] = true;
            }

            return response()->json($response_data, 201);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollback();
            Log::error('Database error creating reserva: ' . $e->getMessage());
            
            // Check for common database constraint violations
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                return response()->json([
                    'error' => 'Validation failed',
                    'message' => 'Referência inválida detectada (sala ou finalidade inexistente).',
                    'details' => [
                        'type' => 'constraint_violation',
                        'code' => 'foreign_key_constraint'
                    ]
                ], 422);
            }
            
            return response()->json([
                'error' => 'Database error',
                'message' => 'Erro na base de dados. Verifique os dados e tente novamente.',
                'details' => [
                    'type' => 'database_error',
                    'code' => 'query_exception'
                ]
            ], 500);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating reserva: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Não foi possível criar a reserva. Tente novamente.',
                'details' => [
                    'type' => 'internal_error',
                    'code' => 'unexpected_exception'
                ]
            ], 500);
        }
    }

    /**
     * Update the specified reserva in storage.
     *
     * @param UpdateReservaRequest $request
     * @param Reserva $reserva
     * @return JsonResponse
     */
    public function update(UpdateReservaRequest $request, Reserva $reserva): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Handle status changes if sala is changed and requires approval
            if (isset($validatedData['sala_id']) && $validatedData['sala_id'] != $reserva->sala_id) {
                $new_sala = Sala::findOrFail($validatedData['sala_id']);
                if ($new_sala->restricao && $new_sala->restricao->aprovacao) {
                    $validatedData['status'] = 'pendente';
                }
            }

            // Remove the approval task if it exists before updating
            $reserva->removerTarefa_AprovacaoAutomatica();

            // Update the reservation
            $reserva->update($validatedData);

            // Reschedule approval task if needed
            if ($reserva->status === 'pendente') {
                $reserva->reagendarTarefa_AprovacaoAutomatica();
            }

            // Handle responsaveis if needed
            if (isset($validatedData['tipo_responsaveis'])) {
                $this->handleResponsaveis([$reserva], $validatedData);
            }

            DB::commit();

            return response()->json([
                'data' => new ReservaResource($reserva->fresh()),
                'message' => 'Reserva atualizada com sucesso.'
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollback();
            Log::error('Database error updating reserva: ' . $e->getMessage());
            
            // Check for common database constraint violations
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                return response()->json([
                    'error' => 'Validation failed',
                    'message' => 'Referência inválida detectada (sala ou finalidade inexistente).',
                    'details' => [
                        'type' => 'constraint_violation',
                        'code' => 'foreign_key_constraint'
                    ]
                ], 422);
            }
            
            return response()->json([
                'error' => 'Database error',
                'message' => 'Erro na base de dados. Verifique os dados e tente novamente.',
                'details' => [
                    'type' => 'database_error',
                    'code' => 'query_exception'
                ]
            ], 500);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating reserva: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Não foi possível atualizar a reserva. Tente novamente.',
                'details' => [
                    'type' => 'internal_error',
                    'code' => 'unexpected_exception'
                ]
            ], 500);
        }
    }

    /**
     * Remove the specified reserva from storage.
     *
     * @param Request $request
     * @param Reserva $reserva
     * @return JsonResponse
     */
    public function destroy(Request $request, Reserva $reserva): JsonResponse
    {
        try {
            // Enhanced authorization check
            $user = $request->user();
            
            // Verify user is authenticated
            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Token de autenticação inválido ou expirado.',
                    'details' => [
                        'type' => 'authentication_required',
                        'code' => 'invalid_token'
                    ]
                ], 401);
            }

            // Check if user can delete this reservation
            $canDelete = $user->id === $reserva->user_id;
            $isAdmin = false;
            
            // Enhanced admin check with error handling  
            if (method_exists($user, 'hasRole')) {
                try {
                    // Try different possible admin role names
                    $adminRoles = ['admin', 'administrator', 'superadmin', 'super-admin'];
                    foreach ($adminRoles as $role) {
                        if ($user->hasRole($role)) {
                            $isAdmin = true;
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Role check failed for user ' . $user->id . ': ' . $e->getMessage());
                    $isAdmin = false;
                }
            }
            
            if (!$canDelete && !$isAdmin) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'Você só pode cancelar suas próprias reservas.',
                    'details' => [
                        'type' => 'insufficient_permissions',
                        'code' => 'unauthorized_access',
                        'user_id' => $user->id,
                        'reservation_owner' => $reserva->user_id
                    ]
                ], 403);
            }

            // Additional validation - prevent deletion of past reservations unless admin
            if (!$isAdmin) {
                $reservationData = $reserva->getRawOriginal('data');
                
                // Since we reverted the model, the raw data is stored in Y-m-d format
                $reservationDate = Carbon::createFromFormat('Y-m-d', $reservationData);
                
                if ($reservationDate->isPast()) {
                    return response()->json([
                        'error' => 'Validation failed',
                        'message' => 'Não é possível cancelar reservas de datas passadas.',
                        'details' => [
                            'type' => 'business_rule_violation',
                            'code' => 'past_date_restriction'
                        ]
                    ], 422);
                }
            }

            DB::beginTransaction();

            $purge = $request->query('purge', false);
            $reservas_deleted = [];

            if ($purge && $reserva->parent_id) {
                // Delete all related recurring reservations
                $related_reservas = Reserva::where('parent_id', $reserva->parent_id)->get();
                
                foreach ($related_reservas as $rel_reserva) {
                    $rel_reserva->removerTarefa_AprovacaoAutomatica();
                    $reservas_deleted[] = [
                        'id' => $rel_reserva->id,
                        'nome' => $rel_reserva->nome,
                        'data' => $rel_reserva->data
                    ];
                    $rel_reserva->delete();
                }
            } else {
                // Delete only this reservation
                $reserva->removerTarefa_AprovacaoAutomatica();
                $reservas_deleted[] = [
                    'id' => $reserva->id,
                    'nome' => $reserva->nome,
                    'data' => $reserva->data
                ];
                $reserva->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Reserva(s) cancelada(s) com sucesso.',
                'data' => [
                    'deleted_count' => count($reservas_deleted),
                    'deleted_reservas' => $reservas_deleted
                ]
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollback();
            Log::error('Database error deleting reserva: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Database error',
                'message' => 'Erro na base de dados ao cancelar a reserva. Tente novamente.',
                'details' => [
                    'type' => 'database_error',
                    'code' => 'query_exception'
                ]
            ], 500);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error deleting reserva: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Não foi possível cancelar a reserva. Tente novamente.',
                'details' => [
                    'type' => 'internal_error',
                    'code' => 'unexpected_exception'
                ]
            ], 500);
        }
    }

    /**
     * Handle responsaveis for reservations
     *
     * @param array $reservas
     * @param array $validatedData
     */
    private function handleResponsaveis(array $reservas, array $validatedData): void
    {
        if (!isset($validatedData['tipo_responsaveis'])) {
            return;
        }

        $responsaveis = collect();
        $user = auth()->user();

        switch ($validatedData['tipo_responsaveis']) {
            case 'eu':
                $responsavel = ResponsavelReserva::firstOrCreate([
                    'nome' => $user->name,
                    'codpes' => $user->codpes ?? null
                ]);
                $responsaveis->push($responsavel);
                break;

            case 'unidade':
                if (isset($validatedData['responsaveis_unidade']) && is_array($validatedData['responsaveis_unidade'])) {
                    foreach ($validatedData['responsaveis_unidade'] as $responsavel_codpes) {
                        try {
                            // Try to get name from Pessoa service
                            $nome = null;
                            if (class_exists('Uspdev\Replicado\Pessoa')) {
                                $nome = Pessoa::obterNome($responsavel_codpes);
                            }
                            
                            $responsavel = ResponsavelReserva::firstOrCreate([
                                'nome' => $nome ?: "Usuário {$responsavel_codpes}",
                                'codpes' => $responsavel_codpes
                            ]);
                            $responsaveis->push($responsavel);
                        } catch (\Exception $e) {
                            Log::warning("Failed to get name for codpes {$responsavel_codpes}: " . $e->getMessage());
                            // Create with codpes only if name lookup fails
                            $responsavel = ResponsavelReserva::firstOrCreate([
                                'nome' => "Usuário {$responsavel_codpes}",
                                'codpes' => $responsavel_codpes
                            ]);
                            $responsaveis->push($responsavel);
                        }
                    }
                }
                break;

            case 'externo':
                if (isset($validatedData['responsaveis_externo']) && is_array($validatedData['responsaveis_externo'])) {
                    foreach ($validatedData['responsaveis_externo'] as $responsavel_nome) {
                        $responsavel = ResponsavelReserva::firstOrCreate([
                            'nome' => $responsavel_nome,
                            'codpes' => null
                        ]);
                        $responsaveis->push($responsavel);
                    }
                }
                break;
        }

        // Sync responsaveis to all reservas (for recurrent reservations)
        if ($responsaveis->isNotEmpty()) {
            foreach ($reservas as $reserva) {
                $reserva->responsaveis()->sync($responsaveis->pluck('id'));
            }
        }
    }
}
