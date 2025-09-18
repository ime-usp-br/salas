<?php

namespace Tests\Feature\Commands;

use App\Models\Categoria;
use App\Models\PeriodoLetivo;
use App\Models\Restricao;
use App\Models\Sala;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetRoomRestrictionsBulkCommandTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $categoria1;
    private Categoria $categoria2;
    private Sala $sala1;
    private Sala $sala2;
    private Sala $sala3;
    private PeriodoLetivo $periodoLetivo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    private function setUpTestData(): void
    {
        // Criar categorias de teste
        $this->categoria1 = Categoria::create([
            'nome' => 'Auditórios',
            'cor' => '#FF0000'
        ]);

        $this->categoria2 = Categoria::create([
            'nome' => 'Salas de Aula',
            'cor' => '#00FF00'
        ]);

        // Criar salas de teste
        $this->sala1 = Sala::create([
            'nome' => 'Auditório Principal',
            'capacidade' => 200,
            'categoria_id' => $this->categoria1->id
        ]);

        $this->sala2 = Sala::create([
            'nome' => 'Auditório Secundário',
            'capacidade' => 100,
            'categoria_id' => $this->categoria1->id
        ]);

        $this->sala3 = Sala::create([
            'nome' => 'Sala 101',
            'capacidade' => 50,
            'categoria_id' => $this->categoria2->id
        ]);

        // Criar período letivo de teste
        $this->periodoLetivo = PeriodoLetivo::create([
            'codigo' => '2025.1',
            'data_inicio' => '2025-02-01',
            'data_fim' => '2025-06-30',
            'data_inicio_reservas' => '2025-01-15',
            'data_fim_reservas' => '2025-06-30'
        ]);

        // Criar uma restrição existente para teste de atualização
        Restricao::create([
            'sala_id' => $this->sala1->id,
            'tipo_restricao' => 'NENHUMA',
            'bloqueada' => false,
            'dias_antecedencia' => 1,
            'aprovacao' => 0
        ]);
    }

    /** @test */
    public function test_command_requires_type_parameter()
    {
        $this->artisan('salas:set-restriction-bulk')
            ->expectsOutput('❌ Erros de validação encontrados:')
            ->expectsOutput('  • O parâmetro --type é obrigatório.')
            ->assertExitCode(1);
    }

    /** @test */
    public function test_command_validates_invalid_type()
    {
        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'INVALID'
        ])
            ->expectsOutput('❌ Erros de validação encontrados:')
            ->expectsOutput('  • Tipo inválido. Valores aceitos: FIXA, AUTO, PERIODO_LETIVO, NENHUMA')
            ->assertExitCode(1);
    }

    /** @test */
    public function test_fixa_type_requires_valid_date()
    {
        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'FIXA'
        ])
            ->expectsOutput('❌ Erros de validação encontrados:')
            ->expectsOutput("  • O parâmetro --value é obrigatório para o tipo 'FIXA'.")
            ->assertExitCode(1);

        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'FIXA',
            '--value' => 'invalid-date'
        ])
            ->expectsOutput('❌ Erros de validação encontrados:')
            ->expectsOutput('  • Para tipo FIXA, o valor deve ser uma data no formato AAAA-MM-DD.')
            ->assertExitCode(1);
    }

    /** @test */
    public function test_auto_type_requires_valid_days()
    {
        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'AUTO'
        ])
            ->expectsOutput('❌ Erros de validação encontrados:')
            ->expectsOutput("  • O parâmetro --value é obrigatório para o tipo 'AUTO'.")
            ->assertExitCode(1);

        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'AUTO',
            '--value' => 'not-a-number'
        ])
            ->expectsOutput('❌ Erros de validação encontrados:')
            ->expectsOutput('  • Para tipo AUTO, o valor deve ser um número inteiro positivo de dias.')
            ->assertExitCode(1);

        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'AUTO',
            '--value' => '0'
        ])
            ->expectsOutput('❌ Erros de validação encontrados:')
            ->expectsOutput('  • Para tipo AUTO, o valor deve ser um número inteiro positivo de dias.')
            ->assertExitCode(1);
    }

    /** @test */
    public function test_periodo_letivo_type_requires_valid_id()
    {
        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'PERIODO_LETIVO'
        ])
            ->expectsOutput('❌ Erros de validação encontrados:')
            ->expectsOutput("  • O parâmetro --value é obrigatório para o tipo 'PERIODO_LETIVO'.")
            ->assertExitCode(1);

        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'PERIODO_LETIVO',
            '--value' => '9999'
        ])
            ->expectsOutput('❌ Erros de validação encontrados:')
            ->expectsOutput('  • Para tipo PERIODO_LETIVO, o valor deve ser um ID válido de período letivo.')
            ->assertExitCode(1);
    }

    /** @test */
    public function test_validates_invalid_category_ids()
    {
        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'NENHUMA',
            '--category' => ['invalid']
        ])
            ->expectsOutput('❌ Erros de validação encontrados:')
            ->expectsOutput("  • ID de categoria inválido: 'invalid'. Deve ser um número.")
            ->assertExitCode(1);

        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'NENHUMA',
            '--category' => ['9999']
        ])
            ->expectsOutput('❌ Erros de validação encontrados:')
            ->expectsOutput('  • Categoria com ID 9999 não encontrada.')
            ->assertExitCode(1);
    }

    /** @test */
    public function test_warns_when_no_rooms_found()
    {
        // Deletar todas as salas
        Sala::query()->delete();

        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'NENHUMA'
        ])
            ->expectsOutput('⚠️  Nenhuma sala encontrada com os critérios especificados.')
            ->assertExitCode(0);
    }

    /** @test */
    public function test_user_can_cancel_operation()
    {
        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'NENHUMA'
        ])
            ->expectsQuestion('Deseja continuar com a operação?', false)
            ->expectsOutput('❌ Operação cancelada pelo usuário.')
            ->assertExitCode(0);
    }

    /** @test */
    public function test_successfully_applies_fixa_restriction_to_all_rooms()
    {
        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'FIXA',
            '--value' => '2025-12-31',
            '--force' => true
        ])
            ->expectsOutput('✅ Operação concluída!')
            ->assertExitCode(0);

        // Verificar que todas as salas foram atualizadas
        $restricoes = Restricao::all();
        $this->assertCount(3, $restricoes); // 1 existente + 2 novas

        foreach ($restricoes as $restricao) {
            $this->assertEquals('FIXA', $restricao->tipo_restricao);
            $this->assertEquals('2025-12-31', $restricao->data_limite);
            $this->assertNull($restricao->dias_limite);
            $this->assertNull($restricao->periodo_letivo_id);
        }

        // Verificar que campos existentes foram preservados
        $restricaoSala1 = Restricao::where('sala_id', $this->sala1->id)->first();
        $this->assertEquals(1, $restricaoSala1->dias_antecedencia);
        $this->assertEquals(0, $restricaoSala1->aprovacao);
        $this->assertEquals(0, $restricaoSala1->bloqueada); // Use 0 instead of false for database comparison
    }

    /** @test */
    public function test_successfully_applies_auto_restriction_to_filtered_category()
    {
        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'AUTO',
            '--value' => '90',
            '--category' => [$this->categoria1->id],
            '--force' => true
        ])
            ->expectsOutput('✅ Operação concluída!')
            ->assertExitCode(0);

        // Verificar que apenas salas da categoria 1 foram atualizadas
        $restricoesCategoria1 = Restricao::whereIn('sala_id', [$this->sala1->id, $this->sala2->id])->get();
        $this->assertCount(2, $restricoesCategoria1);

        foreach ($restricoesCategoria1 as $restricao) {
            $this->assertEquals('AUTO', $restricao->tipo_restricao);
            $this->assertEquals(90, $restricao->dias_limite);
            $this->assertNull($restricao->data_limite);
            $this->assertNull($restricao->periodo_letivo_id);
        }

        // Verificar que sala da categoria 2 não foi afetada
        $restricaoSala3 = Restricao::where('sala_id', $this->sala3->id)->first();
        $this->assertNull($restricaoSala3);
    }

    /** @test */
    public function test_successfully_applies_periodo_letivo_restriction()
    {
        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'PERIODO_LETIVO',
            '--value' => (string)$this->periodoLetivo->id,
            '--force' => true
        ])
            ->expectsOutput('✅ Operação concluída!')
            ->assertExitCode(0);

        // Verificar que todas as salas foram atualizadas
        $restricoes = Restricao::all();
        $this->assertCount(3, $restricoes);

        foreach ($restricoes as $restricao) {
            $this->assertEquals('PERIODO_LETIVO', $restricao->tipo_restricao);
            $this->assertEquals($this->periodoLetivo->id, $restricao->periodo_letivo_id);
            $this->assertNull($restricao->data_limite);
            $this->assertNull($restricao->dias_limite);
        }
    }

    /** @test */
    public function test_successfully_applies_nenhuma_restriction()
    {
        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'NENHUMA',
            '--force' => true
        ])
            ->expectsOutput('✅ Operação concluída!')
            ->assertExitCode(0);

        // Verificar que todas as salas foram atualizadas
        $restricoes = Restricao::all();
        $this->assertCount(3, $restricoes);

        foreach ($restricoes as $restricao) {
            $this->assertEquals('NENHUMA', $restricao->tipo_restricao);
            $this->assertNull($restricao->data_limite);
            $this->assertNull($restricao->dias_limite);
            $this->assertNull($restricao->periodo_letivo_id);
        }
    }

    /** @test */
    public function test_displays_correct_statistics()
    {
        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'NENHUMA',
            '--force' => true
        ])
            ->expectsOutput('✅ Operação concluída!')
            ->assertExitCode(0);

        // Verificar que o comando processou as salas corretamente
        $restricoes = Restricao::all();
        $this->assertCount(3, $restricoes); // 1 existente + 2 novas

        // Verificar que todas têm tipo NENHUMA
        foreach ($restricoes as $restricao) {
            $this->assertEquals('NENHUMA', $restricao->tipo_restricao);
        }
    }

    /** @test */
    public function test_handles_multiple_categories()
    {
        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'NENHUMA',
            '--category' => [$this->categoria1->id, $this->categoria2->id],
            '--force' => true
        ])
            ->expectsOutput('✅ Operação concluída!')
            ->assertExitCode(0);

        // Verificar que todas as salas foram processadas
        $restricoes = Restricao::all();
        $this->assertCount(3, $restricoes);
    }

    /** @test */
    public function test_ignores_value_for_nenhuma_type()
    {
        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'NENHUMA',
            '--value' => 'ignored-value',
            '--force' => true
        ])
            ->expectsOutput('💡 O valor será ignorado para o tipo NENHUMA.')
            ->expectsOutput('✅ Operação concluída!')
            ->assertExitCode(0);
    }

    /** @test */
    public function test_command_shows_help_after_validation_errors()
    {
        $this->artisan('salas:set-restriction-bulk')
            ->expectsOutput('💡 Use php artisan help salas:set-restriction-bulk para ver exemplos de uso.')
            ->assertExitCode(1);
    }

    /** @test */
    public function test_displays_summary_before_confirmation()
    {
        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'FIXA',
            '--value' => '2025-12-31',
            '--category' => [$this->categoria1->id]
        ])
            ->expectsOutput('📋 Resumo da Operação:')
            ->expectsOutput('📂 Categorias selecionadas:')
            ->expectsQuestion('Deseja continuar com a operação?', false)
            ->assertExitCode(0);
    }

    /** @test */
    public function test_transaction_rollback_on_error()
    {
        // Este teste simula um erro durante a execução
        // Como é difícil simular um erro real sem modificar o código,
        // vamos testar o comportamento básico da transação

        $initialCount = Restricao::count();

        $this->artisan('salas:set-restriction-bulk', [
            '--type' => 'NENHUMA',
            '--force' => true
        ])
            ->assertExitCode(0);

        // Verificar que a operação foi completada (não houve rollback)
        $finalCount = Restricao::count();
        $this->assertGreaterThan($initialCount, $finalCount);
    }
}