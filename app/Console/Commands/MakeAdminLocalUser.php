<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class MakeAdminLocalUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin-local-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria um usuário administrador local com role admin para acesso via Laravel Sanctum';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Criação de Usuário Administrador Local');
        $this->line('');
        $this->comment('💡 Este usuário terá role admin e não usa senha única USP');
        $this->line('');

        // Coletar dados do usuário
        $data = $this->collectUserData();

        // Validar dados
        $validation = $this->validateUserData($data);
        if ($validation['failed']) {
            $this->error('❌ Dados inválidos:');
            foreach ($validation['errors'] as $error) {
                $this->error("  • $error");
            }
            return 1;
        }

        // Garantir que role admin existe
        $this->ensureAdminRoleExists();

        // Criar usuário
        $user = $this->createUser($data);

        // Atribuir role admin
        $this->assignAdminRole($user);

        // Exibir resultados
        $this->displayResults($user);

        return 0;
    }

    /**
     * Coleta os dados do usuário interativamente
     */
    private function collectUserData(): array
    {
        $data = [];
        
        // Nome
        $data['name'] = $this->ask('👤 Nome do usuário');
        
        // Email
        $data['email'] = $this->ask('📧 Email');
        
        // Senha (oculta)
        $data['password'] = $this->secret('🔒 Senha (mínimo 6 caracteres)');
        $confirmPassword = $this->secret('🔒 Confirme a senha');
        
        if ($data['password'] !== $confirmPassword) {
            $this->error('❌ As senhas não coincidem!');
            return $this->collectUserData();
        }
        
        return $data;
    }

    /**
     * Valida os dados coletados
     */
    private function validateUserData(array $data): array
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return [
                'failed' => true,
                'errors' => $validator->errors()->all()
            ];
        }

        return ['failed' => false];
    }

    /**
     * Cria o usuário no banco
     */
    private function createUser(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            // Não definimos codpes - usuário não usa senha única USP
        ]);
    }

    /**
     * Garante que o role admin existe no sistema
     */
    private function ensureAdminRoleExists(): void
    {
        try {
            $adminRole = Role::firstOrCreate(
                ['name' => 'admin'],
                ['guard_name' => 'web']
            );

            if ($adminRole->wasRecentlyCreated) {
                $this->info('✨ Role "admin" criada no sistema');
            } else {
                $this->comment('✓ Role "admin" já existe no sistema');
            }
        } catch (\Exception $e) {
            $this->error('❌ Erro ao verificar/criar role admin: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Atribui o role admin ao usuário
     */
    private function assignAdminRole(User $user): void
    {
        try {
            $user->assignRole('admin');
            $this->info('✓ Role "admin" atribuída ao usuário');
        } catch (\Exception $e) {
            $this->error('❌ Erro ao atribuir role admin: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Exibe os resultados da criação
     */
    private function displayResults(User $user): void
    {
        $this->line('');
        $this->info('✅ Usuário administrador criado com sucesso!');
        $this->line('');
        
        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID', $user->id],
                ['Nome', $user->name],
                ['Email', $user->email],
                ['Role', 'admin'],
                ['Criado em', $user->created_at->format('d/m/Y H:i:s')],
            ]
        );
        
        $this->line('');
        $this->info('🔐 Como usar com Laravel Sanctum:');
        $this->line('1. Faça login via POST /api/v1/auth/token com email e senha');
        $this->line('2. Use o token retornado nas requisições seguintes');
        $this->line('3. Este usuário tem permissões administrativas completas');
        
        $this->line('');
        $this->comment('💡 Exemplo de autenticação:');
        $this->line('# Fazer login e obter token:');
        $this->line('curl -X POST http://localhost:8000/api/v1/auth/token \\');
        $this->line('     -H "Content-Type: application/json" \\');
        $this->line('     -d \'{"email":"' . $user->email . '","password":"[sua_senha]"}\'');
        $this->line('');
        $this->line('# Usar token em operações administrativas:');
        $this->line('curl -H "Authorization: Bearer [token_aqui]" \\');
        $this->line('     -H "Content-Type: application/json" \\');
        $this->line('     -X POST -d \'{"sala_id":1,"finalidade_id":7,"nome":"Test","data":"2025-09-04","horario_inicio":"14:00","horario_fim":"16:00","tipo_responsaveis":"eu"}\' \\');
        $this->line('     http://localhost:8000/api/v1/reservas');
    }
}
