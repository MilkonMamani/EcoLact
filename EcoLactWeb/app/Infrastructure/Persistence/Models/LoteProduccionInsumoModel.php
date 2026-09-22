<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoteProduccionInsumoModel extends Model
{
    protected $table = 'lote_produccion_insumos';

    protected $fillable = [
        'lote_produccion_id',
        'insumo_id',
        'cantidad_requerida',
        'cantidad_usada',
        'unidad_base',
    ];

    protected $casts = [
        'cantidad_requerida' => 'decimal:4',
        'cantidad_usada' => 'decimal:4',
    ];

    public function loteProduccion(): BelongsTo
    {
        return $this->belongsTo(LoteProduccionModel::class, 'lote_produccion_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(InsumoModel::class, 'insumo_id');
    }
}
