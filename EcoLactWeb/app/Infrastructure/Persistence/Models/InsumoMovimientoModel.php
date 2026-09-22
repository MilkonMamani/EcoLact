<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsumoMovimientoModel extends Model
{
    protected $table = 'insumo_movimientos';

    protected $fillable = [
        'insumo_id',
        'tipo',
        'motivo',
        'cantidad',
        'lote_produccion_id',
        'origen_tipo',
        'origen_id',
        'fecha',
        'observacion',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'fecha' => 'datetime',
    ];

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(InsumoModel::class, 'insumo_id');
    }

    public function loteProduccion(): BelongsTo
    {
        return $this->belongsTo(LoteProduccionModel::class, 'lote_produccion_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
