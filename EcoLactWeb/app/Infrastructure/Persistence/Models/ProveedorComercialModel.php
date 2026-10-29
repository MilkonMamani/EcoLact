<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tiendas a las que se compra. No confundir con ProveedorModel (leche).
 */
class ProveedorComercialModel extends Model
{
    protected $table = 'proveedores_comerciales';

    protected $fillable = [
        'razon_social',
        'ruc',
        'estado',
    ];
}
