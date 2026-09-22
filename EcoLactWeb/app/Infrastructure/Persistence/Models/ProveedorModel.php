<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProveedorModel extends Model
{
    protected $table = 'proveedores';

    protected $fillable = [
        'user_id',
        'zona_id',
        'dni',
        'nombres',
        'apellidos',
        'telefono',
        'qr_token',
        'estado',
        'fecha_registro',
        'advertencias_count',
    ];

    protected $casts = [
        'fecha_registro' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaModel::class, 'zona_id');
    }

    public function zonaHistorial(): HasMany
    {
        return $this->hasMany(ProveedorZonaHistorialModel::class, 'proveedor_id');
    }

    /**
     * Asignación vigente a una ruta (a lo sumo una fila, por la unicidad de
     * ruta_detalles.proveedor_id). La zona/ruta "actuales" del proveedor se
     * derivan de aquí, nunca de un campo editable directo.
     */
    public function rutaDetalle(): HasOne
    {
        return $this->hasOne(RutaDetalleModel::class, 'proveedor_id');
    }

    public function rutaHistorial(): HasMany
    {
        return $this->hasMany(ProveedorRutaHistorialModel::class, 'proveedor_id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(EntregaModel::class, 'proveedor_id');
    }

    public function sanciones(): HasMany
    {
        return $this->hasMany(SancionModel::class, 'proveedor_id');
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(LiquidacionModel::class, 'proveedor_id');
    }
}
