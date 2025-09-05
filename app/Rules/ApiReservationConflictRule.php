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
            $this->message = 'Reserva não foi criada porque conflita com: <ul>' . implode('', $this->conflicts) . '</ul>';
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
        // This method is called for recurring reservations to check the entire series
        // The actual work is done in checkRecurringDateConflicts() which is called from checkDateConflicts()
        return;
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

        foreach ($existingReservations as $reservation) {
            // Convert reservation data format to match our input format
            try {
                // Handle both d/m/Y and Y-m-d formats for compatibility
                if (strpos($reservation->data, '/') !== false) {
                    // Format: d/m/Y (legacy format)
                    $reservationDate = Carbon::createFromFormat('d/m/Y', $reservation->data)->format('Y-m-d');
                } else {
                    // Format: Y-m-d (API format)
                    $reservationDate = $reservation->data;
                }
                
                $reservationStart = Carbon::createFromFormat('Y-m-d H:i', $reservationDate . ' ' . $reservation->horario_inicio);
                $reservationEnd = Carbon::createFromFormat('Y-m-d H:i', $reservationDate . ' ' . $reservation->horario_fim);
            } catch (\Exception $e) {
                continue; // Skip this reservation if date/time parsing fails
            }

            // Check if the time periods overlap
            if ($this->periodsOverlap($requestStart, $requestEnd, $reservationStart, $reservationEnd)) {
                $this->conflicts[] = sprintf(
                    '<li><a href="/reservas/%d">%s</a></li>',
                    $reservation->id,
                    $reservation->nome
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