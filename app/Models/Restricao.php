<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PeriodoLetivo;
use App\Models\Sala;


class Restricao extends Model
{
    use HasFactory;

    protected $table = 'restricoes';
    protected $guarded = ['id'];

    protected $fillable = [
        'tipo_restricao',
        'data_limite',
        'dias_limite',
        'dias_antecedencia',
        'duracao_minima',
        'duracao_maxima',
        'bloqueada',
        'motivo_bloqueio',
        'sala_id',
        'periodo_letivo_id',
        'aprovacao',
        'prazo_aprovacao'
    ];


    public function periodoLetivo()
    {
        return $this->belongsTo(PeriodoLetivo::class);
    }


    public function sala()
    {
        return $this->belongsTo(Sala::class);
    }


}

