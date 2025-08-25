<?php

namespace Database\Seeders;

use App\Models\Restricao;
use App\Models\Sala;
use App\Models\PeriodoLetivo;
use Illuminate\Database\Seeder;

class RestricaoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $periodoLetivo = PeriodoLetivo::where('codigo', '2º Semestre 2025')->first();
        $salas = Sala::all();

        foreach ($salas as $sala) {
            Restricao::create([
                'sala_id' => $sala->id,
                'tipo_restricao' => 'PERIODO_LETIVO',
                'periodo_letivo_id' => $periodoLetivo->id,
                'bloqueada' => false,
                'aprovacao' => false,
            ]);
        }
    }
}