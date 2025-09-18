<?php

namespace App\Console\Commands;

use App\Models\Categoria;
use App\Models\PeriodoLetivo;
use App\Models\Restricao;
use App\Models\Sala;
use App\Rules\TipoRestricaoRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SetRoomRestrictionsBulkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salas:set-restriction-bulk
                            {--type= : Tipo de restrição (FIXA|AUTO|PERIODO_LETIVO|NENHUMA)}
                            {--value= : Valor da restrição (data, dias ou ID do período letivo)}
                            {--category=* : IDs das categorias para filtrar (opcional)}
                            {--force : Pula a confirmação interativa}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Atualiza em massa o tipo de restrição de data para múltiplas salas';

    /**
     * Tipos de restrição válidos
     */
    private const VALID_TYPES = ['FIXA', 'AUTO', 'PERIODO_LETIVO', 'NENHUMA'];

    /**
     * Estatísticas da operação
     */
    private array $stats = [
        'processed' => 0,
        'updated' => 0,
        'created' => 0,
        'errors' => 0
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏢 Gerenciamento em Massa de Restrições de Salas');
        $this->line('');

        // Validar entradas
        $validationResult = $this->validateInputs();
        if (!$validationResult['valid']) {
            $this->displayValidationErrors($validationResult['errors']);
            return 1;
        }

        // Obter salas afetadas
        $salas = $this->getAffectedRooms();
        if ($salas->isEmpty()) {
            $this->warn('⚠️  Nenhuma sala encontrada com os critérios especificados.');
            return 0;
        }

        // Confirmar operação
        if (!$this->option('force') && !$this->confirmOperation($salas)) {
            $this->comment('❌ Operação cancelada pelo usuário.');
            return 0;
        }

        // Executar atualizações
        $this->executeUpdates($salas);

        // Exibir relatório final
        $this->displayFinalReport();

        return 0;
    }

    /**
     * Valida os parâmetros de entrada
     */
    private function validateInputs(): array
    {
        $type = $this->option('type');
        $value = $this->option('value');
        $categories = $this->option('category');

        $errors = [];

        // Validar tipo
        if (!$type) {
            $errors[] = 'O parâmetro --type é obrigatório.';
        } elseif (!in_array($type, self::VALID_TYPES)) {
            $errors[] = 'Tipo inválido. Valores aceitos: ' . implode(', ', self::VALID_TYPES);
        }

        // Validar valor baseado no tipo
        if ($type && $type !== 'NENHUMA') {
            if ($value === null || $value === '') {
                $errors[] = "O parâmetro --value é obrigatório para o tipo '{$type}'.";
            } else {
                $valueValidation = $this->validateValueForType($type, $value);
                if (!$valueValidation['valid']) {
                    $errors = array_merge($errors, $valueValidation['errors']);
                }
            }
        } elseif ($type === 'NENHUMA' && $value !== null && $value !== '') {
            $this->comment('💡 O valor será ignorado para o tipo NENHUMA.');
        }

        // Validar categorias
        if (!empty($categories)) {
            $categoryValidation = $this->validateCategories($categories);
            if (!$categoryValidation['valid']) {
                $errors = array_merge($errors, $categoryValidation['errors']);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Valida o valor baseado no tipo de restrição
     */
    private function validateValueForType(string $type, string $value): array
    {
        $errors = [];

        switch ($type) {
            case 'FIXA':
                if (!$this->isValidDate($value)) {
                    $errors[] = "Para tipo FIXA, o valor deve ser uma data no formato AAAA-MM-DD.";
                }
                break;

            case 'AUTO':
                if (!is_numeric($value) || (int)$value <= 0) {
                    $errors[] = "Para tipo AUTO, o valor deve ser um número inteiro positivo de dias.";
                }
                break;

            case 'PERIODO_LETIVO':
                if (!is_numeric($value) || !PeriodoLetivo::find((int)$value)) {
                    $errors[] = "Para tipo PERIODO_LETIVO, o valor deve ser um ID válido de período letivo.";
                }
                break;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Valida se uma string é uma data válida no formato Y-m-d
     */
    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Valida os IDs das categorias
     */
    private function validateCategories(array $categoryIds): array
    {
        $errors = [];

        foreach ($categoryIds as $categoryId) {
            if (!is_numeric($categoryId)) {
                $errors[] = "ID de categoria inválido: '{$categoryId}'. Deve ser um número.";
                continue;
            }

            if (!Categoria::find((int)$categoryId)) {
                $errors[] = "Categoria com ID {$categoryId} não encontrada.";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Obtém as salas afetadas pela operação
     */
    private function getAffectedRooms()
    {
        $query = Sala::with('categoria');

        $categories = $this->option('category');
        if (!empty($categories)) {
            $query->whereIn('categoria_id', array_map('intval', $categories));
        }

        return $query->get();
    }

    /**
     * Solicita confirmação do usuário
     */
    private function confirmOperation($salas): bool
    {
        $type = $this->option('type');
        $value = $this->option('value');
        $categories = $this->option('category');

        $this->line('');
        $this->info('📋 Resumo da Operação:');

        $this->table(['Campo', 'Valor'], [
            ['Tipo de Restrição', $type],
            ['Valor', $value ?: 'N/A'],
            ['Salas Afetadas', $salas->count()],
            ['Filtro por Categoria', !empty($categories) ? 'IDs: ' . implode(', ', $categories) : 'Todas as categorias'],
        ]);

        if (!empty($categories)) {
            $categorias = Categoria::whereIn('id', $categories)->pluck('nome', 'id');
            $this->line('');
            $this->comment('📂 Categorias selecionadas:');
            foreach ($categorias as $id => $nome) {
                $this->line("  • ID {$id}: {$nome}");
            }
        }

        $this->line('');
        return $this->confirm('Deseja continuar com a operação?', false);
    }

    /**
     * Executa as atualizações em massa
     */
    private function executeUpdates($salas): void
    {
        $this->line('');
        $this->info('🚀 Executando atualizações...');

        $progressBar = $this->output->createProgressBar($salas->count());
        $progressBar->start();

        DB::transaction(function () use ($salas, $progressBar) {
            foreach ($salas as $sala) {
                try {
                    $this->updateRoomRestriction($sala);
                    $this->stats['processed']++;
                } catch (\Exception $e) {
                    $this->stats['errors']++;
                    // Log error but continue processing
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->line('');
    }

    /**
     * Atualiza a restrição de uma sala específica
     */
    private function updateRoomRestriction(Sala $sala): void
    {
        $type = $this->option('type');
        $value = $this->option('value');

        // Preparar dados para atualização
        $updateData = $this->prepareUpdateData($type, $value);

        // Verificar se já existe restrição
        $restricao = Restricao::where('sala_id', $sala->id)->first();

        if ($restricao) {
            $restricao->update($updateData);
            $this->stats['updated']++;
        } else {
            $updateData['sala_id'] = $sala->id;
            Restricao::create($updateData);
            $this->stats['created']++;
        }
    }

    /**
     * Prepara os dados para atualização baseado no tipo
     */
    private function prepareUpdateData(string $type, ?string $value): array
    {
        $data = [
            'tipo_restricao' => $type,
            'data_limite' => null,
            'dias_limite' => null,
            'periodo_letivo_id' => null,
        ];

        switch ($type) {
            case 'FIXA':
                $data['data_limite'] = $value;
                break;
            case 'AUTO':
                $data['dias_limite'] = (int)$value;
                break;
            case 'PERIODO_LETIVO':
                $data['periodo_letivo_id'] = (int)$value;
                break;
            case 'NENHUMA':
                // Manter todos os valores como null
                break;
        }

        return $data;
    }

    /**
     * Exibe o relatório final da operação
     */
    private function displayFinalReport(): void
    {
        $this->line('');
        $this->info('✅ Operação concluída!');
        $this->line('');

        $this->table(['Métrica', 'Quantidade'], [
            ['Salas processadas', $this->stats['processed']],
            ['Restrições atualizadas', $this->stats['updated']],
            ['Restrições criadas', $this->stats['created']],
            ['Erros encontrados', $this->stats['errors']],
        ]);

        if ($this->stats['errors'] > 0) {
            $this->line('');
            $this->warn("⚠️  {$this->stats['errors']} erros foram encontrados durante o processamento.");
            $this->comment('💡 Verifique os logs para mais detalhes.');
        }

        $this->line('');
        $this->comment('✨ Todas as restrições foram atualizadas com sucesso!');
    }

    /**
     * Exibe erros de validação
     */
    private function displayValidationErrors(array $errors): void
    {
        $this->line('');
        $this->error('❌ Erros de validação encontrados:');
        foreach ($errors as $error) {
            $this->error("  • {$error}");
        }
        $this->line('');
        $this->comment('💡 Use php artisan help salas:set-restriction-bulk para ver exemplos de uso.');
    }
}