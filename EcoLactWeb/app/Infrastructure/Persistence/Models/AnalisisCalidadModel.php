<?php

namespace App\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AnalisisCalidadModel extends Model
{
    protected $table = 'analisis_calidad';

    protected $fillable = [
        'entrega_id',
        'imagen_path',
        'ocr_texto_original',
        'ocr_confianza',
        'estado',
        'resultado',
        'fecha_analisis',
        'confirmado_por_user_id',
        'observacion',
    ];

    protected $casts = [
        'ocr_confianza' => 'decimal:2',
        'fecha_analisis' => 'datetime',
    ];

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(EntregaModel::class, 'entrega_id');
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por_user_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(AnalisisCalidadDetalleModel::class, 'analisis_id');
    }

    public function sanciones(): HasMany
    {
        return $this->hasMany(SancionModel::class, 'analisis_id');
    }

    public function valorizacionEntrega(): HasOne
    {
        return $this->hasOne(ValorizacionEntregaModel::class, 'analisis_id');
    }
}
