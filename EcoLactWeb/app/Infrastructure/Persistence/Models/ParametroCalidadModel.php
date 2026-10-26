<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ParametroCalidadModel extends Model
{
    protected $table = 'parametros_calidad';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'unidad',
        'estado',
    ];

    public function reglasCalidad(): HasMany
    {
        return $this->hasMany(ReglaCalidadModel::class, 'parametro_id');
    }

    /**
     * La regla que hoy evalúa este parámetro: la misma que elige
     * ReglaCalidadRepository::buscarVigentePorParametro() (vigente, menor prioridad, inicio más
     * reciente). Las demás reglas se conservan como historial. Se puede cargar con eager loading.
     */
    public function reglaVigente(): HasOne
    {
        return $this->hasOne(ReglaCalidadModel::class, 'parametro_id')->ofMany(
            ['prioridad' => 'min', 'fecha_inicio' => 'max', 'id' => 'max'],
            fn ($query) => $query->vigentesEn(now()),
        );
    }

    public function analisisCalidadDetalles(): HasMany
    {
        return $this->hasMany(AnalisisCalidadDetalleModel::class, 'parametro_id');
    }
}
