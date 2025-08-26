<?php

namespace App\Rules;

use App\Models\Reserva;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Validation\Rule;

class ReservationConflictRule implements Rule
{
    private $request;
    private $reservaId;
    private $message;
    private $conflicts = [];

    public function __construct($request, $reservaId = null)
    {
        $this->request = $request;
        $this->reservaId = $reservaId;
    }

    public function passes($attribute, $value)
    {
        // Skip if we don't have the necessary data
        if (!$this->request->has('sala_id') || !$this->request->has('horario_inicio') || !$this->request->has('horario_fim')) {
            return true;
        }

        $this->conflicts = [];
        
        // Check for conflicts on the primary date
        $this->checkDateConflicts($value);

        // If it's a recurring reservation, check all dates in the series
        if ($this->request->has('repeat_days') && $this->request->has('repeat_until') && 
            !empty($this->request->repeat_days) && !empty($this->request->repeat_until)) {
            $this->checkRecurringConflicts($value);
        }

        if (!empty($this->conflicts)) {
            $this->message = 'Conflitos encontrados com reservas existentes: ' . implode(', ', $this->conflicts);
            return false;
        }

        return true;
    }

    public function message()
    {
        return $this->message;
    }

    private function checkDateConflicts($date)
    {
        try {
            // Parse the input date (API format Y-m-d)
            $inputDate = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Exception $e) {
            return; // Skip validation if date format is invalid
        }

        // Get existing reservations for the same date and room
        $existingReservations = Reserva::whereDate('data', '=', $inputDate)
            ->where('sala_id', $this->request->sala_id)
            ->where('status', '!=', 'rejeitada')
            ->when($this->reservaId, function ($query) {
                return $query->where('id', '!=', $this->reservaId);
            })
            ->get();

        if ($existingReservations->isEmpty()) {
            return;
        }

        // Check for time overlaps
        $this->checkTimeOverlaps($existingReservations, $inputDate);
    }

    private function checkRecurringConflicts($startDate)
    {
        try {
            $start = Carbon::createFromFormat('Y-m-d', $startDate);
            $end = Carbon::createFromFormat('Y-m-d', $this->request->repeat_until);
        } catch (\Exception $e) {
            return; // Skip validation if date format is invalid
        }

        $repeatDays = is_array($this->request->repeat_days) ? $this->request->repeat_days : [];
        $period = CarbonPeriod::between($start, $end);

        foreach ($period as $date) {
            if (in_array($date->dayOfWeek, $repeatDays)) {
                $this->checkDateConflicts($date->format('Y-m-d'));
            }
        }
    }

    private function checkTimeOverlaps($existingReservations, $inputDate)
    {
        $dayFormatted = $inputDate->format('Y-m-d');
        
        try {
            $requestStart = Carbon::createFromFormat('Y-m-d H:i', $dayFormatted . ' ' . $this->request->horario_inicio);
            $requestEnd = Carbon::createFromFormat('Y-m-d H:i', $dayFormatted . ' ' . $this->request->horario_fim);
        } catch (\Exception $e) {
            return; // Skip validation if time format is invalid
        }

        $requestPeriod = CarbonPeriod::between($requestStart, $requestEnd);

        foreach ($existingReservations as $reservation) {
            // Convert reservation data format to match our input format
            try {
                $reservationDate = Carbon::createFromFormat('d/m/Y', $reservation->data)->format('Y-m-d');
                $reservationStart = Carbon::createFromFormat('Y-m-d H:i', $reservationDate . ' ' . $reservation->horario_inicio);
                $reservationEnd = Carbon::createFromFormat('Y-m-d H:i', $reservationDate . ' ' . $reservation->horario_fim);
            } catch (\Exception $e) {
                continue; // Skip this reservation if date/time parsing fails
            }

            $existingPeriod = CarbonPeriod::between($reservationStart, $reservationEnd);

            if ($existingPeriod->overlaps($requestPeriod)) {
                $this->conflicts[] = sprintf(
                    '%s (%s às %s)',
                    $reservation->nome,
                    $reservation->horario_inicio,
                    $reservation->horario_fim
                );
            }
        }
    }
}