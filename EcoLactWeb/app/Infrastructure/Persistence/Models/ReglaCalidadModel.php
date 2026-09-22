<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReglaCalidadModel extends Model
{
    protected $table = 'reglas_calidad';

    protected $fillable = [
        'parametro_id',
        'valor_minimo',
        'valor_maximo',
        'resultado_incumplimiento',
        'prioridad',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected $casts = [
        'valor_minimo' => 'decimal:4',
        'valor_maximo' => 'decimal:4',
        'prioridad' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function parametro(): BelongsTo
    {
        return $this->belongsTo(ParametroCalidadModel::class, 'parametro_id');
    }
}
