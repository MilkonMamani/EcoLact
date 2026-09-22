<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecorridoAcopioModel extends Model
{
    protected $table = 'recorridos_acopio';

    protected $fillable = [
        'ruta_id',
        'acopiador_id',
        'fecha_operativa',
        'estado',
        'iniciado_en',
        'finalizado_en',
    ];

    protected $casts = [
        'fecha_operativa' => 'date',
        'iniciado_en' => 'datetime',
        'finalizado_en' => 'datetime',
    ];

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(RutaModel::class, 'ruta_id');
    }

    public function acopiador(): BelongsTo
    {
        return $this->belongsTo(AcopiadorModel::class, 'acopiador_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RecorridoDetalleModel::class, 'recorrido_id');
    }
}
