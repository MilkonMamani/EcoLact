<?php

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoría de producto terminado (Quesos, Yogures...). `nombre_normalizado`
 * es único y se calcula solo: dos nombres que solo difieren en mayúsculas o
 * espacios son la misma categoría.
 */
class CategoriaProductoModel extends Model
{
    protected $table = 'categorias_productos';

    protected $fillable = [
        'nombre',
        'estado',
    ];

    protected static function booted(): void
    {
        static::saving(function (CategoriaProductoModel $categoria): void {
            $categoria->nombre = self::limpiar($categoria->nombre);
            $categoria->nombre_normalizado = self::normalizar($categoria->nombre);
        });
    }

    public static function limpiar(string $nombre): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $nombre));
    }

    public static function normalizar(string $nombre): string
    {
        return mb_strtolower(self::limpiar($nombre));
    }

    public static function existeNombre(string $nombre): bool
    {
        return self::where('nombre_normalizado', self::normalizar($nombre))->exists();
    }

    public function scopeActivas(Builder $consulta): Builder
    {
        return $consulta->where('estado', 'ACTIVA');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(ProductoModel::class, 'categoria_id');
    }
}
