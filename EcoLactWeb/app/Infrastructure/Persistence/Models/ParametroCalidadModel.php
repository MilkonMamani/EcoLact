<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParametroCalidadModel extends Model
{
    protected $table = 'parametros_calidad';

    protected $fillable = [
        'codigo',
        'nombre',
        'unidad',
        'estado',
    ];

    public function reglasCalidad(): HasMany
    {
        return $this->hasMany(ReglaCalidadModel::class, 'parametro_id');
    }

    public function analisisCalidadDetalles(): HasMany
    {
        return $this->hasMany(AnalisisCalidadDetalleModel::class, 'parametro_id');
    }
}
