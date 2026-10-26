<?php

namespace App\Infrastructure\Persistence\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Reglas que aplican en $fecha: ACTIVO y con la fecha dentro de [fecha_inicio, fecha_fin]
     * (fecha_fin null = sin fin). Mismo criterio que ReglaCalidad::estaVigente(); es la única
     * definición SQL de "vigente" (la usan el repositorio y ParametroCalidadModel::reglaVigente()).
     *
     * @param  Builder<ReglaCalidadModel>  $query
     */
    public function scopeVigentesEn(Builder $query, DateTimeInterface $fecha): void
    {
        $fechaSql = $fecha->format('Y-m-d');

        $query->where($query->qualifyColumn('estado'), 'ACTIVO')
            ->whereDate($query->qualifyColumn('fecha_inicio'), '<=', $fechaSql)
            ->where(function (Builder $query) use ($fechaSql): void {
                $query->whereNull($query->qualifyColumn('fecha_fin'))
                    ->orWhereDate($query->qualifyColumn('fecha_fin'), '>=', $fechaSql);
            });
    }
}
