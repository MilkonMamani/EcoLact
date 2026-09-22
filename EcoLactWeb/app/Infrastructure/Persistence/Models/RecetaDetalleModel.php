<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecetaDetalleModel extends Model
{
    protected $table = 'receta_detalles';

    protected $fillable = [
        'receta_id',
        'insumo_id',
        'cantidad',
        'unidad',
        'observacion',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
    ];

    public function receta(): BelongsTo
    {
        return $this->belongsTo(RecetaModel::class, 'receta_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(InsumoModel::class, 'insumo_id');
    }
}
