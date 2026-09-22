<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductoModel extends Model
{
    protected $table = 'productos';

    protected $fillable = [
        'nombre',
        'nombre_base',
        'tipo_producto',
        'presentacion_cantidad',
        'presentacion_unidad',
        'imagen',
        'unidad_medida',
        'estado',
    ];

    protected $casts = [
        'presentacion_cantidad' => 'decimal:3',
    ];

    public function produccionDetalles(): HasMany
    {
        return $this->hasMany(ProduccionDetalleModel::class, 'producto_id');
    }

    /**
     * Todas las versiones de receta del producto (varias versiones, un solo producto).
     */
    public function recetas(): HasMany
    {
        return $this->hasMany(RecetaModel::class, 'producto_id');
    }

    public function recetaActiva(): HasOne
    {
        return $this->hasOne(RecetaModel::class, 'producto_id')->where('estado', 'ACTIVA');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(ProductoMovimientoModel::class, 'producto_id');
    }

    /**
     * URL pública de la imagen (única copia, guardada en el disco "public"),
     * o null si el producto no tiene imagen: la vista muestra un placeholder.
     */
    public function imagenUrl(): ?string
    {
        return $this->imagen ? asset('storage/'.ltrim($this->imagen, '/')) : null;
    }
}
