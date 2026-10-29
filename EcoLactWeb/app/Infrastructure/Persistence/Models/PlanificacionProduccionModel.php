<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanificacionProduccionModel extends Model
{
    protected $table = 'planificaciones_produccion';

    protected $fillable = [
        'codigo',
        'observacion',
        'registrado_por_user_id',
    ];

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(LoteProduccionModel::class, 'planificacion_produccion_id');
    }
}
