<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisCalidadDetalleModel extends Model
{
    protected $table = 'analisis_calidad_detalles';

    protected $fillable = [
        'analisis_id',
        'parametro_id',
        'valor',
        'origen',
        'confianza_ocr',
        'cumple',
    ];

    protected $casts = [
        'valor' => 'decimal:4',
        'confianza_ocr' => 'decimal:2',
        'cumple' => 'boolean',
    ];

    public function analisis(): BelongsTo
    {
        return $this->belongsTo(AnalisisCalidadModel::class, 'analisis_id');
    }

    public function parametro(): BelongsTo
    {
        return $this->belongsTo(ParametroCalidadModel::class, 'parametro_id');
    }
}
