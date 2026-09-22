<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RutaModel extends Model
{
    protected $table = 'rutas';

    protected $fillable = [
        'zona_id',
        'nombre',
        'descripcion',
        'estado',
    ];

    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaModel::class, 'zona_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RutaDetalleModel::class, 'ruta_id');
    }

    public function recorridos(): HasMany
    {
        return $this->hasMany(RecorridoAcopioModel::class, 'ruta_id');
    }

    public function proveedorRutaHistorial(): HasMany
    {
        return $this->hasMany(ProveedorRutaHistorialModel::class, 'ruta_id');
    }
}
