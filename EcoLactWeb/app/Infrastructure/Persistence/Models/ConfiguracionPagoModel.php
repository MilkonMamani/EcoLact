<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfiguracionPagoModel extends Model
{
    protected $table = 'configuraciones_pago';

    protected $fillable = [
        'tipo_beneficiario',
        'rol_id',
        'tipo_calculo',
        'monto_base',
        'periodicidad',
        'aplica_calidad',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'observacion',
        'creado_por_user_id',
    ];

    protected $casts = [
        'monto_base' => 'decimal:2',
        'aplica_calidad' => 'boolean',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function rol(): BelongsTo
    {
        return $this->belongsTo(RoleModel::class, 'rol_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function obligaciones(): HasMany
    {
        return $this->hasMany(ObligacionPagoModel::class, 'configuracion_pago_id');
    }
}
