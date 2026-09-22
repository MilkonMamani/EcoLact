<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComunicadoModel extends Model
{
    protected $table = 'comunicados';

    protected $fillable = [
        'titulo',
        'contenido',
        'tipo',
        'alcance',
        'zona_id',
        'fecha_publicacion',
        'fecha_evento',
        'creado_por_user_id',
        'estado',
    ];

    protected $casts = [
        'fecha_publicacion' => 'datetime',
        'fecha_evento' => 'datetime',
    ];

    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaModel::class, 'zona_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(ComunicadoDestinatarioModel::class, 'comunicado_id');
    }
}
