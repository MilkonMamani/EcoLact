<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecetaModel extends Model
{
    protected $table = 'recetas';

    protected $fillable = [
        'producto_id',
        'nombre',
        'descripcion',
        'rendimiento_cantidad',
        'rendimiento_unidad',
        'version',
        'estado',
        'producto_activo_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'rendimiento_cantidad' => 'decimal:4',
        'version' => 'integer',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(ProductoModel::class, 'producto_id');
    }

    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RecetaDetalleModel::class, 'receta_id');
    }

    public function produccionDetalles(): HasMany
    {
        return $this->hasMany(ProduccionDetalleModel::class, 'receta_id');
    }
}
