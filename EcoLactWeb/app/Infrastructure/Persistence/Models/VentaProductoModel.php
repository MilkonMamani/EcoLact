<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaProductoModel extends Model
{
    protected $table = 'ventas_producto';

    protected $fillable = [
        'producto_id',
        'proveedor_id',
        'comprador_id',
        'tipo_cliente',
        'cantidad',
        'precio_unitario',
        'importe_total',
        'forma_pago',
        'periodo_id',
        'estado',
        'fecha',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'importe_total' => 'decimal:2',
        'fecha' => 'datetime',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(ProductoModel::class, 'producto_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(ProveedorModel::class, 'proveedor_id');
    }

    public function comprador(): BelongsTo
    {
        return $this->belongsTo(CompradorModel::class, 'comprador_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLiquidacionModel::class, 'periodo_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
