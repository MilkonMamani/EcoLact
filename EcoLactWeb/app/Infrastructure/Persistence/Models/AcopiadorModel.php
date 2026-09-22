<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcopiadorModel extends Model
{
    protected $table = 'acopiadores';

    protected $fillable = [
        'user_id',
        'zona_id',
        'dni',
        'nombres',
        'apellidos',
        'telefono',
        'estado',
        'fecha_ingreso',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
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
        return $this->hasMany(AcopiadorZonaHistorialModel::class, 'acopiador_id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(EntregaModel::class, 'acopiador_id');
    }

    public function recorridos(): HasMany
    {
        return $this->hasMany(RecorridoAcopioModel::class, 'acopiador_id');
    }
}
