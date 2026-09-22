<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ValorizacionEntregaModel extends Model
{
    protected $table = 'valorizaciones_entrega';

    protected $fillable = [
        'entrega_id',
        'analisis_id',
        'precio_leche_id',
        'regla_pago_id',
        'litros_entregados',
        'litros_pagables',
        'precio_base_litro',
        'ajuste_por_litro',
        'precio_final_litro',
        'importe_total',
        'fecha_calculo',
    ];

    protected $casts = [
        'litros_entregados' => 'decimal:2',
        'litros_pagables' => 'decimal:2',
        'precio_base_litro' => 'decimal:4',
        'ajuste_por_litro' => 'decimal:4',
        'precio_final_litro' => 'decimal:4',
        'importe_total' => 'decimal:2',
        'fecha_calculo' => 'datetime',
    ];

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(EntregaModel::class, 'entrega_id');
    }

    public function analisisCalidad(): BelongsTo
    {
        return $this->belongsTo(AnalisisCalidadModel::class, 'analisis_id');
    }

    public function precioLeche(): BelongsTo
    {
        return $this->belongsTo(PrecioLecheModel::class, 'precio_leche_id');
    }

    public function reglaPagoCalidad(): BelongsTo
    {
        return $this->belongsTo(ReglaPagoCalidadModel::class, 'regla_pago_id');
    }

    public function liquidacionDetalle(): HasOne
    {
        return $this->hasOne(LiquidacionDetalleModel::class, 'valorizacion_entrega_id');
    }
}
