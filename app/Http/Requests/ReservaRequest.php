<?php

namespace App\Http\Requests;

use App\Models\Sala;
use App\Rules\verifyRoomAvailability;
use Illuminate\Support\Facades\Gate;
use App\Rules\RestricoesSalaRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class ReservaRequest extends FormRequest
{
    /**
     * Indicates if the validator should stop on the first rule failure.
     *
     * @var bool
     */
    protected $stopOnFirstFailure = true;

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

        /*
         * A validação da disponibilidade será customizada
         */
        if ($this->method() == 'PATCH' || $this->method() == 'PUT') {
            $id = $this->reserva->id;
        } else {
            $id = 0;
        }


        $rules = [
            'nome' => 'required',
            'finalidade_id' => 'required|integer',
            'descricao' => 'nullable',
            'repeat_until' => ['required_with:repeat_days', 'nullable', 'date_format:d/m/Y'],
            'repeat_days.*' => 'integer|between:0,7',
            'data' => ['bail', 'required', 'date_format:d/m/Y', new verifyRoomAvailability($this, $id)],
            'sala_id' => ['required', Rule::in(Sala::pluck('id')->toArray()), new RestricoesSalaRule($this) ],
            'tipo_responsaveis' => 'required'
        ];

        // Conditional validation: either global times OR per-day times are required
        $hasRepeatDays = !empty($this->repeat_days) && is_array($this->repeat_days);
        $hasDayTimes = !empty($this->day_times) && is_array($this->day_times);

        if ($hasRepeatDays && $hasDayTimes) {
            // Using per-day times - validate each selected day
            foreach ($this->repeat_days as $day) {
                if (isset($this->day_times[$day])) {
                    $rules["day_times.{$day}.start"] = 'required|date_format:G:i';
                    $rules["day_times.{$day}.end"] = 'required|date_format:G:i|after:day_times.' . $day . '.start';
                }
            }
            // Global times are optional when using per-day times but still validated if provided
            $rules['horario_inicio'] = 'nullable|date_format:G:i';
            $rules['horario_fim'] = 'nullable|date_format:G:i|after:horario_inicio';
        } else {
            // Using global times - standard validation
            $rules['horario_inicio'] = 'required|date_format:G:i';
            $rules['horario_fim'] = 'required|date_format:G:i|after:horario_inicio';
        }

        $sala = Sala::find($this->sala_id);
        if(!is_null($sala) && !Gate::allows('responsavel', $sala)){
            array_push($rules['data'], 'after_or_equal:today');
            $date_today = Carbon::createFromFormat('d/m/Y', date('d/m/Y'));
            $date_input = Carbon::createFromFormat('d/m/Y', $this->data);
            if($date_input->eq($date_today)) {
                $rules['horario_inicio'] .= 'after:'. date('G:i');
            }
        }

        // adiciona à $rules eventuais campos extras obrigatórios
        $extras = config('salas.reservaCamposExtras');
        if (!empty($extras))
            foreach ($extras as $campo)
                $rules['extras.' . Str::slug($campo, '_')] = 'required';

        return $rules;
    }

    public function messages()
    {
        $messages = [
            'nome.required' => 'O título não pode ficar em branco.',
            'data.required' => 'A data não pode ficar em branco.',
            'data.date_format' => 'A data deve ser válida e inserida no formato dia/mês/ano.',
            'horario_inicio.required' => 'O horário de início não pode ficar em branco.',
            'horario_fim.required' => 'O horário de fim não pode ficar em branco.',
            'horario_inicio.date_format' => 'Digite o horário no formato 0:00. Exemplo: 9:00',
            'horario_fim.date_format' => 'Digite o horário no formato 0:00. Exemplo: 9:00',
            'horario_fim.after' => 'O horário de fim deve ser posterior ao horário de início.',
            'sala_id.required' => 'Selecione uma sala.',
            'repeat_until.required_with' => 'Selecione uma data para o fim da repetição.',
            'repeat_until.date_format' => 'A data de repetição deve ser válida e inserida no formato dia/mês/ano.',
            'data.after_or_equal' => 'Não é possível fazer reservas em dias passados.',
            'horario_inicio.after' => 'Não é possível fazer reservas em um horário passado.',
            'day_times.*.start.required' => 'O horário de início é obrigatório para os dias selecionados.',
            'day_times.*.end.required' => 'O horário de fim é obrigatório para os dias selecionados.',
            'day_times.*.start.date_format' => 'Digite o horário no formato 0:00. Exemplo: 9:00',
            'day_times.*.end.date_format' => 'Digite o horário no formato 0:00. Exemplo: 11:00',
            'day_times.*.end.after' => 'O horário de fim deve ser posterior ao horário de início.',
        ];

        // adiciona à $messages eventuais campos extras obrigatórios
        $extras = config('salas.reservaCamposExtras');
        if (!empty($extras))
            foreach ($extras as $campo)
                $messages['extras.' . Str::slug($campo, '_') . '.required'] = 'O campo ' . $campo . ' não pode ficar em branco.';

        return $messages;
    }
}
