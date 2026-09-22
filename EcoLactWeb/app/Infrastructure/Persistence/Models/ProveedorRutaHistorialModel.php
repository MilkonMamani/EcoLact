<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProveedorRutaHistorialModel extends Model
{
    protected $table = 'proveedor_ruta_historial';

    protected $fillable = [
        'proveedor_id',
        'ruta_id',
        'fecha_inicio',
        'fecha_fin',
        'motivo',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(ProveedorModel::class, 'proveedor_id');
    }

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(RutaModel::class, 'ruta_id');
    }
}
