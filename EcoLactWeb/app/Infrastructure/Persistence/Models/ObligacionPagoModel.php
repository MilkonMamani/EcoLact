<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ObligacionPagoModel extends Model
{
    protected $table = 'obligaciones_pago';

    protected $fillable = [
        'tipo_beneficiario',
        'proveedor_id',
        'user_id',
        'rol_id',
        'beneficiario_nombre',
        'configuracion_pago_id',
        'liquidacion_id',
        'periodo_inicio',
        'periodo_fin',
        'periodicidad',
        'concepto',
        'base_calculo',
        'monto_calculado',
        'bonificacion',
        'descuento',
        'dias_trabajados',
        'valor_dia',
        'acumulado',
        'estado',
        'clave_unicidad',
        'clave_abierta',
        'creado_por_user_id',
    ];

    protected $casts = [
        'periodo_inicio' => 'date',
        'periodo_fin' => 'date',
        'base_calculo' => 'array',
        'monto_calculado' => 'decimal:2',
        'bonificacion' => 'decimal:2',
        'descuento' => 'decimal:2',
        'valor_dia' => 'decimal:2',
        'acumulado' => 'decimal:2',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(ProveedorModel::class, 'proveedor_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(RoleModel::class, 'rol_id');
    }

    public function configuracion(): BelongsTo
    {
        return $this->belongsTo(ConfiguracionPagoModel::class, 'configuracion_pago_id');
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(LiquidacionModel::class, 'liquidacion_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(ObligacionPagoDetalleModel::class, 'obligacion_pago_id');
    }

    public function pago(): HasOne
    {
        return $this->hasOne(PagoModel::class, 'obligacion_pago_id');
    }
}
