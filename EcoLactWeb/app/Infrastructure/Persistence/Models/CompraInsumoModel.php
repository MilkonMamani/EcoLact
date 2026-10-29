<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cabecera de Compras y gastos (tabla histórica compras_insumo).
 */
class CompraInsumoModel extends Model
{
    protected $table = 'compras_insumo';

    protected $fillable = [
        'codigo',
        'origen',
        'categoria_compra_id',
        'proveedor_comercial_id',
        'proveedor_comercial',
        'ruc',
        'tipo_comprobante',
        'numero_comprobante',
        'fecha',
        'moneda',
        'concepto',
        'impuesto',
        'total',
        'estado_recepcion',
        'recibida_at',
        'recibida_por_user_id',
        'estado_pago',
        'monto_pagado',
        'imagen_boleta',
        'observacion',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total' => 'decimal:2',
        'impuesto' => 'decimal:2',
        'monto_pagado' => 'decimal:2',
        'recibida_at' => 'datetime',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(CompraInsumoDetalleModel::class, 'compra_insumo_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoCompraModel::class, 'compra_insumo_id');
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(ComprobanteCompraModel::class, 'compra_insumo_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaCompraModel::class, 'categoria_compra_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(ProveedorComercialModel::class, 'proveedor_comercial_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
