<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SalaResource;
use App\Models\Sala;
use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class SalaController extends Controller
{
    /**
     * Retorna todas as salas.
     * 
     * @return object
     */
    public function index() : object {
       return SalaResource::collection(Sala::all());
    }

    /**
     * Retorna as informações da sala.
     * 
     * @param Sala $sala
     * 
     * @return object
     */
    public function show(Sala $sala) : object {
        return new SalaResource($sala);
    }

    /**
     * Retorna os slots de disponibilidade da sala no período especificado.
     * 
     * @param Request $request
     * @param Sala $sala
     * 
     * @return JsonResponse
     */
    public function availability(Request $request, Sala $sala): JsonResponse
    {
        try {
            // Validação dos parâmetros
            $validator = Validator::make($request->all(), [
                'data_inicio' => 'required|date|date_format:Y-m-d',
                'data_fim' => 'required|date|date_format:Y-m-d|after_or_equal:data_inicio',
                'horario_inicio' => 'required|date_format:H:i',
                'horario_fim' => 'required|date_format:H:i|after:horario_inicio'
            ], [
                'data_inicio.required' => 'A data de início é obrigatória.',
                'data_inicio.date_format' => 'A data de início deve estar no formato Y-m-d.',
                'data_fim.required' => 'A data de fim é obrigatória.',
                'data_fim.date_format' => 'A data de fim deve estar no formato Y-m-d.',
                'data_fim.after_or_equal' => 'A data de fim deve ser igual ou posterior à data de início.',
                'horario_inicio.required' => 'O horário de início é obrigatório.',
                'horario_inicio.date_format' => 'O horário de início deve estar no formato H:i.',
                'horario_fim.required' => 'O horário de fim é obrigatório.',
                'horario_fim.date_format' => 'O horário de fim deve estar no formato H:i.',
                'horario_fim.after' => 'O horário de fim deve ser posterior ao horário de início.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'message' => 'Dados inválidos fornecidos.',
                    'details' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Parse das datas
            $dataInicio = Carbon::createFromFormat('Y-m-d', $validated['data_inicio']);
            $dataFim = Carbon::createFromFormat('Y-m-d', $validated['data_fim']);
            $horarioInicio = $validated['horario_inicio'];
            $horarioFim = $validated['horario_fim'];

            // Buscar reservas aprovadas no período especificado
            $reservas = Reserva::where('sala_id', $sala->id)
                ->where('status', 'aprovada')
                ->whereDate('data', '>=', $dataInicio)
                ->whereDate('data', '<=', $dataFim)
                ->where(function ($query) use ($horarioInicio, $horarioFim) {
                    // Verificar sobreposição de horários
                    $query->where(function ($q) use ($horarioInicio, $horarioFim) {
                        // Reserva que inicia antes e termina depois (engloba o período)
                        $q->where('horario_inicio', '<', $horarioInicio)
                          ->where('horario_fim', '>', $horarioFim);
                    })->orWhere(function ($q) use ($horarioInicio, $horarioFim) {
                        // Reserva que inicia durante o período
                        $q->where('horario_inicio', '>=', $horarioInicio)
                          ->where('horario_inicio', '<', $horarioFim);
                    })->orWhere(function ($q) use ($horarioInicio, $horarioFim) {
                        // Reserva que termina durante o período
                        $q->where('horario_fim', '>', $horarioInicio)
                          ->where('horario_fim', '<=', $horarioFim);
                    });
                })
                ->select('data', 'horario_inicio', 'horario_fim', 'nome')
                ->get();

            // Calcular dias disponíveis
            $diasDisponiveis = [];
            $currentDate = $dataInicio->copy();

            while ($currentDate <= $dataFim) {
                $dataFormatted = $currentDate->format('Y-m-d');
                
                // Verificar se há conflitos nesta data
                $reservasConflito = $reservas->filter(function ($reserva) use ($dataFormatted) {
                    return Carbon::createFromFormat('d/m/Y', $reserva->data)->format('Y-m-d') === $dataFormatted;
                });

                $disponivel = $reservasConflito->isEmpty();
                
                $diasDisponiveis[] = [
                    'data' => $dataFormatted,
                    'disponivel' => $disponivel,
                    'conflitos' => $reservasConflito->map(function ($reserva) {
                        return [
                            'nome' => $reserva->nome,
                            'horario_inicio' => $reserva->horario_inicio,
                            'horario_fim' => $reserva->horario_fim
                        ];
                    })->values()->toArray()
                ];

                $currentDate->addDay();
            }

            return response()->json([
                'data' => [
                    'sala' => [
                        'id' => $sala->id,
                        'nome' => $sala->nome
                    ],
                    'periodo' => [
                        'data_inicio' => $validated['data_inicio'],
                        'data_fim' => $validated['data_fim'],
                        'horario_inicio' => $horarioInicio,
                        'horario_fim' => $horarioFim
                    ],
                    'disponibilidade' => $diasDisponiveis,
                    'resumo' => [
                        'total_dias' => count($diasDisponiveis),
                        'dias_disponiveis' => count(array_filter($diasDisponiveis, fn($dia) => $dia['disponivel'])),
                        'dias_ocupados' => count(array_filter($diasDisponiveis, fn($dia) => !$dia['disponivel']))
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Erro ao consultar disponibilidade da sala.',
                'details' => [
                    'type' => 'availability_check_failed',
                    'code' => 'internal_error'
                ]
            ], 500);
        }
    }
}
