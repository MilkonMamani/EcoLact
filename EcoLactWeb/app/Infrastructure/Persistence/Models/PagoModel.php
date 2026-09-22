<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pago real de una obligación (tabla `pagos`). No confundir con PagoProveedorModel (legado). */
class PagoModel extends Model
{
    protected $table = 'pagos';

    protected $fillable = [
        'obligacion_pago_id',
        'fecha_pago',
        'pagado_at',
        'impreso_at',
        'lote_codigo',
        'comprobante_snapshot',
        'monto',
        'metodo_pago',
        'numero_operacion',
        'observacion',
        'comprobante_path',
        'comprobante_nombre',
        'pago_proveedor_id',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'pagado_at' => 'datetime',
        'impreso_at' => 'datetime',
        'comprobante_snapshot' => 'array',
        'monto' => 'decimal:2',
    ];

    public function obligacion(): BelongsTo
    {
        return $this->belongsTo(ObligacionPagoModel::class, 'obligacion_pago_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
