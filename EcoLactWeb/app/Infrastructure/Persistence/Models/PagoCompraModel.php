<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoCompraModel extends Model
{
    protected $table = 'pagos_compra';

    protected $fillable = [
        'compra_insumo_id',
        'monto',
        'metodo_pago',
        'numero_operacion',
        'fecha',
        'observacion',
        'movimiento_caja_id',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'datetime',
    ];

    public function compra(): BelongsTo
    {
        return $this->belongsTo(CompraInsumoModel::class, 'compra_insumo_id');
    }

    public function movimientoCaja(): BelongsTo
    {
        return $this->belongsTo(MovimientoCajaModel::class, 'movimiento_caja_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
