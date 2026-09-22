<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReglaPagoCalidadModel extends Model
{
    protected $table = 'reglas_pago_calidad';

    protected $fillable = [
        'resultado_calidad',
        'tipo_ajuste',
        'valor_ajuste',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected $casts = [
        'valor_ajuste' => 'decimal:4',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function valorizacionesEntrega(): HasMany
    {
        return $this->hasMany(ValorizacionEntregaModel::class, 'regla_pago_id');
    }
}
