<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProduccionDetalleModel extends Model
{
    protected $table = 'produccion_detalles';

    protected $fillable = [
        'lote_produccion_id',
        'producto_id',
        'receta_id',
        'receta_version',
        'cantidad_planificada',
        'cantidad_obtenida',
    ];

    protected $casts = [
        'cantidad_planificada' => 'decimal:2',
        'cantidad_obtenida' => 'decimal:2',
    ];

    public function loteProduccion(): BelongsTo
    {
        return $this->belongsTo(LoteProduccionModel::class, 'lote_produccion_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(ProductoModel::class, 'producto_id');
    }

    public function receta(): BelongsTo
    {
        return $this->belongsTo(RecetaModel::class, 'receta_id');
    }
}
