<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Finalidade;
use App\Models\Reserva;
use App\Models\Sala;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesAdminUsers;
use Carbon\Carbon;

class SimpleReservaTest extends TestCase
{
    use RefreshDatabase, CreatesAdminUsers;

    public function test_simple_post_request()
    {
        // Create admin user and authenticate
        $this->actingAsAdmin();

        // Create test data
        $categoria = Categoria::factory()->create();
        $sala = Sala::factory()->create(['categoria_id' => $categoria->id]);
        $finalidade = Finalidade::factory()->create();

        $response = $this->postJson('/api/v1/reservas', [
            'nome' => 'Test Simple',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'horario_inicio' => '14:00',
            'horario_fim' => '16:00',
            'sala_id' => $sala->id,
            'finalidade_id' => $finalidade->id,
            'tipo_responsaveis' => 'eu'
        ]);

        $response->assertStatus(201);
    }
}