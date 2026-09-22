<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoProveedorModel extends Model
{
    protected $table = 'pagos_proveedor';

    protected $fillable = [
        'liquidacion_id',
        'fecha_pago',
        'monto',
        'estado',
        'observacion',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'fecha_pago' => 'datetime',
        'monto' => 'decimal:2',
    ];

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(LiquidacionModel::class, 'liquidacion_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
