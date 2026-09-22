<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SancionModel extends Model
{
    protected $table = 'sanciones';

    protected $fillable = [
        'proveedor_id',
        'analisis_id',
        'tipo',
        'nivel_advertencia',
        'descripcion',
        'tipo_penalizacion',
        'valor_penalizacion',
        'fecha',
        'estado',
        'creado_por_user_id',
    ];

    protected $casts = [
        'valor_penalizacion' => 'decimal:2',
        'fecha' => 'date',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(ProveedorModel::class, 'proveedor_id');
    }

    public function analisisCalidad(): BelongsTo
    {
        return $this->belongsTo(AnalisisCalidadModel::class, 'analisis_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function ajustesLiquidacion(): HasMany
    {
        return $this->hasMany(AjusteLiquidacionModel::class, 'sancion_id');
    }
}
