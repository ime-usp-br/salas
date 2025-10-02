<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Recurso;
use App\Models\Reserva;
use App\Models\Categoria;
use App\Models\Restricao;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Sala extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    use HasFactory;
    protected $guarded = ['id'];

    public function reservas()
    {
        return $this->hasMany(Reserva::class);
    }

    public function recursos()
    {
        return $this->belongsToMany(Recurso::class)
                    ->withTimestamps();
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function responsaveis()
    {
        return $this->belongsToMany(User::class, 'responsaveis')->withPivot('id');
    }

    public function restricao()
    {
        return $this->hasOne(Restricao::class);
    }

    public static function SalasLivresQuery(array $validated) {
        $horario_inicio = $validated['horario_inicio'];
        $horario_fim = $validated['horario_fim'];
        $data = Carbon::createFromFormat('d/m/Y', $validated['data'])->format('Y-m-d');
        $query = "SELECT s.id, s.capacidade, s.nome, c.nome AS nomcat
            FROM salas s
            LEFT JOIN reservas r ON r.sala_id = s.id
            INNER JOIN categorias c ON c.id = s.categoria_id
            WHERE s.id NOT IN (
                SELECT r.sala_id
                FROM reservas r
                WHERE
                    (
                        r.horario_inicio = STR_TO_DATE(?, '%H:%i')
                        AND r.horario_fim = STR_TO_DATE(?, '%H:%i')
                        AND r.data = ?
                    )
                    OR (
                        r.horario_inicio > STR_TO_DATE(?, '%H:%i')
                        AND r.horario_fim < STR_TO_DATE(?, '%H:%i')
                        AND r.data = ?
                    )
                    OR (
                        r.horario_inicio < STR_TO_DATE(?, '%H:%i')
                        AND r.horario_fim > STR_TO_DATE(?, '%H:%i')
                        AND r.data = ?
                    )
                )
            GROUP BY s.id, s.capacidade, s.nome, c.nome
        ";

        return collect(DB::select($query,[
            $horario_inicio, $horario_fim, $data,
            $horario_inicio, $horario_fim, $data,
            $horario_fim, $horario_inicio, $data //sim, está certo
        ]))->groupBy('nomcat');
    }

    public static function SalasLivresRecurringQuery(array $validated) {
        // Parse dates
        $dataInicial = Carbon::createFromFormat('d/m/Y', $validated['data']);
        $dataFinal = Carbon::createFromFormat('d/m/Y', $validated['repeat_until']);
        $repeatDays = $validated['repeat_days'];
        $dayTimes = $validated['day_times'] ?? null;

        // Generate all occurrence dates
        $period = \Carbon\CarbonPeriod::between($dataInicial, $dataFinal);
        $occurrences = [];

        foreach ($period as $date) {
            if (in_array($date->dayOfWeek, $repeatDays)) {
                $dayOfWeek = $date->dayOfWeek;

                // Get time for this specific day
                if ($dayTimes && isset($dayTimes[$dayOfWeek])) {
                    $horarioInicio = $dayTimes[$dayOfWeek]['start'];
                    $horarioFim = $dayTimes[$dayOfWeek]['end'];
                } else {
                    $horarioInicio = $validated['horario_inicio'];
                    $horarioFim = $validated['horario_fim'];
                }

                $occurrences[] = [
                    'date' => $date->format('Y-m-d'),
                    'start' => $horarioInicio,
                    'end' => $horarioFim,
                ];
            }
        }

        // Get all rooms with their restrictions and categories
        $salas = self::with(['restricao', 'categoria'])->get();

        // Filter rooms based on "All or Nothing" logic
        $availableRooms = $salas->filter(function($sala) use ($occurrences, $dataInicial, $dataFinal) {
            // Check room restrictions first (early filtering)
            if (!self::checkRoomRestrictions($sala, $dataInicial, $dataFinal)) {
                return false;
            }

            // Check availability for ALL occurrences
            foreach ($occurrences as $occurrence) {
                if (!self::isRoomAvailableForOccurrence($sala->id, $occurrence)) {
                    return false; // If ANY occurrence conflicts, exclude this room
                }
            }

            return true; // Room is available for ALL occurrences
        });

        // Format results grouped by category
        return $availableRooms->groupBy(function($sala) {
            return $sala->categoria->nome;
        })->map(function($salas) {
            return $salas->map(function($sala) {
                return [
                    'id' => $sala->id,
                    'nome' => $sala->nome,
                    'capacidade' => $sala->capacidade,
                    'nomcat' => $sala->categoria->nome,
                ];
            })->values();
        });
    }

    private static function checkRoomRestrictions($sala, $dataInicial, $dataFinal) {
        // No restriction = available
        if (!$sala->restricao) {
            return true;
        }

        $restricao = $sala->restricao;

        // Blocked rooms are never available
        if ($restricao->bloqueada) {
            return false;
        }

        // Check minimum advance booking requirement
        if ($restricao->dias_antecedencia > 0) {
            $daysUntilStart = Carbon::now()->diffInDays($dataInicial->format('Y-m-d'), false);
            if ($restricao->dias_antecedencia > $daysUntilStart) {
                return false;
            }
        }

        // Check AUTO restriction (dynamic date limit)
        if ($restricao->tipo_restricao === 'AUTO') {
            $dataLimite = Carbon::now()->addDays($restricao->dias_limite);
            if ($dataInicial->isAfter($dataLimite) || $dataFinal->isAfter($dataLimite)) {
                return false;
            }
        }

        // Check FIXA restriction (fixed date limit)
        if ($restricao->tipo_restricao === 'FIXA') {
            if ($dataInicial->isAfter($restricao->data_limite) || $dataFinal->isAfter($restricao->data_limite)) {
                return false;
            }
        }

        // Check PERIODO_LETIVO restriction (academic period)
        if ($restricao->tipo_restricao === 'PERIODO_LETIVO') {
            $periodo = \App\Models\PeriodoLetivo::find($restricao->periodo_letivo_id);
            if (!$dataInicial->between($periodo->data_inicio_reservas, $periodo->data_fim_reservas) ||
                $dataFinal->isAfter($periodo->data_fim_reservas)) {
                return false;
            }
        }

        return true;
    }

    private static function isRoomAvailableForOccurrence($salaId, $occurrence) {
        // Check for conflicting reservations (excluding rejected ones)
        $conflicts = Reserva::whereDate('data', '=', $occurrence['date'])
            ->where('sala_id', $salaId)
            ->where('status', '!=', 'rejeitada')
            ->get();

        if ($conflicts->isEmpty()) {
            return true;
        }

        // Check time overlap
        $requestedStart = Carbon::createFromFormat('Y-m-d H:i', $occurrence['date'] . ' ' . $occurrence['start']);
        $requestedEnd = Carbon::createFromFormat('Y-m-d H:i', $occurrence['date'] . ' ' . $occurrence['end']);

        foreach ($conflicts as $reserva) {
            $reservaStart = Carbon::parse($reserva->inicio);
            $reservaEnd = Carbon::parse($reserva->fim);

            // Check for time overlap
            if ($requestedStart->lt($reservaEnd) && $requestedEnd->gt($reservaStart)) {
                return false; // Conflict found
            }
        }

        return true;
    }

}
