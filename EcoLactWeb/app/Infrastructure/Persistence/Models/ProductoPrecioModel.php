<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoPrecioModel extends Model
{
    protected $table = 'producto_precios';

    protected $fillable = [
        'producto_id',
        'tipo_cliente',
        'precio',
        'cantidad_minima',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'creado_por_user_id',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'cantidad_minima' => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(ProductoModel::class, 'producto_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }
}
