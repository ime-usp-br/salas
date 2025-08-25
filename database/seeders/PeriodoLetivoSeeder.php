<?php

namespace Database\Seeders;

use App\Models\PeriodoLetivo;
use Illuminate\Database\Seeder;

class PeriodoLetivoSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $periodo = [
            'codigo' => '2º Semestre 2025',
            'data_inicio' => '2025/08/04',
            'data_fim' => '2025/12/12',
            'data_inicio_reservas' => '2025/08/15',
            'data_fim_reservas' => '2026/02/22'
        ];

        PeriodoLetivo::create($periodo);
    }
}
