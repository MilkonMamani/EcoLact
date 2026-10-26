<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoCajaModel extends Model
{
    protected $table = 'movimientos_caja';

    protected $fillable = [
        'tipo',
        'categoria',
        'monto',
        'concepto',
        'descripcion',
        'referencia',
        'detalle',
        'referencia_tipo',
        'referencia_id',
        'registrado_por_user_id',
        'fecha',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'detalle' => 'array',
        'fecha' => 'datetime',
    ];

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
