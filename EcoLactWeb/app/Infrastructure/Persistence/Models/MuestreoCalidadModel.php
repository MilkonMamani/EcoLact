<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MuestreoCalidadModel extends Model
{
    protected $table = 'muestreos_calidad';

    protected $fillable = [
        'entrega_id',
        'seleccionado_por_user_id',
        'metodo_seleccion',
        'tipo_control',
        'fecha_seleccion',
        'fecha_captura_local',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'fecha_seleccion' => 'datetime',
        'fecha_captura_local' => 'datetime',
    ];

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(EntregaModel::class, 'entrega_id');
    }

    public function seleccionadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seleccionado_por_user_id');
    }
}
