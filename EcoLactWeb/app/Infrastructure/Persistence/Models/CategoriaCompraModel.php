<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaCompraModel extends Model
{
    protected $table = 'categorias_compra';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'tratamiento_sugerido',
        'categoria_caja',
        'orden',
        'estado',
    ];
}
