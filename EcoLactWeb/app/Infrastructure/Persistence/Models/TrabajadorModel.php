<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
