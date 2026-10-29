<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraInsumoDetalleModel extends Model
{
    protected $table = 'compra_insumo_detalles';

    protected $fillable = [
        'compra_insumo_id',
        'tratamiento',
        'descripcion',
        'insumo_id',
        'presentacion_insumo_id',
        'presentacion_nombre',
        'presentacion_cantidad',
        'presentacion_unidad',
        'cantidad_presentaciones',
        'unidad',
        'precio_unitario',
        'subtotal',
        'cantidad_base',
        'unidad_base',
        'insumo_movimiento_id',
    ];

    protected $casts = [
        'presentacion_cantidad' => 'decimal:4',
        'cantidad_presentaciones' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'cantidad_base' => 'decimal:4',
    ];

    public function compra(): BelongsTo
    {
        return $this->belongsTo(CompraInsumoModel::class, 'compra_insumo_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(InsumoModel::class, 'insumo_id');
    }
}
