<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrabajadorPeriodoActividadModel extends Model
{
    protected $table = 'trabajador_periodos_actividad';

    protected $fillable = [
        'trabajador_id',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(TrabajadorModel::class, 'trabajador_id');
    }
}
