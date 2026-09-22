<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecorridoDetalleModel extends Model
{
    protected $table = 'recorrido_detalles';

    protected $fillable = [
        'recorrido_id',
        'proveedor_id',
        'ruta_detalle_id',
        'orden_visita',
        'latitud',
        'longitud',
        'referencia_ubicacion',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'latitud' => 'decimal:7',
        'longitud' => 'decimal:7',
    ];

    public function recorrido(): BelongsTo
    {
        return $this->belongsTo(RecorridoAcopioModel::class, 'recorrido_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(ProveedorModel::class, 'proveedor_id');
    }

    public function rutaDetalle(): BelongsTo
    {
        return $this->belongsTo(RutaDetalleModel::class, 'ruta_detalle_id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(EntregaModel::class, 'recorrido_detalle_id');
    }
}
