<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresentacionInsumoModel extends Model
{
    protected $table = 'presentaciones_insumo';

    protected $fillable = [
        'insumo_id',
        'nombre',
        'cantidad',
        'unidad',
        'cantidad_base',
        'estado',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'cantidad_base' => 'decimal:4',
    ];

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(InsumoModel::class, 'insumo_id');
    }
}
