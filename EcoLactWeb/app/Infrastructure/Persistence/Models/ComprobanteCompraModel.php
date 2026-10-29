<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComprobanteCompraModel extends Model
{
    protected $table = 'comprobantes_compra';

    protected $fillable = [
        'compra_insumo_id',
        'tipo',
        'disco',
        'ruta',
        'nombre_original',
        'mime',
        'tamano',
        'procesado_ocr',
        'subido_por_user_id',
    ];

    protected $casts = [
        'tamano' => 'integer',
        'procesado_ocr' => 'boolean',
    ];

    public function compra(): BelongsTo
    {
        return $this->belongsTo(CompraInsumoModel::class, 'compra_insumo_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por_user_id');
    }
}
