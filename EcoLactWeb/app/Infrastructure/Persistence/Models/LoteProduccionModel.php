<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoteProduccionModel extends Model
{
    protected $table = 'lotes_produccion';

    protected $fillable = [
        'planificacion_produccion_id',
        'codigo',
        'fecha_hora_inicio',
        'fecha_hora_fin',
        'estado',
        'observacion',
        'observacion_cierre',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'fecha_hora_inicio' => 'datetime',
        'fecha_hora_fin' => 'datetime',
    ];

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function planificacion(): BelongsTo
    {
        return $this->belongsTo(PlanificacionProduccionModel::class, 'planificacion_produccion_id');
    }

    public function produccionDetalles(): HasMany
    {
        return $this->hasMany(ProduccionDetalleModel::class, 'lote_produccion_id');
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(LoteProduccionInsumoModel::class, 'lote_produccion_id');
    }
}
