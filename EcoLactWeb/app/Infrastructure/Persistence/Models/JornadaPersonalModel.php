<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JornadaPersonalModel extends Model
{
    protected $table = 'jornadas_personal';

    protected $fillable = [
        'trabajador_id',
        'rol_id',
        'fecha',
        'estado',
        'origen',
        'hora_marcacion',
        'observacion',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_marcacion' => 'datetime',
    ];

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(TrabajadorModel::class, 'trabajador_id');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(RoleModel::class, 'rol_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
