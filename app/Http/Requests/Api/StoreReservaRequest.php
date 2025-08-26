<?php

namespace App\Http\Requests\Api;

use App\Models\Sala;
use App\Rules\verifyRoomAvailability;
use App\Rules\RestricoesSalaRule;
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
        $rules = [
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'data' => ['bail', 'required', 'date_format:Y-m-d', new verifyRoomAvailability($this, 0)],
            'horario_inicio' => 'required|date_format:G:i',
            'horario_fim' => 'required|date_format:G:i|after:horario_inicio',
            'sala_id' => ['required', 'integer', Rule::in(Sala::pluck('id')->toArray()), new RestricoesSalaRule($this)],
            'finalidade_id' => 'required|integer|exists:finalidades,id',
            'tipo_responsaveis' => ['required', Rule::in(['eu', 'unidade', 'externo'])],
            'responsaveis_unidade' => 'required_if:tipo_responsaveis,unidade|nullable|array',
            'responsaveis_unidade.*' => 'integer|min:1',
            'responsaveis_externo' => 'required_if:tipo_responsaveis,externo|nullable|array',
            'responsaveis_externo.*' => 'string|max:255',
            'repeat_until' => ['required_with:repeat_days', 'nullable', 'date_format:Y-m-d', 'after_or_equal:data'],
            'repeat_days' => 'nullable|array',
            'repeat_days.*' => 'integer|between:0,6',
        ];

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
            'nome.required' => 'O título da reserva é obrigatório.',
            'nome.max' => 'O título da reserva não pode exceder 255 caracteres.',
            'data.required' => 'A data da reserva é obrigatória.',
            'data.date_format' => 'A data deve estar no formato AAAA-MM-DD (ex: 2024-09-15).',
            'data.after_or_equal' => 'Não é possível fazer reservas em datas passadas.',
            'horario_inicio.required' => 'O horário de início é obrigatório.',
            'horario_inicio.date_format' => 'O horário de início deve estar no formato H:mm (ex: 14:00).',
            'horario_inicio.after' => 'Não é possível fazer reservas em um horário passado.',
            'horario_fim.required' => 'O horário de fim é obrigatório.',
            'horario_fim.date_format' => 'O horário de fim deve estar no formato H:mm (ex: 16:00).',
            'horario_fim.after' => 'O horário de fim deve ser posterior ao horário de início.',
            'sala_id.required' => 'É necessário selecionar uma sala.',
            'sala_id.integer' => 'O ID da sala deve ser um número inteiro.',
            'sala_id.in' => 'A sala selecionada não é válida.',
            'finalidade_id.required' => 'É necessário selecionar uma finalidade.',
            'finalidade_id.integer' => 'O ID da finalidade deve ser um número inteiro.',
            'finalidade_id.exists' => 'A finalidade selecionada não existe.',
            'tipo_responsaveis.required' => 'É necessário especificar o tipo de responsáveis.',
            'tipo_responsaveis.in' => 'O tipo de responsáveis deve ser: eu, unidade ou externo.',
            'responsaveis_unidade.required_if' => 'É necessário informar pelo menos um responsável da unidade.',
            'responsaveis_unidade.array' => 'Os responsáveis da unidade devem ser fornecidos como um array.',
            'responsaveis_unidade.*.integer' => 'Cada código de responsável da unidade deve ser um número inteiro.',
            'responsaveis_unidade.*.min' => 'O código do responsável da unidade deve ser maior que zero.',
            'responsaveis_externo.required_if' => 'É necessário informar pelo menos um responsável externo.',
            'responsaveis_externo.array' => 'Os responsáveis externos devem ser fornecidos como um array.',
            'responsaveis_externo.*.string' => 'Cada nome de responsável externo deve ser uma string.',
            'responsaveis_externo.*.max' => 'O nome do responsável externo não pode exceder 255 caracteres.',
            'repeat_until.required_with' => 'A data de fim da recorrência é obrigatória quando há repetição.',
            'repeat_until.date_format' => 'A data de fim da recorrência deve estar no formato AAAA-MM-DD.',
            'repeat_until.after_or_equal' => 'A data de fim da recorrência deve ser igual ou posterior à data da reserva.',
            'repeat_days.array' => 'Os dias de repetição devem ser fornecidos como um array.',
            'repeat_days.*.integer' => 'Cada dia de repetição deve ser um número inteiro.',
            'repeat_days.*.between' => 'Os dias de repetição devem estar entre 0 (domingo) e 6 (sábado).',
        ];

        // Add messages for extra fields
        $extras = config('salas.reservaCamposExtras');
        if (!empty($extras)) {
            foreach ($extras as $campo) {
                $messages['extras.' . Str::slug($campo, '_') . '.required'] = 'O campo ' . $campo . ' é obrigatório.';
            }
        }

        return $messages;
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
                } elseif (is_numeric($day) && $day >= 0 && $day <= 6) {
                    $convertedDays[] = (int) $day;
                }
            }
            
            $this->merge([
                'repeat_days' => $convertedDays
            ]);
        }
    }
}
