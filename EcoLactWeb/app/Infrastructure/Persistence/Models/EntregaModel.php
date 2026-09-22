<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EntregaModel extends Model
{
    protected $table = 'entregas';

    protected $fillable = [
        'proveedor_id',
        'acopiador_id',
        'zona_id',
        'recorrido_detalle_id',
        'registrado_por_user_id',
        'fecha_hora',
        'litros',
        'metodo_identificacion',
        'token_idempotencia',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'litros' => 'decimal:2',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(ProveedorModel::class, 'proveedor_id');
    }

    public function acopiador(): BelongsTo
    {
        return $this->belongsTo(AcopiadorModel::class, 'acopiador_id');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaModel::class, 'zona_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function recorridoDetalle(): BelongsTo
    {
        return $this->belongsTo(RecorridoDetalleModel::class, 'recorrido_detalle_id');
    }

    public function analisisCalidad(): HasOne
    {
        return $this->hasOne(AnalisisCalidadModel::class, 'entrega_id');
    }

    public function valorizacionEntrega(): HasOne
    {
        return $this->hasOne(ValorizacionEntregaModel::class, 'entrega_id');
    }
}
