<?php

namespace App\Rules;

use App\Models\Reserva;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Validation\Rule;

class ApiReservationConflictRule implements Rule
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
        // Reset conflicts array
        $this->conflicts = [];

        // Must have required fields - fail validation if missing
        if (!$this->request->has('sala_id')) {
            $this->message = 'Sala é obrigatória para verificar conflitos.';
            return false;
        }

        // Check if it's a recurring reservation
        $isRecurring = $this->request->has('repeat_days') && $this->request->has('repeat_until') &&
                      !empty($this->request->repeat_days) && !empty($this->request->repeat_until);

        if ($isRecurring) {
            // For recurring reservations, we need day_times
            if (!$this->request->has('day_times') || empty($this->request->day_times)) {
                $this->message = 'Horários por dia (day_times) são obrigatórios para reservas recorrentes.';
                return false;
            }

            // Validate recurring conflicts
            $this->checkRecurringConflicts($value);
        } else {
            // For single reservations, we need horario_inicio and horario_fim
            if (!$this->request->has('horario_inicio') || !$this->request->has('horario_fim')) {
                $this->message = 'Horários de início e fim são obrigatórios para verificar conflitos.';
                return false;
            }

            // Check for conflicts on the primary date
            $this->checkDateConflicts($value);
        }

        if (!empty($this->conflicts)) {
            $this->message = 'Conflito de horário detectado com as seguintes reservas: <ul>' . implode('', $this->conflicts) . '</ul>';
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

        // For recurring reservations, we need to check conflicts with other reservations on the same day of the week
        $isRecurring = $this->request->has('repeat_days') && !empty($this->request->repeat_days);
        
        if ($isRecurring) {
            $this->checkRecurringDateConflicts($inputDate);
        } else {
            $this->checkSingleDateConflicts($inputDate);
        }
    }

    private function checkSingleDateConflicts($inputDate)
    {
        // For single (non-recurring) reservations, only check conflicts on the exact same date
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

    private function checkRecurringDateConflicts($inputDate)
    {
        // For recurring reservations, check conflicts with:
        // 1. Single reservations on any of our recurring dates
        // 2. Other recurring reservations that have overlapping days of the week
        
        try {
            $start = $inputDate;
            $end = Carbon::createFromFormat('Y-m-d', $this->request->repeat_until);
        } catch (\Exception $e) {
            return; // Skip validation if date format is invalid
        }

        $repeatDays = is_array($this->request->repeat_days) ? $this->request->repeat_days : [];
        $period = CarbonPeriod::between($start, $end);

        // Check each date in our recurring series
        foreach ($period as $date) {
            if (in_array($date->dayOfWeek, $repeatDays)) {
                // Get existing reservations for this specific date
                $existingReservations = Reserva::whereDate('data', '=', $date)
                    ->where('sala_id', $this->request->sala_id)
                    ->where('status', '!=', 'rejeitada')
                    ->when($this->reservaId, function ($query) {
                        return $query->where('id', '!=', $this->reservaId)
                            // Also exclude child reservations of the current series
                            ->where('parent_id', '!=', $this->reservaId);
                    })
                    ->get();

                if (!$existingReservations->isEmpty()) {
                    $this->checkTimeOverlaps($existingReservations, $date);
                }
            }
        }
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
        $dayTimes = is_array($this->request->day_times) ? $this->request->day_times : [];
        $period = CarbonPeriod::between($start, $end);

        // Check each date in our recurring series
        foreach ($period as $date) {
            $dayOfWeek = $date->dayOfWeek;

            if (in_array($dayOfWeek, $repeatDays) && isset($dayTimes[$dayOfWeek])) {
                $dayTime = $dayTimes[$dayOfWeek];

                // Validate this specific date with its specific times
                $this->checkSingleDateConflictsWithTimes(
                    $date,
                    $dayTime['start'],
                    $dayTime['end']
                );
            }
        }
    }

    private function checkSingleDateConflictsWithTimes($inputDate, $startTime, $endTime)
    {
        // Get existing reservations for this specific date
        $existingReservations = Reserva::whereDate('data', '=', $inputDate)
            ->where('sala_id', $this->request->sala_id)
            ->where('status', '!=', 'rejeitada')
            ->when($this->reservaId, function ($query) {
                return $query->where('id', '!=', $this->reservaId)
                    ->where('parent_id', '!=', $this->reservaId);
            })
            ->get();

        if (!$existingReservations->isEmpty()) {
            $this->checkTimeOverlapsWithTimes($existingReservations, $inputDate, $startTime, $endTime);
        }
    }

    private function checkTimeOverlaps($existingReservations, $inputDate)
    {
        $this->checkTimeOverlapsWithTimes(
            $existingReservations,
            $inputDate,
            $this->request->horario_inicio,
            $this->request->horario_fim
        );
    }

    private function checkTimeOverlapsWithTimes($existingReservations, $inputDate, $startTime, $endTime)
    {
        $dayFormatted = $inputDate->format('Y-m-d');

        try {
            $requestStart = Carbon::createFromFormat('Y-m-d H:i', $dayFormatted . ' ' . $startTime);
            $requestEnd = Carbon::createFromFormat('Y-m-d H:i', $dayFormatted . ' ' . $endTime);
        } catch (\Exception $e) {
            return; // Skip validation if time format is invalid
        }

        foreach ($existingReservations as $reservation) {
            // Convert reservation data format to match our input format
            try {
                // Get the raw date from database (Y-m-d format)
                $reservationDate = $reservation->getRawOriginal('data');
                if (!$reservationDate) {
                    continue; // Skip if no date
                }

                $reservationStart = Carbon::createFromFormat('Y-m-d H:i', $reservationDate . ' ' . $reservation->horario_inicio);
                $reservationEnd = Carbon::createFromFormat('Y-m-d H:i', $reservationDate . ' ' . $reservation->horario_fim);
            } catch (\Exception $e) {
                continue; // Skip this reservation if date/time parsing fails
            }

            // Check if the time periods overlap
            if ($this->periodsOverlap($requestStart, $requestEnd, $reservationStart, $reservationEnd)) {
                $this->conflicts[] = sprintf(
                    '<li>Reserva "%s" (ID: %d) - %s às %s em %s</li>',
                    $reservation->nome,
                    $reservation->id,
                    $reservation->horario_inicio,
                    $reservation->horario_fim,
                    $inputDate->format('d/m/Y')
                );
            }
        }
    }

    /**
     * Check if two time periods overlap
     */
    private function periodsOverlap($start1, $end1, $start2, $end2)
    {
        // Two periods overlap if one starts before the other ends
        // and vice versa
        return $start1->lt($end2) && $start2->lt($end1);
    }
}