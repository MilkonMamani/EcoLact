<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComunicadoDestinatarioModel extends Model
{
    protected $table = 'comunicado_destinatarios';

    protected $fillable = [
        'comunicado_id',
        'user_id',
        'leido',
        'fecha_lectura',
    ];

    protected $casts = [
        'leido' => 'boolean',
        'fecha_lectura' => 'datetime',
    ];

    public function comunicado(): BelongsTo
    {
        return $this->belongsTo(ComunicadoModel::class, 'comunicado_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
