<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Executa as migrações
        $this->artisan('migrate');
    }

    /** @test */
    public function it_can_find_user_by_codpes()
    {
        $authenticatedUser = User::factory()->create();
        $targetUser = User::factory()->create([
            'codpes' => 7654321,
            'name' => 'Maria Santos',
            'email' => 'maria.santos@usp.br'
        ]);

        Sanctum::actingAs($authenticatedUser);

        $response = $this->getJson('/api/v1/users?codpes=7654321');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'codpes',
                        'name',
                        'email',
                        'categorias',
                        'created_at',
                        'updated_at'
                    ]
                ])
                ->assertJsonPath('data.id', $targetUser->id)
                ->assertJsonPath('data.codpes', 7654321)
                ->assertJsonPath('data.name', 'Maria Santos')
                ->assertJsonPath('data.email', 'maria.santos@usp.br');
    }

    /** @test */
    public function it_returns_404_when_user_not_found_by_codpes()
    {
        $authenticatedUser = User::factory()->create();
        Sanctum::actingAs($authenticatedUser);

        $response = $this->getJson('/api/v1/users?codpes=9999999');

        $response->assertStatus(404)
                ->assertJson([
                    'error' => 'Not Found',
                    'message' => 'Usuário não encontrado.'
                ]);
    }

    /** @test */
    public function it_rejects_non_numeric_codpes()
    {
        $authenticatedUser = User::factory()->create();
        Sanctum::actingAs($authenticatedUser);

        $response = $this->getJson('/api/v1/users?codpes=abc123');

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'error',
                    'message',
                    'details' => [
                        'validation_errors' => [
                            'codpes'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_requires_codpes_parameter_for_user_search()
    {
        $authenticatedUser = User::factory()->create();
        Sanctum::actingAs($authenticatedUser);

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'error',
                    'message',
                    'details' => [
                        'validation_errors' => [
                            'codpes'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_includes_categorias_when_finding_user_by_codpes()
    {
        $authenticatedUser = User::factory()->create();
        $targetUser = User::factory()->create(['codpes' => 1122334]);
        $categoria = \App\Models\Categoria::factory()->create(['nome' => 'Auditório']);

        // Associa categoria ao usuário
        $targetUser->categorias()->attach($categoria->id);

        Sanctum::actingAs($authenticatedUser);

        $response = $this->getJson('/api/v1/users?codpes=1122334');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'codpes',
                        'name',
                        'email',
                        'categorias' => [
                            '*' => [
                                'id',
                                'nome'
                            ]
                        ]
                    ]
                ])
                ->assertJsonCount(1, 'data.categorias');
    }

    /** @test */
    public function it_can_create_user_successfully()
    {
        $authenticatedUser = User::factory()->create();
        Sanctum::actingAs($authenticatedUser);

        $userData = [
            'codpes' => 1234567,
            'name' => 'João da Silva',
            'email' => 'joao.silva@usp.br',
            'password' => 'senha12345'
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(201)
                ->assertJson([
                    'message' => 'Usuário criado com sucesso.',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'codpes',
                        'name',
                        'email',
                        'created_at',
                        'updated_at'
                    ]
                ])
                ->assertJsonPath('data.codpes', 1234567)
                ->assertJsonPath('data.name', 'João da Silva')
                ->assertJsonPath('data.email', 'joao.silva@usp.br');

        // Verifica se o usuário foi criado no banco
        $this->assertDatabaseHas('users', [
            'codpes' => 1234567,
            'name' => 'João da Silva',
            'email' => 'joao.silva@usp.br'
        ]);

        // Verifica que a senha foi hasheada
        $user = User::where('codpes', 1234567)->first();
        $this->assertTrue(Hash::check('senha12345', $user->password));
    }

    /** @test */
    public function it_can_create_user_without_password()
    {
        $authenticatedUser = User::factory()->create();
        Sanctum::actingAs($authenticatedUser);

        $userData = [
            'codpes' => 7654321,
            'name' => 'Maria Santos',
            'email' => 'maria.santos@usp.br'
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(201)
                ->assertJson([
                    'message' => 'Usuário criado com sucesso.',
                ])
                ->assertJsonPath('data.codpes', 7654321)
                ->assertJsonPath('data.name', 'Maria Santos');

        // Verifica que o usuário foi criado com senha gerada automaticamente
        $user = User::where('codpes', 7654321)->first();
        $this->assertNotNull($user->password);
        $this->assertNotEmpty($user->password);
    }

    /** @test */
    public function it_can_show_user_details()
    {
        $authenticatedUser = User::factory()->create();
        $targetUser = User::factory()->create([
            'codpes' => 9876543,
            'name' => 'Pedro Oliveira',
            'email' => 'pedro.oliveira@usp.br'
        ]);

        Sanctum::actingAs($authenticatedUser);

        $response = $this->getJson("/api/v1/users/{$targetUser->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'codpes',
                        'name',
                        'email',
                        'categorias',
                        'created_at',
                        'updated_at'
                    ]
                ])
                ->assertJsonPath('data.id', $targetUser->id)
                ->assertJsonPath('data.codpes', 9876543)
                ->assertJsonPath('data.name', 'Pedro Oliveira')
                ->assertJsonPath('data.email', 'pedro.oliveira@usp.br');
    }

    /** @test */
    public function it_rejects_duplicate_codpes()
    {
        $authenticatedUser = User::factory()->create();
        $existingUser = User::factory()->create(['codpes' => 1111111]);

        Sanctum::actingAs($authenticatedUser);

        $userData = [
            'codpes' => 1111111,
            'name' => 'Outro Usuário',
            'email' => 'outro@usp.br'
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'error',
                    'message',
                    'details' => [
                        'validation_errors' => [
                            'codpes'
                        ]
                    ]
                ])
                ->assertJsonPath('details.validation_errors.codpes.0', 'Este número USP (codpes) já está cadastrado no sistema.');
    }

    /** @test */
    public function it_rejects_duplicate_email()
    {
        $authenticatedUser = User::factory()->create();
        $existingUser = User::factory()->create(['email' => 'existente@usp.br']);

        Sanctum::actingAs($authenticatedUser);

        $userData = [
            'codpes' => 2222222,
            'name' => 'Novo Usuário',
            'email' => 'existente@usp.br'
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'error',
                    'message',
                    'details' => [
                        'validation_errors' => [
                            'email'
                        ]
                    ]
                ])
                ->assertJsonPath('details.validation_errors.email.0', 'Este e-mail já está cadastrado no sistema.');
    }

    /** @test */
    public function it_rejects_invalid_email_format()
    {
        $authenticatedUser = User::factory()->create();
        Sanctum::actingAs($authenticatedUser);

        $userData = [
            'codpes' => 3333333,
            'name' => 'Usuário Teste',
            'email' => 'email-invalido'
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'error',
                    'message',
                    'details' => [
                        'validation_errors' => [
                            'email'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_requires_all_mandatory_fields()
    {
        $authenticatedUser = User::factory()->create();
        Sanctum::actingAs($authenticatedUser);

        $response = $this->postJson('/api/v1/users', []);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'error',
                    'message',
                    'details' => [
                        'validation_errors' => [
                            'codpes',
                            'name',
                            'email'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_rejects_name_exceeding_max_length()
    {
        $authenticatedUser = User::factory()->create();
        Sanctum::actingAs($authenticatedUser);

        $userData = [
            'codpes' => 4444444,
            'name' => str_repeat('a', 256), // 256 caracteres (excede o limite de 255)
            'email' => 'teste@usp.br'
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'error',
                    'message',
                    'details' => [
                        'validation_errors' => [
                            'name'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_rejects_password_shorter_than_8_characters()
    {
        $authenticatedUser = User::factory()->create();
        Sanctum::actingAs($authenticatedUser);

        $userData = [
            'codpes' => 5555555,
            'name' => 'Usuário Teste',
            'email' => 'teste@usp.br',
            'password' => '1234567' // 7 caracteres
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'error',
                    'message',
                    'details' => [
                        'validation_errors' => [
                            'password'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_rejects_unauthenticated_requests_to_create_user()
    {
        $userData = [
            'codpes' => 6666666,
            'name' => 'Usuário Não Autenticado',
            'email' => 'naoauth@usp.br'
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_rejects_unauthenticated_requests_to_show_user()
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/v1/users/{$user->id}");

        $response->assertStatus(401);
    }

    // Note: Testing non-existent users (404) requires proper exception handling
    // Laravel's route model binding will throw ModelNotFoundException
    // which is automatically converted to 404 by Laravel's exception handler

    /** @test */
    public function it_includes_categorias_when_showing_user()
    {
        $authenticatedUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $categoria = \App\Models\Categoria::factory()->create(['nome' => 'Padrão']);

        // Associa categoria ao usuário
        $targetUser->categorias()->attach($categoria->id);

        Sanctum::actingAs($authenticatedUser);

        $response = $this->getJson("/api/v1/users/{$targetUser->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'codpes',
                        'name',
                        'email',
                        'categorias' => [
                            '*' => [
                                'id',
                                'nome'
                            ]
                        ]
                    ]
                ])
                ->assertJsonCount(1, 'data.categorias');
    }
}
