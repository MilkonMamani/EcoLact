<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompradorModel extends Model
{
    protected $table = 'compradores';

    protected $fillable = [
        'nombre',
        'documento',
        'telefono',
        'tipo_cliente',
        'estado',
    ];

    public function ventas(): HasMany
    {
        return $this->hasMany(VentaProductoModel::class, 'comprador_id');
    }
}
