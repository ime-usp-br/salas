<?php

namespace Database\Seeders;

use App\Models\Recurso;
use Illuminate\Database\Seeder;

class RecursoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $recursos = [
            ['nome' => 'Térreo'],
            ['nome' => '1º Andar'],
            ['nome' => '2º Andar'],
            ['nome' => 'Ar Condicionado'],
            ['nome' => 'Vídeo Conferência'],
        ];

        foreach ($recursos as $recurso) {
            Recurso::create($recurso);
        }
    }
}
