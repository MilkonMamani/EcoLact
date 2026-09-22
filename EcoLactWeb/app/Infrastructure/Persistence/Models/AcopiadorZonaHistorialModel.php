<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcopiadorZonaHistorialModel extends Model
{
    protected $table = 'acopiador_zona_historial';

    protected $fillable = [
        'acopiador_id',
        'zona_id',
        'fecha_inicio',
        'fecha_fin',
        'motivo',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function acopiador(): BelongsTo
    {
        return $this->belongsTo(AcopiadorModel::class, 'acopiador_id');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaModel::class, 'zona_id');
    }
}
