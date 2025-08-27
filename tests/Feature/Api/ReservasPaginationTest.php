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

class ReservasPaginationTest extends TestCase
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
    public function test_reservas_endpoint_returns_paginated_results()
    {
        $today = Carbon::now()->format('Y-m-d');
        $todayDbFormat = Carbon::now()->format('d/m/Y');

        // Create 25 approved reservations for today
        for ($i = 0; $i < 25; $i++) {
            Reserva::create([
                'nome' => 'Reserva Test ' . ($i + 1),
                'descricao' => 'Descrição da reserva ' . ($i + 1),
                'sala_id' => $this->sala->id,
                'finalidade_id' => $this->finalidade->id,
                'user_id' => $this->user->id,
                'data' => $todayDbFormat,
                'horario_inicio' => sprintf('%02d:00', 8 + ($i % 10)),
                'horario_fim' => sprintf('%02d:00', 9 + ($i % 10)),
                'status' => 'aprovada'
            ]);
        }

        $response = $this->getJson('/api/v1/reservas');

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
                        'status'
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

        // Check default pagination (15 per page)
        $this->assertCount(15, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));
        $this->assertEquals(2, $response->json('meta.last_page'));
        $this->assertEquals(15, $response->json('meta.per_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }

    /** @test */
    public function test_can_navigate_through_pages()
    {
        $today = Carbon::now()->format('Y-m-d');
        $todayDbFormat = Carbon::now()->format('d/m/Y');

        // Create 25 approved reservations for today
        for ($i = 0; $i < 25; $i++) {
            Reserva::create([
                'nome' => 'Reserva Nav ' . ($i + 1),
                'descricao' => 'Descrição da reserva ' . ($i + 1),
                'sala_id' => $this->sala->id,
                'finalidade_id' => $this->finalidade->id,
                'user_id' => $this->user->id,
                'data' => $todayDbFormat,
                'horario_inicio' => sprintf('%02d:00', 8 + ($i % 10)),
                'horario_fim' => sprintf('%02d:00', 9 + ($i % 10)),
                'status' => 'aprovada'
            ]);
        }

        // Test first page
        $response = $this->getJson('/api/v1/reservas?page=1');
        $response->assertStatus(200);
        $this->assertCount(15, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));

        // Test second page
        $response = $this->getJson('/api/v1/reservas?page=2');
        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data')); // Remaining 10 items
        $this->assertEquals(2, $response->json('meta.current_page'));
    }

    /** @test */
    public function test_can_customize_per_page_with_limit()
    {
        $today = Carbon::now()->format('Y-m-d');
        $todayDbFormat = Carbon::now()->format('d/m/Y');

        // Create 30 approved reservations for today
        for ($i = 0; $i < 30; $i++) {
            Reserva::create([
                'nome' => 'Reserva Custom ' . ($i + 1),
                'descricao' => 'Descrição da reserva ' . ($i + 1),
                'sala_id' => $this->sala->id,
                'finalidade_id' => $this->finalidade->id,
                'user_id' => $this->user->id,
                'data' => $todayDbFormat,
                'horario_inicio' => sprintf('%02d:00', 8 + ($i % 12)),
                'horario_fim' => sprintf('%02d:00', 9 + ($i % 12)),
                'status' => 'aprovada'
            ]);
        }

        // Test with custom per_page
        $response = $this->getJson('/api/v1/reservas?per_page=20');

        $response->assertStatus(200);
        $this->assertCount(20, $response->json('data'));
        $this->assertEquals(20, $response->json('meta.per_page'));
        $this->assertEquals(2, $response->json('meta.last_page')); // 30 items / 20 per page = 2 pages

        // Test with smaller per_page
        $response = $this->getJson('/api/v1/reservas?per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(5, $response->json('meta.per_page'));
        $this->assertEquals(6, $response->json('meta.last_page')); // 30 items / 5 per page = 6 pages
    }

    /** @test */
    public function test_per_page_limit_is_capped_at_50()
    {
        $today = Carbon::now()->format('Y-m-d');
        $todayDbFormat = Carbon::now()->format('d/m/Y');

        // Create 100 approved reservations for today
        for ($i = 0; $i < 100; $i++) {
            Reserva::create([
                'nome' => 'Reserva Cap ' . ($i + 1),
                'descricao' => 'Descrição da reserva ' . ($i + 1),
                'sala_id' => $this->sala->id,
                'finalidade_id' => $this->finalidade->id,
                'user_id' => $this->user->id,
                'data' => $todayDbFormat,
                'horario_inicio' => sprintf('%02d:00', 6 + ($i % 16)),
                'horario_fim' => sprintf('%02d:00', 7 + ($i % 16)),
                'status' => 'aprovada'
            ]);
        }

        // Try to request 100 per page, should be capped at 50
        $response = $this->getJson('/api/v1/reservas?per_page=100');

        $response->assertStatus(200);
        $this->assertCount(50, $response->json('data'));
        $this->assertEquals(50, $response->json('meta.per_page'));
        $this->assertEquals(2, $response->json('meta.last_page')); // 100 items / 50 per page = 2 pages
    }

    /** @test */
    public function test_pagination_maintains_filters()
    {
        $today = Carbon::now()->format('Y-m-d');
        $todayDbFormat = Carbon::now()->format('d/m/Y');
        $otherSala = Sala::factory()->create(['categoria_id' => $this->sala->categoria_id]);

        // Create 20 reservations for main sala
        for ($i = 0; $i < 20; $i++) {
            Reserva::create([
                'nome' => 'Reserva Main ' . ($i + 1),
                'descricao' => 'Descrição da reserva ' . ($i + 1),
                'sala_id' => $this->sala->id,
                'finalidade_id' => $this->finalidade->id,
                'user_id' => $this->user->id,
                'data' => $todayDbFormat,
                'horario_inicio' => sprintf('%02d:00', 8 + ($i % 10)),
                'horario_fim' => sprintf('%02d:00', 9 + ($i % 10)),
                'status' => 'aprovada'
            ]);
        }

        // Create 10 reservations for other sala
        for ($i = 0; $i < 10; $i++) {
            Reserva::create([
                'nome' => 'Reserva Other ' . ($i + 1),
                'descricao' => 'Descrição da reserva ' . ($i + 1),
                'sala_id' => $otherSala->id,
                'finalidade_id' => $this->finalidade->id,
                'user_id' => $this->user->id,
                'data' => $todayDbFormat,
                'horario_inicio' => sprintf('%02d:00', 8 + ($i % 8)),
                'horario_fim' => sprintf('%02d:00', 9 + ($i % 8)),
                'status' => 'aprovada'
            ]);
        }

        // Test with sala filter and pagination
        $response = $this->getJson('/api/v1/reservas?sala=' . $this->sala->id . '&per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(20, $response->json('meta.total')); // Only main sala reservations
        $this->assertEquals(2, $response->json('meta.last_page')); // 20 items / 10 per page = 2 pages

        // All reservations should belong to the filtered sala
        $salaIds = collect($response->json('data'))->pluck('sala.id')->unique();
        $this->assertCount(1, $salaIds);
        $this->assertEquals($this->sala->id, $salaIds->first());
    }

    /** @test */
    public function test_pagination_with_finalidade_filter()
    {
        $today = Carbon::now()->format('Y-m-d');
        $todayDbFormat = Carbon::now()->format('d/m/Y');
        $otherFinalidade = Finalidade::factory()->create();

        // Create 15 reservations with main finalidade
        for ($i = 0; $i < 15; $i++) {
            Reserva::create([
                'nome' => 'Reserva Main Fin ' . ($i + 1),
                'descricao' => 'Descrição da reserva ' . ($i + 1),
                'sala_id' => $this->sala->id,
                'finalidade_id' => $this->finalidade->id,
                'user_id' => $this->user->id,
                'data' => $todayDbFormat,
                'horario_inicio' => sprintf('%02d:00', 8 + ($i % 10)),
                'horario_fim' => sprintf('%02d:00', 9 + ($i % 10)),
                'status' => 'aprovada'
            ]);
        }

        // Create 10 reservations with other finalidade
        for ($i = 0; $i < 10; $i++) {
            Reserva::create([
                'nome' => 'Reserva Other Fin ' . ($i + 1),
                'descricao' => 'Descrição da reserva ' . ($i + 1),
                'sala_id' => $this->sala->id,
                'finalidade_id' => $otherFinalidade->id,
                'user_id' => $this->user->id,
                'data' => $todayDbFormat,
                'horario_inicio' => sprintf('%02d:00', 8 + ($i % 8)),
                'horario_fim' => sprintf('%02d:00', 9 + ($i % 8)),
                'status' => 'aprovada'
            ]);
        }

        // Test with finalidade filter and pagination
        $response = $this->getJson('/api/v1/reservas?finalidade=' . $this->finalidade->id . '&per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total')); // Only main finalidade reservations
        $this->assertEquals(2, $response->json('meta.last_page')); // 15 items / 10 per page = 2 pages

        // All reservations should belong to the filtered finalidade
        $finalidadeIds = collect($response->json('data'))->pluck('finalidade.id')->unique();
        $this->assertCount(1, $finalidadeIds);
        $this->assertEquals($this->finalidade->id, $finalidadeIds->first());
    }

    /** @test */
    public function test_pagination_with_date_filter()
    {
        $today = Carbon::now()->format('Y-m-d');
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');
        $todayDbFormat = Carbon::now()->format('d/m/Y');
        $tomorrowDbFormat = Carbon::tomorrow()->format('d/m/Y');

        // Create 20 reservations for today
        for ($i = 0; $i < 20; $i++) {
            Reserva::create([
                'nome' => 'Reserva Today ' . ($i + 1),
                'descricao' => 'Descrição da reserva ' . ($i + 1),
                'sala_id' => $this->sala->id,
                'finalidade_id' => $this->finalidade->id,
                'user_id' => $this->user->id,
                'data' => $todayDbFormat,
                'horario_inicio' => sprintf('%02d:00', 8 + ($i % 10)),
                'horario_fim' => sprintf('%02d:00', 9 + ($i % 10)),
                'status' => 'aprovada'
            ]);
        }

        // Create 15 reservations for tomorrow
        for ($i = 0; $i < 15; $i++) {
            Reserva::create([
                'nome' => 'Reserva Tomorrow ' . ($i + 1),
                'descricao' => 'Descrição da reserva ' . ($i + 1),
                'sala_id' => $this->sala->id,
                'finalidade_id' => $this->finalidade->id,
                'user_id' => $this->user->id,
                'data' => $tomorrowDbFormat,
                'horario_inicio' => sprintf('%02d:00', 8 + ($i % 10)),
                'horario_fim' => sprintf('%02d:00', 9 + ($i % 10)),
                'status' => 'aprovada'
            ]);
        }

        // Test with date filter for tomorrow and pagination
        $response = $this->getJson('/api/v1/reservas?data=' . $tomorrow . '&per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total')); // Only tomorrow's reservations
        $this->assertEquals(2, $response->json('meta.last_page')); // 15 items / 10 per page = 2 pages

        // All reservations should be for tomorrow's date
        $dates = collect($response->json('data'))->pluck('data')->unique();
        $this->assertCount(1, $dates);
        $this->assertEquals($tomorrow, $dates->first());
    }

    /** @test */
    public function test_reservations_are_ordered_by_horario_inicio()
    {
        $today = Carbon::now()->format('Y-m-d');
        $todayDbFormat = Carbon::now()->format('d/m/Y');

        // Create reservations with different start times
        $reservation1 = Reserva::create([
            'nome' => 'Reserva Order 1',
            'descricao' => 'Descrição da reserva 1',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'user_id' => $this->user->id,
            'data' => $todayDbFormat,
            'horario_inicio' => '14:00',
            'horario_fim' => '15:00',
            'status' => 'aprovada'
        ]);

        $reservation2 = Reserva::create([
            'nome' => 'Reserva Order 2',
            'descricao' => 'Descrição da reserva 2',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'user_id' => $this->user->id,
            'data' => $todayDbFormat,
            'horario_inicio' => '09:00',
            'horario_fim' => '10:00',
            'status' => 'aprovada'
        ]);

        $reservation3 = Reserva::create([
            'nome' => 'Reserva Order 3',
            'descricao' => 'Descrição da reserva 3',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'user_id' => $this->user->id,
            'data' => $todayDbFormat,
            'horario_inicio' => '11:00',
            'horario_fim' => '12:00',
            'status' => 'aprovada'
        ]);

        $response = $this->getJson('/api/v1/reservas');

        $response->assertStatus(200);
        $reservations = $response->json('data');

        // Should be ordered by horario_inicio ascending
        $this->assertEquals($reservation2->id, $reservations[0]['id']); // 09:00
        $this->assertEquals($reservation3->id, $reservations[1]['id']); // 11:00
        $this->assertEquals($reservation1->id, $reservations[2]['id']); // 14:00
    }

    /** @test */
    public function test_includes_eager_loaded_relationships()
    {
        $today = Carbon::now()->format('Y-m-d');
        $todayDbFormat = Carbon::now()->format('d/m/Y');

        Reserva::create([
            'nome' => 'Reserva Includes Test',
            'descricao' => 'Descrição da reserva de teste',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'user_id' => $this->user->id,
            'data' => $todayDbFormat,
            'horario_inicio' => '10:00',
            'horario_fim' => '11:00',
            'status' => 'aprovada'
        ]);

        $response = $this->getJson('/api/v1/reservas');

        $response->assertStatus(200);
        $reservation = $response->json('data.0');

        // Check that relationships are included
        $this->assertArrayHasKey('sala', $reservation);
        $this->assertArrayHasKey('finalidade', $reservation);
        $this->assertArrayHasKey('user', $reservation);
        $this->assertArrayHasKey('responsaveis', $reservation);

        $this->assertEquals($this->sala->id, $reservation['sala']['id']);
        $this->assertEquals($this->finalidade->id, $reservation['finalidade']['id']);
        $this->assertEquals($this->user->id, $reservation['user']['id']);
    }

    /** @test */
    public function test_only_shows_approved_reservations()
    {
        $today = Carbon::now()->format('Y-m-d');
        $todayDbFormat = Carbon::now()->format('d/m/Y');

        // Create reservations with different statuses
        Reserva::create([
            'nome' => 'Reserva Aprovada',
            'descricao' => 'Descrição da reserva aprovada',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'user_id' => $this->user->id,
            'data' => $todayDbFormat,
            'horario_inicio' => '10:00',
            'horario_fim' => '11:00',
            'status' => 'aprovada'
        ]);

        Reserva::create([
            'nome' => 'Reserva Pendente',
            'descricao' => 'Descrição da reserva pendente',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'user_id' => $this->user->id,
            'data' => $todayDbFormat,
            'horario_inicio' => '12:00',
            'horario_fim' => '13:00',
            'status' => 'pendente'
        ]);

        Reserva::create([
            'nome' => 'Reserva Rejeitada',
            'descricao' => 'Descrição da reserva rejeitada',
            'sala_id' => $this->sala->id,
            'finalidade_id' => $this->finalidade->id,
            'user_id' => $this->user->id,
            'data' => $todayDbFormat,
            'horario_inicio' => '14:00',
            'horario_fim' => '15:00',
            'status' => 'rejeitada'
        ]);

        $response = $this->getJson('/api/v1/reservas');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('aprovada', $response->json('data.0.status'));
        $this->assertEquals(1, $response->json('meta.total'));
    }

}