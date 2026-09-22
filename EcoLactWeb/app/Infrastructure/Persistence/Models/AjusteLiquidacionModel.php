<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AjusteLiquidacionModel extends Model
{
    protected $table = 'ajustes_liquidacion';

    protected $fillable = [
        'liquidacion_id',
        'sancion_id',
        'tipo',
        'descripcion',
        'monto',
        'creado_por_user_id',
        'venta_producto_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(LiquidacionModel::class, 'liquidacion_id');
    }

    public function sancion(): BelongsTo
    {
        return $this->belongsTo(SancionModel::class, 'sancion_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function ventaProducto(): BelongsTo
    {
        return $this->belongsTo(VentaProductoModel::class, 'venta_producto_id');
    }
}
