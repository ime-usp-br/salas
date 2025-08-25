<?php

namespace Database\Seeders;

use App\Models\Sala;
use App\Models\Categoria;
use Illuminate\Database\Seeder;

class SalaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get category IDs
        $categorias = [
            'auditorio' => Categoria::where('nome', 'Auditório')->first()->id,
            'nobre' => Categoria::where('nome', 'Sala Nobre')->first()->id,
            'padrao' => Categoria::where('nome', 'Padrão')->first()->id,
        ];

        $salas = [
            ['nome' => 'A132', 'capacidade' => 45, 'categoria_id' => $categorias['nobre']],
            ['nome' => 'Sala Elza Gomide', 'capacidade' => 30, 'categoria_id' => $categorias['nobre']],
            ['nome' => 'A241', 'capacidade' => 30, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'A242', 'capacidade' => 30, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'A243', 'capacidade' => 30, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'A249', 'capacidade' => 30, 'categoria_id' => $categorias['nobre']],
            ['nome' => 'A252', 'capacidade' => 30, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'A259', 'capacidade' => 30, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'A266', 'capacidade' => 30, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'A267', 'capacidade' => 30, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'A268', 'capacidade' => 30, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'Auditório Antonio Gilioli', 'capacidade' => 80, 'categoria_id' => $categorias['auditorio']],
            ['nome' => 'Auditório Jacy Monteiro', 'capacidade' => 80, 'categoria_id' => $categorias['auditorio']],
            ['nome' => 'B01', 'capacidade' => 70, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'B02', 'capacidade' => 70, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'B03', 'capacidade' => 80, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'B05', 'capacidade' => 150, 'categoria_id' => $categorias['nobre']],
            ['nome' => 'B06', 'capacidade' => 70, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'B07', 'capacidade' => 20, 'categoria_id' => $categorias['nobre']],
            ['nome' => 'B09', 'capacidade' => 100, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'B10', 'capacidade' => 90, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'B16', 'capacidade' => 100, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'B101', 'capacidade' => 100, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'B138', 'capacidade' => 30, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'B139', 'capacidade' => 60, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'B142', 'capacidade' => 60, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'B143', 'capacidade' => 60, 'categoria_id' => $categorias['padrao']],
            ['nome' => 'B144', 'capacidade' => 80, 'categoria_id' => $categorias['nobre']],
        ];

        foreach ($salas as $sala) {
            Sala::create($sala);
        }

        $this->associarRecursos();
    }

    private function associarRecursos()
    {
        $recursoIds = [
            'terreo' => \App\Models\Recurso::where('nome', 'Térreo')->first()->id,
            'primeiro' => \App\Models\Recurso::where('nome', '1º Andar')->first()->id,
            'segundo' => \App\Models\Recurso::where('nome', '2º Andar')->first()->id,
            'ar' => \App\Models\Recurso::where('nome', 'Ar Condicionado')->first()->id,
            'video' => \App\Models\Recurso::where('nome', 'Vídeo Conferência')->first()->id,
        ];

        $associacoes = [
            'A132' => ['primeiro', 'ar'],
            'Sala Elza Gomide' => ['primeiro', 'ar'],
            'A241' => ['segundo'],
            'A242' => ['segundo'],
            'A243' => ['segundo'],
            'A249' => ['segundo', 'ar'],
            'A252' => ['segundo'],
            'A259' => ['segundo'],
            'A266' => ['segundo'],
            'A267' => ['segundo'],
            'A268' => ['segundo'],
            'Auditório Antonio Gilioli' => ['segundo', 'ar', 'video'],
            'Auditório Jacy Monteiro' => ['terreo', 'ar', 'video'],
            'B01' => ['terreo', 'ar'],
            'B02' => ['terreo', 'ar'],
            'B03' => ['terreo', 'ar'],
            'B05' => ['terreo', 'ar'],
            'B06' => ['terreo'],
            'B07' => ['terreo', 'ar', 'video'],
            'B09' => ['terreo', 'ar'],
            'B10' => ['terreo', 'ar'],
            'B16' => ['terreo', 'ar'],
            'B101' => ['primeiro', 'ar'],
            'B138' => ['primeiro'],
            'B139' => ['primeiro', 'ar'],
            'B142' => ['primeiro'],
            'B143' => ['primeiro', 'ar'],
            'B144' => ['primeiro', 'ar', 'video'],
        ];

        foreach ($associacoes as $nomeSala => $recursos) {
            $sala = Sala::where('nome', $nomeSala)->first();
            if ($sala) {
                $recursosParaAssociar = [];
                foreach ($recursos as $recursoKey) {
                    $recursosParaAssociar[] = $recursoIds[$recursoKey];
                }
                $sala->recursos()->attach($recursosParaAssociar);
            }
        }
    }
}
