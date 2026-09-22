<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionDetalleModel extends Model
{
    protected $table = 'liquidacion_detalles';

    protected $fillable = [
        'liquidacion_id',
        'valorizacion_entrega_id',
        'importe',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
    ];

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(LiquidacionModel::class, 'liquidacion_id');
    }

    public function valorizacionEntrega(): BelongsTo
    {
        return $this->belongsTo(ValorizacionEntregaModel::class, 'valorizacion_entrega_id');
    }
}
