<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalaLivreRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            // Data inicial é sempre obrigatória
            'data' => 'required|date_format:d/m/Y',
        ];

        // Check if this is a recurring search (both fields must be present to activate)
        $hasRepeatDays = !empty($this->repeat_days) && is_array($this->repeat_days);
        $hasRepeatUntil = !empty($this->repeat_until);

        if ($hasRepeatDays && $hasRepeatUntil) {
            // Recurring search validation - OPTIONAL but if used, must be valid
            $rules['repeat_until'] = 'required|date_format:d/m/Y|after_or_equal:data';
            $rules['repeat_days'] = 'required|array|min:1';
            $rules['repeat_days.*'] = 'integer|between:1,7';

            // Validate per-day times for each selected day
            if (!empty($this->day_times) && is_array($this->day_times)) {
                foreach ($this->repeat_days as $day) {
                    $rules["day_times.{$day}.start"] = 'required|date_format:G:i';
                    $rules["day_times.{$day}.end"] = 'required|date_format:G:i|after:day_times.' . $day . '.start';
                }
            } else {
                // If no per-day times in recurring mode, require global times
                $rules['horario_inicio'] = 'required|date_format:G:i';
                $rules['horario_fim'] = 'required|date_format:G:i|after:horario_inicio';
            }
        } else {
            // Single date search validation (default behavior)
            $rules['horario_inicio'] = 'required|date_format:G:i';
            $rules['horario_fim'] = 'required|date_format:G:i|after:horario_inicio';

            // If only one recurring field is provided, show error
            if ($hasRepeatDays || $hasRepeatUntil) {
                $rules['repeat_until'] = 'required_with:repeat_days|date_format:d/m/Y';
                $rules['repeat_days'] = 'required_with:repeat_until|array|min:1';
            }
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'data.required' => 'A data não pode ficar em branco.',
            'data.date_format' => 'A data deve ser válida e inserida no formato dia/mês/ano.',
            'horario_inicio.required' => 'O horário de início não pode ficar em branco.',
            'horario_fim.required' => 'O horário de fim não pode ficar em branco.',
            'horario_inicio.date_format' => 'Digite o horário de início no formato 0:00. Exemplo: 9:00',
            'horario_fim.date_format' => 'Digite o horário fim no formato 0:00. Exemplo: 9:00',
            'horario_fim.after' => 'Horário fim precisa ser maior que o horário de início.',
            'repeat_until.required' => 'Selecione uma data para o fim da repetição.',
            'repeat_until.date_format' => 'A data de repetição deve ser válida e inserida no formato dia/mês/ano.',
            'repeat_until.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
            'repeat_days.required' => 'Selecione pelo menos um dia da semana.',
            'repeat_days.min' => 'Selecione pelo menos um dia da semana.',
            'day_times.*.start.required' => 'O horário de início é obrigatório para os dias selecionados.',
            'day_times.*.end.required' => 'O horário de fim é obrigatório para os dias selecionados.',
            'day_times.*.start.date_format' => 'Digite o horário no formato 0:00. Exemplo: 9:00',
            'day_times.*.end.date_format' => 'Digite o horário no formato 0:00. Exemplo: 11:00',
            'day_times.*.end.after' => 'O horário de fim deve ser posterior ao horário de início.',
        ];
    }
}
