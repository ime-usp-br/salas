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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyReservationsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private User $otherUser;
    private Sala $sala;
    private Finalidade $finalidade;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $categoria = Categoria::factory()->create();
        $this->sala = Sala::factory()->create(['categoria_id' => $categoria->id]);
        $this->finalidade = Finalidade::factory()->create();
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_my_reservations()
    {
        $response = $this->getJson('/api/v1/reservas/my');

        $response->assertStatus(401);
    }

    /** @test */
    public function test_authenticated_user_can_get_their_reservations()
    {
        Sanctum::actingAs($this->user);

        // Create reservations for the authenticated user
        Reserva::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'aprovada'
        ]);

        // Create reservation for another user (should not appear)
        Reserva::factory()->create([
            'user_id' => $this->otherUser->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'aprovada'
        ]);

        $response = $this->getJson('/api/v1/reservas/my');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'nome',
                        'descricao',
                        'sala' => ['id', 'nome'],
                        'finalidade' => ['id', 'legenda'],
                        'data',
                        'horario_inicio',
                        'horario_fim',
                        'status',
                        'user' => ['id', 'name', 'email'],
                        'timestamps' => ['created_at', 'updated_at']
                    ]
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next'
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);

        // Should return only the user's reservations
        $this->assertCount(3, $response->json('data'));
        
        // All returned reservations should belong to the authenticated user
        $reservationUserIds = collect($response->json('data'))->pluck('user.id')->unique();
        $this->assertCount(1, $reservationUserIds);
        $this->assertEquals($this->user->id, $reservationUserIds->first());
    }

    /** @test */
    public function test_can_filter_reservations_by_status()
    {
        Sanctum::actingAs($this->user);

        // Create reservations with different statuses
        Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'aprovada'
        ]);

        Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'pendente'
        ]);

        Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'rejeitada'
        ]);

        // Filter by status 'aprovada'
        $response = $this->getJson('/api/v1/reservas/my?status=aprovada');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('aprovada', $response->json('data.0.status'));
    }

    /** @test */
    public function test_can_filter_reservations_by_date()
    {
        Sanctum::actingAs($this->user);

        $targetDate = Carbon::tomorrow()->format('Y-m-d');
        $otherDate = Carbon::tomorrow()->addDay()->format('Y-m-d');

        // Create reservations for different dates
        Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'data' => $targetDate,
            'status' => 'aprovada'
        ]);

        Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'data' => $otherDate,
            'status' => 'aprovada'
        ]);

        // Filter by specific date
        $response = $this->getJson('/api/v1/reservas/my?data=' . $targetDate);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($targetDate, $response->json('data.0.data'));
    }

    /** @test */
    public function test_can_filter_reservations_by_date_range()
    {
        Sanctum::actingAs($this->user);

        $startDate = Carbon::tomorrow();
        $endDate = $startDate->copy()->addDays(2);
        $outsideDate = $startDate->copy()->addDays(5);

        // Create reservations within and outside the range
        Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'data' => $startDate->format('Y-m-d'),
            'status' => 'aprovada'
        ]);

        Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'data' => $startDate->copy()->addDay()->format('Y-m-d'),
            'status' => 'aprovada'
        ]);

        Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'data' => $outsideDate->format('Y-m-d'),
            'status' => 'aprovada'
        ]);

        // Filter by date range
        $response = $this->getJson('/api/v1/reservas/my?data_inicio=' . $startDate->format('Y-m-d') . '&data_fim=' . $endDate->format('Y-m-d'));

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function test_returns_validation_error_for_invalid_date_format()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/reservas/my?data=10/02/2024'); // Wrong format

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Validation failed',
                'message' => 'Formato de data inválido. Use Y-m-d.'
            ]);
    }

    /** @test */
    public function test_returns_validation_error_for_invalid_date_range_format()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/reservas/my?data_inicio=10/02/2024&data_fim=11/02/2024'); // Wrong format

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Validation failed',
                'message' => 'Formato de data inválido para período. Use Y-m-d.'
            ]);
    }

    /** @test */
    public function test_pagination_works_correctly()
    {
        Sanctum::actingAs($this->user);

        // Create 25 reservations
        Reserva::factory()->count(25)->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'aprovada'
        ]);

        // Test first page (default per_page = 15)
        $response = $this->getJson('/api/v1/reservas/my');

        $response->assertStatus(200);
        $this->assertCount(15, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));
        $this->assertEquals(2, $response->json('meta.last_page'));
        $this->assertEquals(25, $response->json('meta.total'));

        // Test second page
        $response = $this->getJson('/api/v1/reservas/my?page=2');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.current_page'));
    }

    /** @test */
    public function test_can_customize_per_page_with_limit()
    {
        Sanctum::actingAs($this->user);

        // Create 30 reservations
        Reserva::factory()->count(30)->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'aprovada'
        ]);

        // Test with custom per_page
        $response = $this->getJson('/api/v1/reservas/my?per_page=20');

        $response->assertStatus(200);
        $this->assertCount(20, $response->json('data'));
        $this->assertEquals(20, $response->json('meta.per_page'));

        // Test maximum limit (should cap at 50)
        $response = $this->getJson('/api/v1/reservas/my?per_page=100');

        $response->assertStatus(200);
        $this->assertEquals(50, $response->json('meta.per_page'));
    }

    /** @test */
    public function test_reservations_are_ordered_by_date_and_time_desc()
    {
        Sanctum::actingAs($this->user);

        $date1 = Carbon::tomorrow()->format('Y-m-d');
        $date2 = Carbon::tomorrow()->addDay()->format('Y-m-d');

        // Create reservations in different order
        $reservation1 = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'data' => $date1,
            'horario_inicio' => '09:00',
            'status' => 'aprovada'
        ]);

        $reservation2 = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'data' => $date2,
            'horario_inicio' => '08:00',
            'status' => 'aprovada'
        ]);

        $reservation3 = Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'data' => $date1,
            'horario_inicio' => '10:00',
            'status' => 'aprovada'
        ]);

        $response = $this->getJson('/api/v1/reservas/my');

        $response->assertStatus(200);
        $reservations = $response->json('data');

        // Should be ordered by date desc, then time desc
        $this->assertEquals($reservation2->id, $reservations[0]['id']); // Later date
        $this->assertEquals($reservation3->id, $reservations[1]['id']); // Same date, later time
        $this->assertEquals($reservation1->id, $reservations[2]['id']); // Same date, earlier time
    }

    /** @test */
    public function test_includes_related_data()
    {
        Sanctum::actingAs($this->user);

        Reserva::factory()->create([
            'user_id' => $this->user->id,
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'status' => 'aprovada'
        ]);

        $response = $this->getJson('/api/v1/reservas/my');

        $response->assertStatus(200);
        $reservation = $response->json('data.0');

        // Check that related data is included
        $this->assertArrayHasKey('sala', $reservation);
        $this->assertArrayHasKey('finalidade', $reservation);
        $this->assertArrayHasKey('user', $reservation);
        $this->assertArrayHasKey('responsaveis', $reservation);

        $this->assertEquals($this->sala->id, $reservation['sala']['id']);
        $this->assertEquals($this->finalidade->id, $reservation['finalidade']['id']);
        $this->assertEquals($this->user->id, $reservation['user']['id']);
    }
}