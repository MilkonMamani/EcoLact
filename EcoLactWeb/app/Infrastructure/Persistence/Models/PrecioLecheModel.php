<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrecioLecheModel extends Model
{
    protected $table = 'precios_leche';

    protected $fillable = [
        'precio_litro',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'creado_por_user_id',
    ];

    protected $casts = [
        'precio_litro' => 'decimal:4',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function valorizacionesEntrega(): HasMany
    {
        return $this->hasMany(ValorizacionEntregaModel::class, 'precio_leche_id');
    }
}
