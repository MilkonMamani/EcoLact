<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoMovimientoModel extends Model
{
    protected $table = 'producto_movimientos';

    protected $fillable = [
        'producto_id',
        'tipo',
        'cantidad',
        'lote_produccion_id',
        'venta_producto_id',
        'fecha',
        'observacion',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'fecha' => 'datetime',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(ProductoModel::class, 'producto_id');
    }

    public function loteProduccion(): BelongsTo
    {
        return $this->belongsTo(LoteProduccionModel::class, 'lote_produccion_id');
    }

    public function ventaProducto(): BelongsTo
    {
        return $this->belongsTo(VentaProductoModel::class, 'venta_producto_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
