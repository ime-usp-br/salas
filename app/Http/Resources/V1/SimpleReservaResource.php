<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class SimpleReservaResource extends JsonResource
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
            'horario_inicio' => $this->formatTimeToGi($this->horario_inicio),
            'horario_fim' => $this->formatTimeToGi($this->horario_fim),
        ];
    }

    /**
     * Format time to G:i format (e.g., '9:00', '14:30')
     *
     * @param string $time
     * @return string
     */
    private function formatTimeToGi(string $time): string
    {
        try {
            return Carbon::createFromFormat('H:i:s', $time)->format('G:i');
        } catch (\Exception $e) {
            // If time is already in G:i format or H:i format, try to parse it
            try {
                return Carbon::createFromFormat('H:i', $time)->format('G:i');
            } catch (\Exception $e) {
                // Return original if all parsing attempts fail
                return $time;
            }
        }
    }
}