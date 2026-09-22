<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodoLiquidacionModel extends Model
{
    protected $table = 'periodos_liquidacion';

    protected $fillable = [
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(LiquidacionModel::class, 'periodo_id');
    }
}
