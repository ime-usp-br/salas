<?php

namespace App\Rules;

use App\Models\Reserva;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Validation\Rule;

class verifyRoomAvailability implements Rule
{
    private $reserva;
    private $id;
    private $conflicts = '';
    private $n = 0;
    private $message = 0;
    private $quantidade_de_reservas = 1;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($reserva, $id)
    {
        $this->reserva = $reserva;
        $this->id = $id;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value)
    { 
        // o campo $value é o dia/mês/ano da reserva 
        $this->check($value);

        if ($this->reserva->repeat_days && $this->reserva->repeat_until) {
            // Parse initial date with format detection
            try {
                $inicio = Carbon::createFromFormat('d/m/Y', $value);
            } catch (\Exception $e) {
                try {
                    $inicio = Carbon::createFromFormat('Y-m-d', $value);
                } catch (\Exception $e2) {
                    $inicio = Carbon::parse($value);
                }
            }
            
            // Parse repeat_until date
            try {
                $fim = Carbon::createFromFormat('d/m/Y', $this->reserva->repeat_until);
            } catch (\Exception $e) {
                try {
                    $fim = Carbon::createFromFormat('Y-m-d', $this->reserva->repeat_until);
                } catch (\Exception $e2) {
                    $fim = Carbon::parse($this->reserva->repeat_until);
                }
            }

            // array (objeto) com todos os dias entre as datas
            $period = CarbonPeriod::between($inicio, $fim);

            foreach ($period as $date) {
                // Vamos passar por todos dias, mas só validar e criar a reserva nos dias da semana marcados em repeat_days
                if (in_array($date->dayOfWeek, $this->reserva->repeat_days)) {
                    $this->check($date->format('Y-m-d')); // Use Y-m-d format for consistency
                    $this->quantidade_de_reservas++;
                }
            }
        }

        if($this->quantidade_de_reservas > 300){
            $this->message = "Reservas não foram criadas! porque ultrapassam 300 reservas, 
                              diminua o intervalo das reservas e tente novamente.";
            return false;
        }

        if ($this->n != 0) {
            $this->message = "Reserva não foi criada porque conflita com: <ul>{$this->conflicts}</ul>";
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }

    private function check($day)
    {
        // 0. ignorar na validação as reservas filhas
        $filhas = []; // caso de novas reservas
        if(Reserva::find($this->id) != null){
            $filhas = Reserva::find($this->id)->children()->pluck('id')->toArray();
        }

        // 1. Vamos pegar as reservas que existem para o mesmo dia e mesmo horário
        // Try to parse different date formats
        try {
            // First try d/m/Y format (traditional format)
            $data = Carbon::createFromFormat('d/m/Y', $day);
        } catch (\Exception $e) {
            try {
                // Then try Y-m-d format (API format)
                $data = Carbon::createFromFormat('Y-m-d', $day);
            } catch (\Exception $e2) {
                // If both fail, try parsing as a generic date
                $data = Carbon::parse($day);
            }
        }
        
        // Enhanced business validation: exclude rejected reservations from conflicts
        $reservas = Reserva::whereDate('data', '=', $data)
            ->where('sala_id', $this->reserva->sala_id)
            ->where('status', '!=', 'rejeitada') // Only consider approved and pending reservations
            ->get();

        // 2. Se não há reserva alguma na data e sala em questão, podemos cadastrar
        if ($reservas->isEmpty()) {
            return true;
        }

        // 3. Se há conflitos vamos montar a string $conflicts indicando-os
        // Use the same data format detection for time creation
        $dayFormatted = $data->format('d/m/Y');
        $inicio = Carbon::createFromFormat('d/m/Y H:i', $dayFormatted.' '.$this->reserva->horario_inicio);
        $fim = Carbon::createFromFormat('d/m/Y H:i', $dayFormatted.' '.$this->reserva->horario_fim);

        $desejado = CarbonPeriod::between($inicio, $fim);

        foreach ($reservas as $reserva) {
            $period = CarbonPeriod::between($reserva->inicio, $reserva->fim);
            if ($period->overlaps($desejado)) {
                // vamos ignorar a própria reserva
                if ($this->id != $reserva->id and !in_array($reserva->id,$filhas)) {
                    $this->conflicts .= "<li><a href='/reservas/{$reserva->id}'>$reserva->nome</a></li>";
                    ++$this->n;
                }
            }
        }
    }
}
