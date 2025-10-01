<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserCategoriaTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Executa as migrações
        $this->artisan('migrate');
    }

    /** @test */
    public function it_can_attach_category_to_user_successfully()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();
        $categoria = Categoria::factory()->create(['nome' => 'Padrão']);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/users/{$targetUser->id}/categorias", [
            'categoria_id' => $categoria->id
        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'message' => 'Categoria associada com sucesso.',
                    'data' => [
                        'user_id' => $targetUser->id,
                        'categoria_id' => $categoria->id,
                        'categoria_nome' => 'Padrão',
                    ]
                ])
                ->assertJsonStructure([
                    'data' => [
                        'user_id',
                        'categoria_id',
                        'categoria_nome',
                        'created_at'
                    ]
                ]);

        // Verifica se a associação foi criada no banco
        $this->assertDatabaseHas('categoria_user', [
            'user_id' => $targetUser->id,
            'categoria_id' => $categoria->id
        ]);
    }

    /** @test */
    public function it_returns_200_when_attaching_existing_category_idempotency()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();
        $categoria = Categoria::factory()->create(['nome' => 'Auditório']);

        // Associa a categoria primeiro
        $targetUser->categorias()->attach($categoria->id);

        Sanctum::actingAs($user);

        // Tenta associar novamente
        $response = $this->postJson("/api/v1/users/{$targetUser->id}/categorias", [
            'categoria_id' => $categoria->id
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Categoria já estava associada ao usuário.',
                    'data' => [
                        'user_id' => $targetUser->id,
                        'categoria_id' => $categoria->id,
                    ]
                ]);

        // Verifica que não há duplicatas
        $count = $targetUser->categorias()->where('categoria_id', $categoria->id)->count();
        $this->assertEquals(1, $count);
    }

    /** @test */
    public function it_can_detach_category_from_user_successfully()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();
        $categoria = Categoria::factory()->create(['nome' => 'Sala Nobre']);

        // Associa a categoria primeiro
        $targetUser->categorias()->attach($categoria->id);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/users/{$targetUser->id}/categorias/{$categoria->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Categoria removida com sucesso.'
                ]);

        // Verifica que a associação foi removida
        $this->assertDatabaseMissing('categoria_user', [
            'user_id' => $targetUser->id,
            'categoria_id' => $categoria->id
        ]);
    }

    /** @test */
    public function it_returns_404_when_detaching_non_existent_association()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();
        $categoria = Categoria::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/users/{$targetUser->id}/categorias/{$categoria->id}");

        $response->assertStatus(404)
                ->assertJson([
                    'error' => 'Not Found',
                    'message' => 'Associação não encontrada.'
                ]);
    }

    /** @test */
    public function it_can_list_user_categories()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();
        $categoria1 = Categoria::factory()->create(['nome' => 'Padrão']);
        $categoria2 = Categoria::factory()->create(['nome' => 'Auditório']);
        $categoria3 = Categoria::factory()->create(['nome' => 'Sala Nobre']);

        // Associa algumas categorias
        $targetUser->categorias()->attach([$categoria1->id, $categoria2->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/users/{$targetUser->id}/categorias");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'nome',
                            ]
                        ]
                    ]
                ])
                ->assertJsonCount(2, 'data.data');
    }

    /** @test */
    public function it_can_sync_categories_for_user()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();
        $categoria1 = Categoria::factory()->create(['nome' => 'Padrão']);
        $categoria2 = Categoria::factory()->create(['nome' => 'Auditório']);
        $categoria3 = Categoria::factory()->create(['nome' => 'Sala Nobre']);

        // Associa categorias iniciais
        $targetUser->categorias()->attach([$categoria1->id, $categoria2->id]);

        Sanctum::actingAs($user);

        // Sincroniza para manter categoria1, remover categoria2, adicionar categoria3
        $response = $this->putJson("/api/v1/users/{$targetUser->id}/categorias", [
            'categoria_ids' => [$categoria1->id, $categoria3->id]
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Categorias sincronizadas com sucesso.',
                ])
                ->assertJsonPath('data.attached.0', $categoria3->id)
                ->assertJsonPath('data.updated', []);

        // Verifica o estado final
        $this->assertDatabaseHas('categoria_user', [
            'user_id' => $targetUser->id,
            'categoria_id' => $categoria1->id
        ]);
        $this->assertDatabaseHas('categoria_user', [
            'user_id' => $targetUser->id,
            'categoria_id' => $categoria3->id
        ]);
        $this->assertDatabaseMissing('categoria_user', [
            'user_id' => $targetUser->id,
            'categoria_id' => $categoria2->id
        ]);
    }

    /** @test */
    public function it_rejects_attach_with_non_existent_category()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/users/{$targetUser->id}/categorias", [
            'categoria_id' => 99999
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'error',
                    'message',
                    'details' => [
                        'validation_errors' => [
                            'categoria_id'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_rejects_attach_without_categoria_id()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/users/{$targetUser->id}/categorias", []);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'error',
                    'message',
                    'details' => [
                        'validation_errors' => [
                            'categoria_id'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_rejects_sync_with_invalid_categoria_ids()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/users/{$targetUser->id}/categorias", [
            'categoria_ids' => [99999, 88888]
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'error',
                    'message',
                    'details' => [
                        'validation_errors'
                    ]
                ]);
    }

    /** @test */
    public function it_rejects_sync_with_empty_array()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/users/{$targetUser->id}/categorias", [
            'categoria_ids' => []
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'error',
                    'message',
                    'details' => [
                        'validation_errors' => [
                            'categoria_ids'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_rejects_unauthenticated_requests_to_attach()
    {
        $targetUser = User::factory()->create();
        $categoria = Categoria::factory()->create();

        $response = $this->postJson("/api/v1/users/{$targetUser->id}/categorias", [
            'categoria_id' => $categoria->id
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_rejects_unauthenticated_requests_to_detach()
    {
        $targetUser = User::factory()->create();
        $categoria = Categoria::factory()->create();

        $response = $this->deleteJson("/api/v1/users/{$targetUser->id}/categorias/{$categoria->id}");

        $response->assertStatus(401);
    }

    /** @test */
    public function it_rejects_unauthenticated_requests_to_list()
    {
        $targetUser = User::factory()->create();

        $response = $this->getJson("/api/v1/users/{$targetUser->id}/categorias");

        $response->assertStatus(401);
    }

    /** @test */
    public function it_rejects_unauthenticated_requests_to_sync()
    {
        $targetUser = User::factory()->create();
        $categoria = Categoria::factory()->create();

        $response = $this->putJson("/api/v1/users/{$targetUser->id}/categorias", [
            'categoria_ids' => [$categoria->id]
        ]);

        $response->assertStatus(401);
    }

    // Note: Testing non-existent users (404) requires proper exception handling
    // Laravel's route model binding will throw ModelNotFoundException
    // which is automatically converted to 404 by Laravel's exception handler
}
