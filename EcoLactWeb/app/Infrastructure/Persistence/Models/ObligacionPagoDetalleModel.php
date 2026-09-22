<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObligacionPagoDetalleModel extends Model
{
    protected $table = 'obligacion_pago_detalles';

    protected $fillable = [
        'obligacion_pago_id',
        'entrega_id',
        'litros_entregados',
        'litros_pagables',
        'resultado_calidad',
        'precio_base',
        'ajuste_calidad',
        'precio_final',
        'subtotal',
    ];

    protected $casts = [
        'litros_entregados' => 'decimal:2',
        'litros_pagables' => 'decimal:2',
        'precio_base' => 'decimal:4',
        'ajuste_calidad' => 'decimal:4',
        'precio_final' => 'decimal:4',
        'subtotal' => 'decimal:2',
    ];

    public function obligacion(): BelongsTo
    {
        return $this->belongsTo(ObligacionPagoModel::class, 'obligacion_pago_id');
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(EntregaModel::class, 'entrega_id');
    }
}
