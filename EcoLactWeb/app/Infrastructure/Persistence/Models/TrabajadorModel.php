<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrabajadorModel extends Model
{
    protected $table = 'trabajadores';

    protected $fillable = [
        'user_id',
        'dni',
        'nombres',
        'apellidos',
        'telefono',
        'estado',
        'fecha_ingreso',
        'fecha_salida',
        'qr_token',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_salida' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Periodos en que estuvo ACTIVO (historial de desactivaciones/reactivaciones). */
    public function periodos(): HasMany
    {
        return $this->hasMany(TrabajadorPeriodoActividadModel::class, 'trabajador_id')->orderBy('fecha_inicio');
    }
}
