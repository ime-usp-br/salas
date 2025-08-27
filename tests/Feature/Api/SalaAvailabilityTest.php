<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Finalidade;
use App\Models\Reserva;
use App\Models\Sala;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SalaAvailabilityTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private Sala $sala;
    private Finalidade $finalidade;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $categoria = Categoria::factory()->create();
        $this->sala = Sala::factory()->create(['categoria_id' => $categoria->id]);
        $this->finalidade = Finalidade::factory()->create();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function test_can_check_sala_availability_for_period()
    {
        $dataInicio = Carbon::tomorrow()->format('Y-m-d');
        $dataFim = Carbon::tomorrow()->addDays(2)->format('Y-m-d');
        
        $queryParams = http_build_query([
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'horario_inicio' => '09:00',
            'horario_fim' => '11:00'
        ]);
        
        $response = $this->getJson('/api/v1/salas/' . $this->sala->id . '/availability?' . $queryParams);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'sala' => ['id', 'nome'],
                    'periodo' => [
                        'data_inicio',
                        'data_fim', 
                        'horario_inicio',
                        'horario_fim'
                    ],
                    'disponibilidade' => [
                        '*' => [
                            'data',
                            'disponivel',
                            'conflitos'
                        ]
                    ],
                    'resumo' => [
                        'total_dias',
                        'dias_disponiveis',
                        'dias_ocupados'
                    ]
                ]
            ]);
    }

    /** @test */
    public function test_shows_conflicts_when_sala_has_reservations()
    {
        $dataTest = Carbon::tomorrow()->format('Y-m-d');
        
        // Create a reservation that conflicts
        Reserva::factory()->create([
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'user_id' => $this->user->id,
            'data' => $dataTest,
            'horario_inicio' => '09:30',
            'horario_fim' => '10:30',
            'status' => 'aprovada',
            'nome' => 'Reunião de Teste'
        ]);
        
        $queryParams = http_build_query([
            'data_inicio' => $dataTest,
            'data_fim' => $dataTest,
            'horario_inicio' => '09:00',
            'horario_fim' => '11:00'
        ]);
        
        $response = $this->getJson('/api/v1/salas/' . $this->sala->id . '/availability?' . $queryParams);

        $response->assertStatus(200);
        
        $responseData = $response->json('data');
        $this->assertFalse($responseData['disponibilidade'][0]['disponivel']);
        $this->assertNotEmpty($responseData['disponibilidade'][0]['conflitos']);
        $this->assertEquals(1, $responseData['resumo']['dias_ocupados']);
        $this->assertEquals(0, $responseData['resumo']['dias_disponiveis']);
    }

    /** @test */
    public function test_validation_requires_all_parameters()
    {
        $response = $this->getJson('/api/v1/salas/' . $this->sala->id . '/availability');

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'message',
                'details'
            ])
            ->assertJson([
                'error' => 'Validation failed',
                'message' => 'Dados inválidos fornecidos.'
            ]);
            
        // Check that validation details contain expected fields
        $details = $response->json('details');
        $this->assertArrayHasKey('data_inicio', $details);
        $this->assertArrayHasKey('data_fim', $details);
        $this->assertArrayHasKey('horario_inicio', $details);
        $this->assertArrayHasKey('horario_fim', $details);
    }

    /** @test */
    public function test_validation_data_fim_must_be_after_or_equal_data_inicio()
    {
        $queryParams = http_build_query([
            'data_inicio' => '2024-02-10',
            'data_fim' => '2024-02-09', // Before data_inicio
            'horario_inicio' => '09:00',
            'horario_fim' => '11:00'
        ]);
        
        $response = $this->getJson('/api/v1/salas/' . $this->sala->id . '/availability?' . $queryParams);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Validation failed',
                'message' => 'Dados inválidos fornecidos.'
            ]);
            
        $details = $response->json('details');
        $this->assertArrayHasKey('data_fim', $details);
    }

    /** @test */
    public function test_validation_horario_fim_must_be_after_horario_inicio()
    {
        $dataInicio = Carbon::tomorrow()->format('Y-m-d');
        
        $queryParams = http_build_query([
            'data_inicio' => $dataInicio,
            'data_fim' => $dataInicio,
            'horario_inicio' => '11:00',
            'horario_fim' => '09:00' // Before horario_inicio
        ]);
        
        $response = $this->getJson('/api/v1/salas/' . $this->sala->id . '/availability?' . $queryParams);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Validation failed',
                'message' => 'Dados inválidos fornecidos.'
            ]);
            
        $details = $response->json('details');
        $this->assertArrayHasKey('horario_fim', $details);
    }

    /** @test */
    public function test_validation_date_format_must_be_Y_m_d()
    {
        $queryParams = http_build_query([
            'data_inicio' => '10/02/2024', // Wrong format
            'data_fim' => '11/02/2024', // Wrong format
            'horario_inicio' => '09:00',
            'horario_fim' => '11:00'
        ]);
        
        $response = $this->getJson('/api/v1/salas/' . $this->sala->id . '/availability?' . $queryParams);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Validation failed',
                'message' => 'Dados inválidos fornecidos.'
            ]);
            
        $details = $response->json('details');
        $this->assertArrayHasKey('data_inicio', $details);
        $this->assertArrayHasKey('data_fim', $details);
    }

    /** @test */
    public function test_validation_time_format_must_be_H_i()
    {
        $dataInicio = Carbon::tomorrow()->format('Y-m-d');
        
        $queryParams = http_build_query([
            'data_inicio' => $dataInicio,
            'data_fim' => $dataInicio,
            'horario_inicio' => '9:00 AM', // Wrong format
            'horario_fim' => '11:00 AM' // Wrong format
        ]);
        
        $response = $this->getJson('/api/v1/salas/' . $this->sala->id . '/availability?' . $queryParams);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Validation failed',
                'message' => 'Dados inválidos fornecidos.'
            ]);
            
        $details = $response->json('details');
        $this->assertArrayHasKey('horario_inicio', $details);
        $this->assertArrayHasKey('horario_fim', $details);
    }

    /** @test */
    public function test_returns_404_for_nonexistent_sala()
    {
        $dataInicio = Carbon::tomorrow()->format('Y-m-d');
        
        $queryParams = http_build_query([
            'data_inicio' => $dataInicio,
            'data_fim' => $dataInicio,
            'horario_inicio' => '09:00',
            'horario_fim' => '11:00'
        ]);
        
        $response = $this->getJson('/api/v1/salas/999/availability?' . $queryParams);

        $response->assertStatus(404);
    }

    /** @test */
    public function test_ignores_non_approved_reservations()
    {
        $dataTest = Carbon::tomorrow()->format('Y-m-d');
        
        // Create reservations with different statuses
        Reserva::factory()->create([
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'user_id' => $this->user->id,
            'data' => $dataTest,
            'horario_inicio' => '09:30',
            'horario_fim' => '10:30',
            'status' => 'pendente', // Not approved
            'nome' => 'Reunião Pendente'
        ]);

        Reserva::factory()->create([
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'user_id' => $this->user->id,
            'data' => $dataTest,
            'horario_inicio' => '14:00',
            'horario_fim' => '15:00',
            'status' => 'rejeitada', // Rejected
            'nome' => 'Reunião Rejeitada'
        ]);
        
        $queryParams = http_build_query([
            'data_inicio' => $dataTest,
            'data_fim' => $dataTest,
            'horario_inicio' => '09:00',
            'horario_fim' => '16:00'
        ]);
        
        $response = $this->getJson('/api/v1/salas/' . $this->sala->id . '/availability?' . $queryParams);

        $response->assertStatus(200);
        
        $responseData = $response->json('data');
        $this->assertTrue($responseData['disponibilidade'][0]['disponivel']);
        $this->assertEmpty($responseData['disponibilidade'][0]['conflitos']);
        $this->assertEquals(1, $responseData['resumo']['dias_disponiveis']);
        $this->assertEquals(0, $responseData['resumo']['dias_ocupados']);
    }

    /** @test */
    public function test_handles_multiple_days_correctly()
    {
        $dataInicio = Carbon::tomorrow();
        $dataFim = $dataInicio->copy()->addDays(3);
        
        // Create reservation for second day
        Reserva::factory()->create([
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'user_id' => $this->user->id,
            'data' => $dataInicio->copy()->addDay()->format('Y-m-d'),
            'horario_inicio' => '10:00',
            'horario_fim' => '11:00',
            'status' => 'aprovada',
            'nome' => 'Reunião Dia 2'
        ]);
        
        $queryParams = http_build_query([
            'data_inicio' => $dataInicio->format('Y-m-d'),
            'data_fim' => $dataFim->format('Y-m-d'),
            'horario_inicio' => '09:00',
            'horario_fim' => '12:00'
        ]);
        
        $response = $this->getJson('/api/v1/salas/' . $this->sala->id . '/availability?' . $queryParams);

        $response->assertStatus(200);
        
        $responseData = $response->json('data');
        $this->assertEquals(4, $responseData['resumo']['total_dias']);
        $this->assertEquals(3, $responseData['resumo']['dias_disponiveis']);
        $this->assertEquals(1, $responseData['resumo']['dias_ocupados']);
        
        // Check that second day shows conflict
        $this->assertFalse($responseData['disponibilidade'][1]['disponivel']);
        $this->assertNotEmpty($responseData['disponibilidade'][1]['conflitos']);
    }
}