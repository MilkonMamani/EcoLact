<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsumoModel extends Model
{
    protected $table = 'insumos';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'descripcion',
        'unidad_base',
        'stock_actual',
        'stock_minimo',
        'estado',
    ];

    protected $casts = [
        'stock_actual' => 'decimal:4',
        'stock_minimo' => 'decimal:4',
    ];

    public function movimientos(): HasMany
    {
        return $this->hasMany(InsumoMovimientoModel::class, 'insumo_id');
    }

    public function loteProduccionInsumos(): HasMany
    {
        return $this->hasMany(LoteProduccionInsumoModel::class, 'insumo_id');
    }

    public function recetaDetalles(): HasMany
    {
        return $this->hasMany(RecetaDetalleModel::class, 'insumo_id');
    }
}
