<?php

namespace App\Http\Requests\Api;

use App\Models\Sala;
use App\Rules\verifyRoomAvailability;
use App\Rules\RestricoesSalaRule;
use App\Rules\ReservationConflictRule;
use App\Rules\ApiReservationConflictRule;
use App\Rules\UserPermissionRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Carbon\Carbon;

class StoreReservaRequest extends FormRequest
{
    /**
     * Indicates if the validator should stop on the first rule failure.
     *
     * @var bool
     */
    protected $stopOnFirstFailure = false;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // API users with valid token can create reservas
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isRecurring = !empty($this->input('repeat_days')) && !empty($this->input('repeat_until'));

        $rules = [
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'data' => [
                'bail',
                'required',
                'date_format:Y-m-d',
                new ApiReservationConflictRule($this, 0)
            ],
            'sala_id' => [
                'required',
                'integer',
                Rule::in(Sala::pluck('id')->toArray()),
                new RestricoesSalaRule($this),
                new UserPermissionRule($this, 'create')
            ],
            'finalidade_id' => 'required|integer|exists:finalidades,id',
            'tipo_responsaveis' => ['required', Rule::in(['eu', 'unidade', 'externo'])],
            'responsaveis_unidade' => 'required_if:tipo_responsaveis,unidade|nullable|array',
            'responsaveis_unidade.*' => 'integer|min:1',
            'responsaveis_externo' => 'required_if:tipo_responsaveis,externo|nullable|array',
            'responsaveis_externo.*' => 'string|max:255',
            'repeat_until' => [
                'required_with:repeat_days',
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:data',
                function ($attribute, $value, $fail) {
                    if ($value && $this->input('data')) {
                        try {
                            $startDate = Carbon::createFromFormat('Y-m-d', $this->input('data'));
                            $endDate = Carbon::createFromFormat('Y-m-d', $value);

                            // Maximum 6 months recurrence period
                            $monthsDiff = $startDate->diffInMonths($endDate);
                            if ($monthsDiff > 6) {
                                $fail('O período de recorrência não pode exceder 6 meses.');
                                return;
                            }

                            // Maximum 100 instances to prevent system overload
                            if ($this->input('repeat_days') && is_array($this->input('repeat_days'))) {
                                $daysCount = count($this->input('repeat_days'));
                                $weeks = $startDate->diffInWeeks($endDate);
                                $estimatedInstances = $daysCount * ($weeks + 1); // +1 to include both start and end weeks

                                if ($estimatedInstances > 100) {
                                    $fail('O padrão de recorrência resultaria em mais de 100 reservas. Reduza o período ou os dias da semana.');
                                    return;
                                }
                            }
                        } catch (\Exception $e) {
                            $fail('Erro ao validar período de recorrência.');
                        }
                    }
                }
            ],
            'repeat_days' => [
                'nullable',
                'array',
                'min:1',
                'max:7'
            ],
            'repeat_days.*' => 'integer|between:0,6',
        ];

