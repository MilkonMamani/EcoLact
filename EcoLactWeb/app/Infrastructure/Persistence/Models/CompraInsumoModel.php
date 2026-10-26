<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompraInsumoModel extends Model
{
    protected $table = 'compras_insumo';

    protected $fillable = [
        'origen',
        'proveedor_comercial',
        'ruc',
        'numero_comprobante',
        'fecha',
        'total',
        'imagen_boleta',
        'observacion',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total' => 'decimal:2',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(CompraInsumoDetalleModel::class, 'compra_insumo_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
