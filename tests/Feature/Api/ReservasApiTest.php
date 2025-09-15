<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Finalidade;
use App\Models\Reserva;
use App\Models\Sala;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesAdminUsers;
use Carbon\Carbon;

class ReservasApiTest extends TestCase
{
    use RefreshDatabase, WithFaker, CreatesAdminUsers;

    private User $user;
    private User $admin;
    private Sala $sala;
    private Finalidade $finalidade;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users - All need admin role for API access
        $this->user = $this->createAdmin();
        $this->admin = $this->createAdmin();

        // Create test data
        $categoria = Categoria::factory()->create();
        $this->sala = Sala::factory()->create(['categoria_id' => $categoria->id]);
        $this->finalidade = Finalidade::factory()->create();
    }

    /** @test */
    public function test_unauthenticated_user_cannot_create_reserva()
    {
        $response = $this->postJson('/api/v1/reservas', [
            'nome' => 'Test Reserva',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'horario_inicio' => '14:00',
            'horario_fim' => '16:00',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function test_authenticated_user_can_create_reserva()
    {
        Sanctum::actingAs($this->user);

        $reservaData = [
            'nome' => 'Reunião de Teste',
            'descricao' => 'Descrição da reunião de teste',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'horario_inicio' => '14:00', // Within business hours
            'horario_fim' => '16:00', // Within business hours
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu'
        ];

        $response = $this->postJson('/api/v1/reservas', $reservaData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'nome',
                    'sala',
                    'data',
                    'horario_inicio',
                    'horario_fim',
                    'status',
                    'instances_created'
                ]
            ])
            ->assertJson([
                'data' => [
                    'nome' => 'Reunião de Teste',
                    'instances_created' => 1
                ]
            ]);

        $this->assertDatabaseHas('reservas', [
            'nome' => 'Reunião de Teste',
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id
        ]);
    }

    /** @test */
    public function test_create_reserva_with_invalid_data_returns_validation_errors()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/reservas', [
            'nome' => '', // Required field empty
            'data' => 'invalid-date', // Invalid date format
            'horario_inicio' => '25:00', // Invalid time
            'horario_fim' => '13:00', // Before start time
            'sala_id' => 999, // Non-existent sala
            'finalidade_id' => 999, // Non-existent finalidade
            'tipo_responsaveis' => 'invalid' // Invalid enum value
        ]);

        $response->assertStatus(422);
        
        // The response might contain validation errors for various fields
        // depending on which validation rule fails first
        $this->assertTrue($response->status() === 422);
    }

    /** @test */
    public function test_create_recurrent_reserva()
    {
        Sanctum::actingAs($this->user);

        $start_date = Carbon::tomorrow()->next(Carbon::MONDAY);
        $end_date = $start_date->copy()->addWeeks(2);
        
        $reservaData = [
            'nome' => 'Reunião Recorrente',
            'data' => $start_date->format('Y-m-d'),
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu',
            'repeat_days' => [1, 3, 5], // Monday, Wednesday, Friday
            'repeat_until' => $end_date->format('Y-m-d'),
            'day_times' => [
                '1' => ['start' => '10:00', 'end' => '11:00'], // Monday
                '3' => ['start' => '10:00', 'end' => '11:00'], // Wednesday
                '5' => ['start' => '10:00', 'end' => '11:00']  // Friday
            ]
        ];

        $response = $this->postJson('/api/v1/reservas', $reservaData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'nome',
                    'parent_id',
                    'recurrent',
                    'instances_created'
                ]
            ]);

        // Should create multiple instances for the date range
        $this->assertTrue($response->json('data.instances_created') > 1);
        $this->assertTrue($response->json('data.recurrent'));
    }

    /** @test */
    public function test_update_reserva_is_disabled_for_immutability()
    {
        Sanctum::actingAs($this->user);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id
        ]);

        $updateData = [
            'nome' => 'Reunião Atualizada',
            'descricao' => 'Nova descrição'
        ];

        $response = $this->putJson("/api/v1/reservas/{$reserva->id}", $updateData);

        $response->assertStatus(405)
            ->assertJson([
                'error' => 'Method Not Allowed',
                'message' => 'As reservas são imutáveis via API. Para alterar uma reserva, exclua-a e crie uma nova.',
                'details' => [
                    'type' => 'immutability_policy',
                    'code' => 'update_not_allowed',
                    'method' => 'PUT',
                    'suggested_workflow' => [
                        '1. DELETE /api/v1/reservas/{id} - Excluir a reserva existente',
                        '2. POST /api/v1/reservas - Criar uma nova reserva com os dados corretos'
                    ]
                ]
            ]);

        // Ensure the original data remains unchanged
        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'nome' => $reserva->nome,
            'descricao' => $reserva->descricao
        ]);
    }

    /** @test */
    public function test_patch_reserva_is_also_disabled_for_immutability()
    {
        Sanctum::actingAs($this->user);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id
        ]);

        $response = $this->patchJson("/api/v1/reservas/{$reserva->id}", [
            'nome' => 'Tentativa de patch'
        ]);

        $response->assertStatus(405)
            ->assertJson([
                'error' => 'Method Not Allowed',
                'message' => 'As reservas são imutáveis via API. Para alterar uma reserva, exclua-a e crie uma nova.',
                'details' => [
                    'type' => 'immutability_policy',
                    'code' => 'update_not_allowed',
                    'method' => 'PATCH'
                ]
            ]);
    }

    /** @test */
    public function test_even_admin_cannot_update_reserva_due_to_immutability()
    {
        Sanctum::actingAs($this->admin);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id
        ]);

        $response = $this->putJson("/api/v1/reservas/{$reserva->id}", [
            'nome' => 'Editado pelo Admin'
        ]);

        // Even admin cannot update due to immutability policy
        $response->assertStatus(405)
            ->assertJson([
                'error' => 'Method Not Allowed',
                'message' => 'As reservas são imutáveis via API. Para alterar uma reserva, exclua-a e crie uma nova.',
                'details' => [
                    'type' => 'immutability_policy',
                    'code' => 'update_not_allowed',
                    'method' => 'PUT'
                ]
            ]);

        // Ensure the original data remains unchanged
        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'nome' => $reserva->nome
        ]);
    }

    /** @test */
    public function test_user_can_delete_own_reserva()
    {
        Sanctum::actingAs($this->user);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $response = $this->deleteJson("/api/v1/reservas/{$reserva->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'deleted_count',
                    'deleted_reservas'
                ]
            ])
            ->assertJson([
                'data' => [
                    'deleted_count' => 1
                ]
            ]);

        $this->assertDatabaseMissing('reservas', ['id' => $reserva->id]);
    }

    /** @test */
    public function test_user_cannot_delete_other_users_reserva()
    {
        // Create two regular non-admin users
        $otherUser = User::factory()->create(); // No admin role
        $regularUser = User::factory()->create(); // No admin role
        Sanctum::actingAs($regularUser);

        $reserva = Reserva::factory()->create([
            'user_id' => $otherUser->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id
        ]);

        $response = $this->deleteJson("/api/v1/reservas/{$reserva->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('reservas', ['id' => $reserva->id]);
    }

    /** @test */
    public function test_delete_recurrent_reserva_with_purge()
    {
        Sanctum::actingAs($this->user);

        // Create parent and child reservas
        $parentReserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'parent_id' => null,
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $parentReserva->update(['parent_id' => $parentReserva->id]);

        $childReserva1 = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'parent_id' => $parentReserva->id,
            'data' => Carbon::now()->addDays(2)->format('Y-m-d')
        ]);

        $childReserva2 = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'parent_id' => $parentReserva->id,
            'data' => Carbon::now()->addDays(3)->format('Y-m-d')
        ]);

        $response = $this->deleteJson("/api/v1/reservas/{$parentReserva->id}?purge=true");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'deleted_count' => 3 // Parent + 2 children
                ]
            ]);

        $this->assertDatabaseMissing('reservas', ['id' => $parentReserva->id]);
        $this->assertDatabaseMissing('reservas', ['id' => $childReserva1->id]);
        $this->assertDatabaseMissing('reservas', ['id' => $childReserva2->id]);
    }

    /** @test */
    public function test_delete_recurrent_reserva_without_purge_only_deletes_single_instance()
    {
        Sanctum::actingAs($this->user);

        $parentReserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'parent_id' => null,
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $parentReserva->update(['parent_id' => $parentReserva->id]);

        $childReserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'parent_id' => $parentReserva->id,
            'data' => Carbon::now()->addDays(2)->format('Y-m-d')
        ]);

        $response = $this->deleteJson("/api/v1/reservas/{$parentReserva->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'deleted_count' => 1
                ]
            ]);

        $this->assertDatabaseMissing('reservas', ['id' => $parentReserva->id]);
        $this->assertDatabaseHas('reservas', ['id' => $childReserva->id]);
    }

    /** @test */
    public function test_create_reserva_with_past_date_validation()
    {
        // Create regular non-admin user to test date validation  
        $regularUser = User::factory()->create(); // No admin role
        Sanctum::actingAs($regularUser);

        $pastDate = Carbon::yesterday()->format('Y-m-d');

        $response = $this->postJson('/api/v1/reservas', [
            'nome' => 'Reunião do Passado',
            'data' => $pastDate,
            'horario_inicio' => '14:00',
            'horario_fim' => '16:00',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu'
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('details.validation_errors.data.0', function ($message) {
                return str_contains($message, 'passada') || str_contains($message, 'anterior');
            });
    }

    /** @test */
    public function test_create_reserva_validates_time_range()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/reservas', [
            'nome' => 'Reunião Inválida',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'horario_inicio' => '16:00',
            'horario_fim' => '14:00', // End before start
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu'
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('details.validation_errors.horario_fim', function ($errors) {
                return !empty($errors);
            });
    }

    /** @test */
    public function test_api_returns_proper_error_format_on_server_error()
    {
        Sanctum::actingAs($this->user);

        // Force a server error by providing invalid sala_id that will cause foreign key constraint
        $response = $this->postJson('/api/v1/reservas', [
            'nome' => 'Test Error Handling',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'horario_inicio' => '14:00',
            'horario_fim' => '16:00',
            'sala_id' => 99999, // Non-existent but passes validation
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu'
        ]);

        // Should either return validation error (422) or server error (500) with proper format
        $this->assertContains($response->status(), [422, 500]);
        
        if ($response->status() === 500) {
            $response->assertJsonStructure([
                'error',
                'message'
            ]);
        }
    }

    /** @test */
    public function test_create_reserva_with_responsaveis_unidade()
    {
        Sanctum::actingAs($this->user);

        $reservaData = [
            'nome' => 'Reunião com Responsáveis da Unidade',
            'data' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'horario_inicio' => '14:00',
            'horario_fim' => '15:00',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'unidade',
            'responsaveis_unidade' => [123456, 789012]
        ];

        $response = $this->postJson('/api/v1/reservas', $reservaData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'nome',
                    'status',
                    'instances_created'
                ]
            ]);

        $this->assertDatabaseHas('reservas', [
            'nome' => 'Reunião com Responsáveis da Unidade',
            'tipo_responsaveis' => 'unidade'
        ]);
    }

    /** @test */
    public function test_create_reserva_with_responsaveis_externo()
    {
        Sanctum::actingAs($this->user);

        $reservaData = [
            'nome' => 'Reunião com Responsáveis Externos',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'horario_inicio' => '11:00',
            'horario_fim' => '12:00',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'externo',
            'responsaveis_externo' => ['João Silva', 'Maria Santos']
        ];

        $response = $this->postJson('/api/v1/reservas', $reservaData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'nome',
                    'status',
                    'instances_created'
                ]
            ]);

        $this->assertDatabaseHas('reservas', [
            'nome' => 'Reunião com Responsáveis Externos',
            'tipo_responsaveis' => 'externo'
        ]);
    }

    /** @test */
    public function test_validation_fails_when_responsaveis_unidade_missing()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/reservas', [
            'nome' => 'Reunião Inválida',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'horario_inicio' => '14:00',
            'horario_fim' => '16:00',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'unidade'
            // Missing responsaveis_unidade
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['responsaveis_unidade']);
    }

    /** @test */
    public function test_validation_fails_when_responsaveis_externo_missing()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/reservas', [
            'nome' => 'Reunião Inválida',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'horario_inicio' => '14:00',
            'horario_fim' => '16:00',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'externo'
            // Missing responsaveis_externo
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['responsaveis_externo']);
    }

    /** @test */
    public function test_cannot_delete_past_reserva_as_non_admin()
    {
        Sanctum::actingAs($this->user);

        $pastReserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'data' => Carbon::yesterday()->format('Y-m-d')
        ]);

        $response = $this->deleteJson("/api/v1/reservas/{$pastReserva->id}");

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'message',
                'details' => [
                    'type',
                    'code'
                ]
            ])
            ->assertJson([
                'details' => [
                    'code' => 'past_date_restriction'
                ]
            ]);

        $this->assertDatabaseHas('reservas', ['id' => $pastReserva->id]);
    }

    /** @test */
    public function test_enhanced_error_format_on_database_constraint_violation()
    {
        Sanctum::actingAs($this->user);

        // This should trigger a foreign key constraint error
        $response = $this->postJson('/api/v1/reservas', [
            'nome' => 'Test Database Error',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'horario_inicio' => '14:00',
            'horario_fim' => '16:00',
            'sala_id' => 999999, // Non-existent sala
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu'
        ]);

        // Should return validation error
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'sala_id'
                ]
            ])
            ->assertJsonValidationErrors(['sala_id']);
    }

    /** @test */
    public function test_update_reserva_with_responsaveis_is_disabled()
    {
        Sanctum::actingAs($this->user);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu'
        ]);

        $updateData = [
            'tipo_responsaveis' => 'externo',
            'responsaveis_externo' => ['Responsável Externo Atualizado']
        ];

        $response = $this->putJson("/api/v1/reservas/{$reserva->id}", $updateData);

        $response->assertStatus(405)
            ->assertJson([
                'error' => 'Method Not Allowed',
                'message' => 'As reservas são imutáveis via API. Para alterar uma reserva, exclua-a e crie uma nova.',
                'details' => [
                    'type' => 'immutability_policy',
                    'code' => 'update_not_allowed',
                    'method' => 'PUT'
                ]
            ]);

        // Ensure the original data remains unchanged
        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'tipo_responsaveis' => 'eu'
        ]);
    }

    /** @test */
    public function test_enhanced_permission_error_format()
    {
        $otherUser = $this->createAdmin();
        Sanctum::actingAs($this->user);

        $reserva = Reserva::factory()->create([
            'user_id' => $otherUser->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id
        ]);

        $response = $this->deleteJson("/api/v1/reservas/{$reserva->id}");

        $response->assertStatus(403)
            ->assertJsonStructure([
                'error',
                'message',
                'details' => [
                    'type',
                    'code',
                    'user_id',
                    'reservation_owner'
                ]
            ])
            ->assertJson([
                'details' => [
                    'code' => 'unauthorized_access',
                    'user_id' => $this->user->id,
                    'reservation_owner' => $otherUser->id
                ]
            ]);
    }

    /** @test */
    public function test_recurrent_reservation_validation_maximum_period()
    {
        Sanctum::actingAs($this->user);

        $startDate = Carbon::tomorrow()->format('Y-m-d');
        $endDate = Carbon::tomorrow()->addMonths(8)->format('Y-m-d'); // 8 months > 6 months limit

        $response = $this->postJson('/api/v1/reservas', [
            'nome' => 'Reserva Recorrente Longa',
            'data' => $startDate,
            'horario_inicio' => '14:00',
            'horario_fim' => '16:00',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu',
            'repeat_days' => [1, 3, 5],
            'repeat_until' => $endDate
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['repeat_until']);
    }

    /** @test */
    public function test_recurrent_reservation_validation_too_many_instances()
    {
        Sanctum::actingAs($this->user);

        $startDate = Carbon::tomorrow()->format('Y-m-d');
        $endDate = Carbon::tomorrow()->addMonths(5)->format('Y-m-d'); // 5 months with daily repeat = >100 instances

        $response = $this->postJson('/api/v1/reservas', [
            'nome' => 'Reserva Recorrente Excessiva',
            'data' => $startDate,
            'horario_inicio' => '09:00',
            'horario_fim' => '10:00',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu',
            'repeat_days' => [0, 1, 2, 3, 4, 5, 6], // Daily
            'repeat_until' => $endDate
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['repeat_until']);
    }

    /** @test */
    public function test_recurrent_reservation_validation_invalid_repeat_days()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/reservas', [
            'nome' => 'Reserva com Dias Inválidos',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'horario_inicio' => '14:00',
            'horario_fim' => '16:00',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu',
            'repeat_days' => [0, 1, 2, 3, 4, 5, 6, 7], // 8 days > 7 limit
            'repeat_until' => Carbon::tomorrow()->addWeeks(4)->format('Y-m-d')
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['repeat_days']);
    }

    /** @test */
    public function test_enhanced_recurring_reservation_response_format()
    {
        Sanctum::actingAs($this->user);

        $reservaData = [
            'nome' => 'Reserva Semanal',
            'descricao' => 'Reunião de equipe semanal',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'horario_inicio' => '15:00',
            'horario_fim' => '16:00',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu',
            'repeat_days' => [1, 3], // Monday, Wednesday
            'repeat_until' => Carbon::tomorrow()->addWeeks(2)->format('Y-m-d')
        ];

        $response = $this->postJson('/api/v1/reservas', $reservaData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'nome',
                    'descricao',
                    'sala' => ['id', 'nome'],
                    'finalidade' => ['id', 'nome'],
                    'data',
                    'horario_inicio',
                    'horario_fim',
                    'status',
                    'user_id',
                    'created_at',
                    'recurrent',
                    'instances_created',
                    'parent_id',
                    'recurring_details' => [
                        'repeat_days',
                        'repeat_until',
                        'first_date',
                        'last_date'
                    ]
                ],
                'meta' => [
                    'total_reservations',
                    'recurring_series',
                    'success',
                    'date_range' => ['from', 'to']
                ]
            ])
            ->assertJson([
                'data' => [
                    'recurrent' => true,
                ],
                'meta' => [
                    'recurring_series' => true,
                    'success' => true
                ]
            ]);
    }

    /** @test */
    public function test_purge_with_date_range_partial_deletion()
    {
        Sanctum::actingAs($this->user);

        // Create recurring reservations
        $parentReserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);
        $parentReserva->update(['parent_id' => $parentReserva->id]);

        $childReserva1 = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'parent_id' => $parentReserva->id,
            'data' => Carbon::tomorrow()->addDays(3)->format('Y-m-d')
        ]);

        $childReserva2 = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'parent_id' => $parentReserva->id,
            'data' => Carbon::tomorrow()->addDays(7)->format('Y-m-d')
        ]);

        $purgeFromDate = Carbon::tomorrow()->addDays(5)->format('Y-m-d');

        $response = $this->deleteJson("/api/v1/reservas/{$parentReserva->id}?purge=true&purge_from_date={$purgeFromDate}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'deleted_count',
                    'deleted_reservas',
                    'operation_type'
                ],
                'meta' => [
                    'purge_applied',
                    'partial_purge',
                    'success',
                    'purge_from_date'
                ]
            ])
            ->assertJson([
                'data' => [
                    'deleted_count' => 1, // Only childReserva2 should be deleted
                    'operation_type' => 'series_deletion'
                ],
                'meta' => [
                    'purge_applied' => true,
                    'partial_purge' => true,
                    'purge_from_date' => $purgeFromDate
                ]
            ]);

        // Verify correct reservations remain/deleted
        $this->assertDatabaseHas('reservas', ['id' => $parentReserva->id]);
        $this->assertDatabaseHas('reservas', ['id' => $childReserva1->id]);
        $this->assertDatabaseMissing('reservas', ['id' => $childReserva2->id]);
    }

    /** @test */
    public function test_purge_from_date_validation_error()
    {
        Sanctum::actingAs($this->user);

        $parentReserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);
        $parentReserva->update(['parent_id' => $parentReserva->id]);

        $response = $this->deleteJson("/api/v1/reservas/{$parentReserva->id}?purge=true&purge_from_date=invalid-date");

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Validation failed',
                'details' => [
                    'type' => 'invalid_purge_date_format',
                    'code' => 'validation_error'
                ]
            ]);
    }

    /** @test */
    public function test_create_recurring_reserva_with_day_times()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/reservas', [
            'nome' => 'Curso com Horários Distintos',
            'descricao' => 'Curso recorrente com horários diferentes por dia',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu',
            'repeat_days' => [1, 3], // Monday and Wednesday
            'repeat_until' => Carbon::tomorrow()->addWeeks(4)->format('Y-m-d'),
            'day_times' => [
                '1' => ['start' => '08:00', 'end' => '10:00'], // Monday
                '3' => ['start' => '14:00', 'end' => '16:00']  // Wednesday
            ]
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'nome',
                    'sala',
                    'finalidade',
                    'data',
                    'horario_inicio',
                    'horario_fim',
                    'status',
                    'recurrent',
                    'instances_created',
                    'recurring_details' => [
                        'repeat_days',
                        'repeat_until',
                        'day_times_used',
                        'day_times'
                    ]
                ],
                'meta'
            ])
            ->assertJson([
                'data' => [
                    'nome' => 'Curso com Horários Distintos',
                    'recurrent' => true,
                    'recurring_details' => [
                        'repeat_days' => [1, 3],
                        'day_times_used' => true,
                        'day_times' => [
                            '1' => ['start' => '08:00', 'end' => '10:00'],
                            '3' => ['start' => '14:00', 'end' => '16:00']
                        ]
                    ]
                ]
            ]);

        $this->assertTrue($response->json('data.instances_created') > 1);
    }

    /** @test */
    public function test_day_times_validation_requires_all_repeat_days()
    {
        Sanctum::actingAs($this->user);

        // Missing day_times for day 3 (Wednesday)
        $response = $this->postJson('/api/v1/reservas', [
            'nome' => 'Curso Incompleto',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu',
            'repeat_days' => [1, 3], // Monday and Wednesday
            'repeat_until' => Carbon::tomorrow()->addWeeks(2)->format('Y-m-d'),
            'day_times' => [
                '1' => ['start' => '08:00', 'end' => '10:00'] // Missing Wednesday (3)
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['day_times'])
            ->assertJsonFragment([
                'day_times' => ['Horário não definido para Quarta (dia 3) em day_times.']
            ]);
    }

    /** @test */
    public function test_day_times_prohibited_for_single_reservations()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/reservas', [
            'nome' => 'Reunião Única',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'horario_inicio' => '14:00',
            'horario_fim' => '16:00',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu',
            'day_times' => [
                '1' => ['start' => '14:00', 'end' => '16:00']
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['day_times'])
            ->assertJsonFragment([
                'day_times' => ['O campo day_times só pode ser usado em reservas recorrentes (com repeat_days e repeat_until).']
            ]);
    }

    /** @test */
    public function test_single_reservation_response_format()
    {
        Sanctum::actingAs($this->user);

        $reservaData = [
            'nome' => 'Reserva Única',
            'descricao' => 'Reunião pontual',
            'data' => Carbon::tomorrow()->format('Y-m-d'),
            'horario_inicio' => '10:00',
            'horario_fim' => '11:00',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'tipo_responsaveis' => 'eu'
        ];

        $response = $this->postJson('/api/v1/reservas', $reservaData);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'recurrent' => false,
                    'instances_created' => 1
                ],
                'meta' => [
                    'total_reservations' => 1,
                    'recurring_series' => false,
                    'success' => true
                ]
            ])
            ->assertJsonMissing([
                'data' => [
                    'parent_id',
                    'recurring_details'
                ]
            ]);
    }
}
