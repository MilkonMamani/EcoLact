<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZonaModel extends Model
{
    protected $table = 'zonas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
    ];

    public function proveedores(): HasMany
    {
        return $this->hasMany(ProveedorModel::class, 'zona_id');
    }

    public function rutas(): HasMany
    {
        return $this->hasMany(RutaModel::class, 'zona_id');
    }

    public function acopiadores(): HasMany
    {
        return $this->hasMany(AcopiadorModel::class, 'zona_id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(EntregaModel::class, 'zona_id');
    }

    public function proveedorZonaHistorial(): HasMany
    {
        return $this->hasMany(ProveedorZonaHistorialModel::class, 'zona_id');
    }

    public function acopiadorZonaHistorial(): HasMany
    {
        return $this->hasMany(AcopiadorZonaHistorialModel::class, 'zona_id');
    }
}
