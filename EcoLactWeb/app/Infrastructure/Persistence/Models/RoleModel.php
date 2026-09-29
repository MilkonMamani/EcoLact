<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoleModel extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'nombre',
        'descripcion',
        'es_remunerable',
        'controla_asistencia',
    ];

    protected $casts = [
        'es_remunerable' => 'boolean',
        'controla_asistencia' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
