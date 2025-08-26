<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class ReservaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'sala' => [
                'id' => $this->sala->id,
                'nome' => $this->sala->nome,
            ],
            'finalidade' => [
                'id' => $this->finalidade->id,
                'legenda' => $this->finalidade->legenda,
            ],
            'data' => $this->convertToApiDateFormat($this->data),
            'horario_inicio' => $this->horario_inicio,
            'horario_fim' => $this->horario_fim,
            'status' => $this->status,
            'tipo_responsaveis' => $this->tipo_responsaveis,
            'responsaveis' => $this->responsaveis->map(function ($responsavel) {
                return [
                    'id' => $responsavel->id,
                    'nome' => $responsavel->nome,
                    'codpes' => $responsavel->codpes,
                ];
            }),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'recorrente' => [
                'is_recurrent' => !is_null($this->parent_id),
                'parent_id' => $this->parent_id,
                'repeat_days' => $this->repeat_days,
                'repeat_until' => $this->convertToApiDateFormat($this->repeat_until),
            ],
            'timestamps' => [
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
        ];
    }

    /**
     * Convert date from web format (d/m/Y) to API format (Y-m-d)
     */
    private function convertToApiDateFormat($date)
    {
        if (!$date) {
            return null;
        }

        try {
            // Try to parse as d/m/Y format (web format)
            $carbonDate = Carbon::createFromFormat('d/m/Y', $date);
            return $carbonDate->format('Y-m-d');
        } catch (\Exception $e) {
            // If parsing fails, return original value
            return $date;
        }
    }
}
