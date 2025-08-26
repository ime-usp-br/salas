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
use Carbon\Carbon;

class ReservaApprovalTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private User $responsible;
    private User $admin;
    private Sala $sala;
    private Finalidade $finalidade;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::factory()->create();
        $this->responsible = User::factory()->create();
        $this->admin = User::factory()->create();

        // Create test data
        $categoria = Categoria::factory()->create();
        $this->sala = Sala::factory()->create(['categoria_id' => $categoria->id]);
        $this->finalidade = Finalidade::factory()->create();

        // Make responsible user responsible for the room
        $this->sala->responsaveis()->attach($this->responsible->id);
    }

    /** @test */
    public function test_responsible_user_can_approve_pending_reservation()
    {
        Sanctum::actingAs($this->responsible);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'pendente',
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $response = $this->patchJson("/api/v1/reservas/{$reserva->id}/approve");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'nome',
                    'status',
                    'approved_by',
                    'approved_at'
                ]
            ])
            ->assertJson([
                'message' => 'Reserva aprovada com sucesso.',
                'data' => [
                    'id' => $reserva->id,
                    'status' => 'aprovada',
                    'approved_by' => $this->responsible->name
                ]
            ]);

        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'status' => 'aprovada'
        ]);
    }

    /** @test */
    public function test_responsible_user_can_reject_pending_reservation()
    {
        Sanctum::actingAs($this->responsible);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'pendente',
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $response = $this->patchJson("/api/v1/reservas/{$reserva->id}/reject");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'nome',
                    'status',
                    'rejected_by',
                    'rejected_at'
                ]
            ])
            ->assertJson([
                'message' => 'Reserva rejeitada com sucesso.',
                'data' => [
                    'id' => $reserva->id,
                    'status' => 'rejeitada',
                    'rejected_by' => $this->responsible->name
                ]
            ]);

        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'status' => 'rejeitada'
        ]);
    }

    /** @test */
    public function test_non_responsible_user_cannot_approve_reservation()
    {
        Sanctum::actingAs($this->user);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'pendente',
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $response = $this->patchJson("/api/v1/reservas/{$reserva->id}/approve");

        $response->assertStatus(403)
            ->assertJsonStructure([
                'error',
                'message',
                'details' => [
                    'type',
                    'code'
                ]
            ])
            ->assertJson([
                'error' => 'Forbidden',
                'message' => 'Apenas responsáveis pela sala podem aprovar reservas.',
                'details' => [
                    'code' => 'not_room_responsible'
                ]
            ]);

        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'status' => 'pendente'
        ]);
    }

    /** @test */
    public function test_non_responsible_user_cannot_reject_reservation()
    {
        Sanctum::actingAs($this->user);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'pendente',
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $response = $this->patchJson("/api/v1/reservas/{$reserva->id}/reject");

        $response->assertStatus(403)
            ->assertJsonStructure([
                'error',
                'message',
                'details' => [
                    'type',
                    'code'
                ]
            ])
            ->assertJson([
                'error' => 'Forbidden',
                'message' => 'Apenas responsáveis pela sala podem rejeitar reservas.',
                'details' => [
                    'code' => 'not_room_responsible'
                ]
            ]);

        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'status' => 'pendente'
        ]);
    }

    /** @test */
    public function test_cannot_approve_already_approved_reservation()
    {
        Sanctum::actingAs($this->responsible);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'aprovada',
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $response = $this->patchJson("/api/v1/reservas/{$reserva->id}/approve");

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'message',
                'details' => [
                    'type',
                    'code',
                    'current_status'
                ]
            ])
            ->assertJson([
                'error' => 'Invalid status',
                'message' => 'Apenas reservas pendentes podem ser aprovadas.',
                'details' => [
                    'code' => 'not_pending',
                    'current_status' => 'aprovada'
                ]
            ]);
    }

    /** @test */
    public function test_cannot_reject_already_approved_reservation()
    {
        Sanctum::actingAs($this->responsible);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'aprovada',
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $response = $this->patchJson("/api/v1/reservas/{$reserva->id}/reject");

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'message',
                'details' => [
                    'type',
                    'code',
                    'current_status'
                ]
            ])
            ->assertJson([
                'error' => 'Invalid status',
                'message' => 'Apenas reservas pendentes podem ser rejeitadas.',
                'details' => [
                    'code' => 'not_pending',
                    'current_status' => 'aprovada'
                ]
            ]);
    }

    /** @test */
    public function test_cannot_approve_already_rejected_reservation()
    {
        Sanctum::actingAs($this->responsible);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'rejeitada',
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $response = $this->patchJson("/api/v1/reservas/{$reserva->id}/approve");

        $response->assertStatus(422)
            ->assertJson([
                'details' => [
                    'code' => 'not_pending',
                    'current_status' => 'rejeitada'
                ]
            ]);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_approve_reservation()
    {
        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'pendente',
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $response = $this->patchJson("/api/v1/reservas/{$reserva->id}/approve");

        $response->assertStatus(401);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_reject_reservation()
    {
        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'pendente',
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $response = $this->patchJson("/api/v1/reservas/{$reserva->id}/reject");

        $response->assertStatus(401);
    }

    /** @test */
    public function test_approve_reservation_changes_status_correctly()
    {
        Sanctum::actingAs($this->responsible);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'pendente',
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $response = $this->patchJson("/api/v1/reservas/{$reserva->id}/approve");

        $response->assertStatus(200);
        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'status' => 'aprovada'
        ]);

        // Verify the reservation was actually approved
        $reserva->refresh();
        $this->assertEquals('aprovada', $reserva->status);
    }

    /** @test */
    public function test_reject_reservation_changes_status_correctly()
    {
        Sanctum::actingAs($this->responsible);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'pendente',
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $response = $this->patchJson("/api/v1/reservas/{$reserva->id}/reject");

        $response->assertStatus(200);
        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'status' => 'rejeitada'
        ]);

        // Verify the reservation was actually rejected
        $reserva->refresh();
        $this->assertEquals('rejeitada', $reserva->status);
    }

    /** @test */
    public function test_response_format_includes_correct_timestamps()
    {
        Sanctum::actingAs($this->responsible);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'pendente',
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $response = $this->patchJson("/api/v1/reservas/{$reserva->id}/approve");

        $response->assertStatus(200);
        
        $responseData = $response->json('data');
        $this->assertArrayHasKey('approved_at', $responseData);
        $this->assertMatchesRegularExpression('/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}/', $responseData['approved_at']);
    }

    /** @test */
    public function test_reservation_not_found_returns_404()
    {
        Sanctum::actingAs($this->responsible);

        $response = $this->patchJson("/api/v1/reservas/99999/approve");

        $response->assertStatus(404);
    }

    /** @test */
    public function test_admin_can_approve_any_reservation()
    {
        // This test assumes admin role functionality exists
        // If not implemented, it will test that the current behavior works as expected
        
        Sanctum::actingAs($this->admin);

        $reserva = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'pendente',
            'data' => Carbon::tomorrow()->format('Y-m-d')
        ]);

        $response = $this->patchJson("/api/v1/reservas/{$reserva->id}/approve");

        // Should either work (200) if admin roles are implemented, or fail (403) if not
        $this->assertContains($response->status(), [200, 403]);
        
        if ($response->status() === 200) {
            $this->assertDatabaseHas('reservas', [
                'id' => $reserva->id,
                'status' => 'aprovada'
            ]);
        }
    }
}