        // Handle time fields based on whether it's recurring with day_times or not
        if ($isRecurring) {
            // For recurring reservations, day_times is required
            $rules['day_times'] = [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    $repeatDays = $this->input('repeat_days', []);

                    if (!is_array($value)) {
                        $fail('O campo day_times deve ser um objeto com os horários de cada dia.');
                        return;
                    }

                    // Validate that all repeat_days have corresponding day_times entries
                    foreach ($repeatDays as $day) {
                        if (!isset($value[$day])) {
                            $dayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                            $fail("Horário não definido para {$dayNames[$day]} (dia {$day}) em day_times.");
                            return;
                        }

                        $dayTime = $value[$day];
                        if (!isset($dayTime['start']) || !isset($dayTime['end'])) {
                            $dayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                            $fail("Horário de início (start) e fim (end) são obrigatórios para {$dayNames[$day]} em day_times.");
                            return;
                        }

                        // Validate time format
                        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $dayTime['start'])) {
                            $dayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                            $fail("Formato de horário de início inválido para {$dayNames[$day]}. Use HH:MM (ex: 08:00).");
                            return;
                        }

                        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $dayTime['end'])) {
                            $dayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                            $fail("Formato de horário de fim inválido para {$dayNames[$day]}. Use HH:MM (ex: 16:00).");
                            return;
                        }

                        // Validate that end time is after start time
                        try {
                            $start = Carbon::createFromFormat('H:i', $dayTime['start']);
                            $end = Carbon::createFromFormat('H:i', $dayTime['end']);

                            if ($end->lte($start)) {
                                $dayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                                $fail("O horário de fim deve ser posterior ao horário de início para {$dayNames[$day]}.");
                                return;
                            }
                        } catch (\Exception $e) {
                            $fail('Erro ao validar horários em day_times.');
                            return;
                        }
                    }
                }
            ];

            // For recurring reservations, horario_inicio and horario_fim are optional (will be ignored)
            $rules['horario_inicio'] = 'nullable|date_format:G:i';
            $rules['horario_fim'] = 'nullable|date_format:G:i';
        } else {
            // For single reservations, traditional time validation
            $rules['horario_inicio'] = 'required|date_format:G:i';
            $rules['horario_fim'] = 'required|date_format:G:i|after:horario_inicio';

            // day_times is not allowed for single reservations
            $rules['day_times'] = [
                'prohibited',
                function ($attribute, $value, $fail) {
                    if ($value !== null) {
                        $fail('O campo day_times só pode ser usado em reservas recorrentes (com repeat_days e repeat_until).');
                    }
                }
            ];
        }

        // Validate time restrictions for non-responsaveis
        $sala = Sala::find($this->sala_id);
        if (!is_null($sala) && !Gate::allows('responsavel', $sala)) {
            array_push($rules['data'], 'after_or_equal:today');
            $date_today = Carbon::createFromFormat('Y-m-d', date('Y-m-d'));
            $date_input = Carbon::createFromFormat('Y-m-d', $this->data);
            if ($date_input->eq($date_today)) {
                $rules['horario_inicio'] .= '|after:' . date('G:i');
            }
        }

        // Add validation for extra fields
        $extras = config('salas.reservaCamposExtras');
        if (!empty($extras)) {
            foreach ($extras as $campo) {
                $rules['extras.' . Str::slug($campo, '_')] = 'required';
            }
        }

        return $rules;
    }

    /**
     * Get the validation messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            // Basic field validation messages
            'nome.required' => 'O título da reserva é obrigatório.',
            'nome.max' => 'O título da reserva não pode exceder 255 caracteres.',
            'data.required' => 'A data da reserva é obrigatória.',
            'data.date_format' => 'A data deve estar no formato AAAA-MM-DD (ex: 2024-09-15).',
            'data.after_or_equal' => 'Não é possível fazer reservas em datas passadas.',
            'horario_inicio.required' => 'O horário de início é obrigatório.',
            'horario_inicio.date_format' => 'O horário de início deve estar no formato G:i (ex: 9:00 ou 14:00).',
            'horario_inicio.after' => 'Não é possível fazer reservas em um horário passado.',
            'horario_fim.required' => 'O horário de fim é obrigatório.',
            'horario_fim.date_format' => 'O horário de fim deve estar no formato G:i (ex: 9:00 ou 16:00).',
            'horario_fim.after' => 'O horário de fim deve ser posterior ao horário de início.',
            'sala_id.required' => 'É necessário selecionar uma sala.',
            'sala_id.integer' => 'O ID da sala deve ser um número inteiro.',
            'sala_id.in' => 'A sala selecionada não é válida ou não existe.',
            'finalidade_id.required' => 'É necessário selecionar uma finalidade.',
            'finalidade_id.integer' => 'O ID da finalidade deve ser um número inteiro.',
            'finalidade_id.exists' => 'A finalidade selecionada não existe no sistema.',
            'tipo_responsaveis.required' => 'É necessário especificar o tipo de responsáveis.',
            'tipo_responsaveis.in' => 'O tipo de responsáveis deve ser: "eu", "unidade" ou "externo".',
            
            // Responsaveis validation messages
            'responsaveis_unidade.required_if' => 'É necessário informar pelo menos um responsável da unidade quando tipo_responsaveis for "unidade".',
            'responsaveis_unidade.array' => 'Os responsáveis da unidade devem ser fornecidos como um array.',
            'responsaveis_unidade.*.integer' => 'Cada código de responsável da unidade deve ser um número inteiro.',
            'responsaveis_unidade.*.min' => 'O código do responsável da unidade deve ser maior que zero.',
            'responsaveis_externo.required_if' => 'É necessário informar pelo menos um responsável externo quando tipo_responsaveis for "externo".',
            'responsaveis_externo.array' => 'Os responsáveis externos devem ser fornecidos como um array.',
            'responsaveis_externo.*.string' => 'Cada nome de responsável externo deve ser uma string.',
            'responsaveis_externo.*.max' => 'O nome do responsável externo não pode exceder 255 caracteres.',
            
            // Recurring reservation messages
            'repeat_until.required_with' => 'A data de fim da recorrência é obrigatória quando há repetição (repeat_days informado).',
            'repeat_until.date_format' => 'A data de fim da recorrência deve estar no formato AAAA-MM-DD.',
            'repeat_until.after_or_equal' => 'A data de fim da recorrência deve ser igual ou posterior à data da reserva.',
            'repeat_days.array' => 'Os dias de repetição devem ser fornecidos como um array.',
            'repeat_days.min' => 'É necessário informar pelo menos um dia da semana para repetição.',
            'repeat_days.*.integer' => 'Cada dia de repetição deve ser um número inteiro.',
            'repeat_days.*.between' => 'Os dias de repetição devem estar entre 0 (domingo) e 6 (sábado).',
            'repeat_days.max' => 'Não é possível repetir em mais de 7 dias por semana.',
            
            // Enhanced business validation messages with context
            'sala_id.user_permission_rule' => 'Acesso negado: apenas administradores podem criar reservas via API.',
            'data.reservation_conflict_rule' => 'Conflito de horário: já existe uma reserva para esta sala no horário solicitado.',
            'data.verify_room_availability' => 'Sala não disponível: verifique se a sala não está bloqueada ou se há restrições de horário.',

            // Day times validation messages
            'day_times.required' => 'O campo day_times é obrigatório para reservas recorrentes. Defina os horários de cada dia da semana.',
            'day_times.array' => 'O campo day_times deve ser um objeto JSON com os horários de cada dia.',
            'day_times.min' => 'É necessário definir pelo menos um horário em day_times.',
            'day_times.prohibited' => 'O campo day_times só pode ser usado em reservas recorrentes (com repeat_days e repeat_until).',
        ];

        // Add enhanced messages for extra fields with context
        $extras = config('salas.reservaCamposExtras');
        if (!empty($extras)) {
            $messages['extras.required'] = 'Os campos extras são obrigatórios quando configurados no sistema.';
            foreach ($extras as $campo) {
                $fieldSlug = Str::slug($campo, '_');
                $messages["extras.{$fieldSlug}.required"] = "O campo '{$campo}' é obrigatório. Configure os campos extras no objeto 'extras' da requisição.";
            }
        }

        return $messages;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nome' => 'título da reserva',
            'data' => 'data da reserva',
            'horario_inicio' => 'horário de início',
            'horario_fim' => 'horário de fim',
            'sala_id' => 'sala',
            'finalidade_id' => 'finalidade',
            'tipo_responsaveis' => 'tipo de responsáveis',
            'responsaveis_unidade' => 'responsáveis da unidade',
            'responsaveis_externo' => 'responsáveis externos',
            'repeat_until' => 'data de fim da recorrência',
            'repeat_days' => 'dias de repetição',
            'extras' => 'campos extras',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Convert repeat_days from day names to numbers if needed
        if ($this->has('repeat_days') && is_array($this->repeat_days)) {
            $dayMap = [
                'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                'thursday' => 4, 'friday' => 5, 'saturday' => 6
            ];
            
            $convertedDays = [];
            foreach ($this->repeat_days as $day) {
                if (is_string($day) && isset($dayMap[strtolower($day)])) {
                    $convertedDays[] = $dayMap[strtolower($day)];
                } elseif (is_numeric($day)) {
                    $convertedDays[] = (int) $day; // Let validation handle range checking
                }
            }
            
            $this->merge([
                'repeat_days' => $convertedDays
            ]);
        }
    }
}
