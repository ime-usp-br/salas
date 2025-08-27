<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Executa as migrações incluindo a do Sanctum
        $this->artisan('migrate');
    }

    /** @test */
    public function it_can_create_api_token_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'name' => 'Test User'
        ]);

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'token_name' => 'Test Token'
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'token',
                        'token_name',
                        'user' => [
                            'id',
                            'name',
                            'email'
                        ]
                    ]
                ])
                ->assertJson([
                    'message' => 'Token criado com sucesso',
                    'data' => [
                        'token_name' => 'Test Token',
                        'user' => [
                            'id' => $user->id,
                            'name' => 'Test User',
                            'email' => 'test@example.com'
                        ]
                    ]
                ]);

        // Verifica se o token foi criado no banco
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Test Token'
        ]);
    }

    /** @test */
    public function it_cannot_create_token_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => 'test@example.com',
            'password' => 'wrong-password'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);

        // Verifica que nenhum token foi criado
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id
        ]);
    }

    /** @test */
    public function it_cannot_create_token_with_nonexistent_user()
    {
        $response = $this->postJson('/api/v1/auth/token', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_requires_email_and_password_for_token_creation()
    {
        $response = $this->postJson('/api/v1/auth/token', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'password']);
    }

    /** @test */
    public function it_can_access_protected_endpoint_with_valid_token()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name',
                        'email',
                        'roles',
                        'permissions'
                    ]
                ])
                ->assertJson([
                    'data' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email
                    ]
                ]);
    }

    /** @test */
    public function it_cannot_access_protected_endpoint_without_token()
    {
        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_can_list_user_tokens()
    {
        $user = User::factory()->create();
        
        // Cria alguns tokens
        $token1 = $user->createToken('Token 1');
        $token2 = $user->createToken('Token 2');
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/tokens');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'last_used_at',
                            'created_at'
                        ]
                    ]
                ])
                ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_can_revoke_specific_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Token');
        $tokenId = $token->accessToken->id;
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/auth/tokens/{$tokenId}");

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Token revogado com sucesso',
                    'data' => [
                        'token_name' => 'Test Token'
                    ]
                ]);

        // Verifica se o token foi removido do banco
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId
        ]);
    }

    /** @test */
    public function it_cannot_revoke_token_that_does_not_belong_to_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $token = $user2->createToken('Other User Token');
        $tokenId = $token->accessToken->id;
        
        Sanctum::actingAs($user1);

        $response = $this->deleteJson("/api/v1/auth/tokens/{$tokenId}");

        $response->assertStatus(404)
                ->assertJson([
                    'error' => 'Token não encontrado'
                ]);

        // Verifica que o token ainda existe
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId
        ]);
    }

    /** @test */
    public function it_can_revoke_all_user_tokens()
    {
        $user = User::factory()->create();
        
        // Cria múltiplos tokens
        $token1 = $user->createToken('Token 1');
        $token2 = $user->createToken('Token 2');
        $token3 = $user->createToken('Token 3');
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/auth/tokens');

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Todos os tokens foram revogados com sucesso',
                    'data' => [
                        'tokens_revoked' => 3
                    ]
                ]);

        // Verifica que todos os tokens do usuário foram removidos
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id
        ]);
    }

    /** @test */
    public function it_applies_rate_limiting_to_token_creation()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        // Simula 6 tentativas (acima do limite de 5)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/auth/token', [
                'email' => 'test@example.com',
                'password' => 'wrong-password'
            ]);
        }

        // A 6ª tentativa deve retornar erro de rate limiting (429)
        $response->assertStatus(429);
        
        // Verifica que o header de retry-after está presente
        $this->assertTrue($response->headers->has('Retry-After'));
    }

    /** @test */
    public function it_uses_default_token_name_when_not_provided()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => 'test@example.com',
            'password' => 'password123'
            // Não fornece token_name
        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'data' => [
                        'token_name' => 'API Token'
                    ]
                ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'API Token'
        ]);
    }

    /** @test */
    public function it_can_access_user_info_with_valid_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Token');

        // Testa com token válido
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ])->getJson('/api/v1/auth/user');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name', 
                        'email',
                        'roles',
                        'permissions'
                    ]
                ])
                ->assertJson([
                    'data' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email
                    ]
                ]);
    }
}