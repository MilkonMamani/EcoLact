<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiquidacionModel extends Model
{
    protected $table = 'liquidaciones';

    protected $fillable = [
        'periodo_id',
        'proveedor_id',
        'total_litros_entregados',
        'total_litros_pagables',
        'subtotal',
        'total_ajustes',
        'total_pagar',
        'estado',
        'fecha_calculo',
        'generado_por_user_id',
        'precio_litro_aplicado',
        'tuvo_incidencia',
        'sancion_id',
    ];

    protected $casts = [
        'total_litros_entregados' => 'decimal:2',
        'total_litros_pagables' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total_ajustes' => 'decimal:2',
        'total_pagar' => 'decimal:2',
        'precio_litro_aplicado' => 'decimal:4',
        'tuvo_incidencia' => 'boolean',
        'fecha_calculo' => 'datetime',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLiquidacionModel::class, 'periodo_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(ProveedorModel::class, 'proveedor_id');
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por_user_id');
    }

    public function sancion(): BelongsTo
    {
        return $this->belongsTo(SancionModel::class, 'sancion_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(LiquidacionDetalleModel::class, 'liquidacion_id');
    }

    public function ajustes(): HasMany
    {
        return $this->hasMany(AjusteLiquidacionModel::class, 'liquidacion_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoProveedorModel::class, 'liquidacion_id');
    }
}
