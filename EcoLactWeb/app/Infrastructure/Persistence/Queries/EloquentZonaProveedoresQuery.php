<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Zona\Contracts\ZonaProveedoresQuery;
use App\Application\Zona\ProveedorAsignadoFila;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentZonaProveedoresQuery implements ZonaProveedoresQuery
{
    /**
     * @return list<ProveedorAsignadoFila>
     */
    public function deZona(int $zonaId): array
    {
        return $this->base()
            ->where('r.zona_id', $zonaId)
            ->orderBy('r.nombre')
            ->orderBy('rd.orden_visita')
            ->get()
            ->map(fn (object $fila): ProveedorAsignadoFila => $this->aFila($fila))
            ->all();
    }

    /**
     * @return list<ProveedorAsignadoFila>
     */
    public function sinAsignar(): array
    {
        return $this->base()
            ->whereNull('rd.id')
            ->where('p.estado', 'ACTIVO')
            ->orderBy('p.nombres')
            ->orderBy('p.apellidos')
            ->get()
            ->map(fn (object $fila): ProveedorAsignadoFila => $this->aFila($fila))
            ->all();
    }

    public function contarSinAsignar(): int
    {
        return (int) ProveedorModel::query()
            ->where('estado', 'ACTIVO')
            ->whereDoesntHave('rutaDetalle')
            ->count();
    }

    /**
     * @return list<ProveedorAsignadoFila>
     */
    public function buscar(string $texto, int $limite = 20): array
    {
        $palabras = preg_split('/\s+/', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($palabras === []) {
            return [];
        }

        $query = $this->base()->where('p.estado', 'ACTIVO');

        foreach ($palabras as $palabra) {
            $patron = '%'.addcslashes($palabra, '%_\\').'%';

            $query->where(function (Builder $grupo) use ($patron): void {
                $grupo->where('p.dni', 'like', $patron)
                    ->orWhere('p.nombres', 'like', $patron)
                    ->orWhere('p.apellidos', 'like', $patron);
            });
        }

        return $query
            ->orderBy('p.nombres')
            ->orderBy('p.apellidos')
            ->limit($limite)
            ->get()
            ->map(fn (object $fila): ProveedorAsignadoFila => $this->aFila($fila))
            ->all();
    }

    private function base(): Builder
    {
        return DB::table('proveedores as p')
            ->leftJoin('ruta_detalles as rd', 'rd.proveedor_id', '=', 'p.id')
            ->leftJoin('rutas as r', 'r.id', '=', 'rd.ruta_id')
            ->leftJoin('zonas as z', 'z.id', '=', 'r.zona_id')
            ->leftJoin('zonas as zr', 'zr.id', '=', 'p.zona_id')
            ->select([
                'p.id as proveedor_id',
                'p.nombres',
                'p.apellidos',
                'p.dni',
                'rd.id as detalle_id',
                'rd.orden_visita',
                'rd.latitud',
                'rd.longitud',
                'rd.referencia_ubicacion',
                'r.id as ruta_id',
                'r.nombre as ruta_nombre',
                'z.id as zona_id',
                'z.nombre as zona_nombre',
                'zr.id as zona_registrada_id',
                'zr.nombre as zona_registrada_nombre',
            ]);
    }

    private function aFila(object $fila): ProveedorAsignadoFila
    {
        return new ProveedorAsignadoFila(
            proveedorId: (int) $fila->proveedor_id,
            nombre: trim($fila->nombres.' '.$fila->apellidos),
            dni: $fila->dni,
            detalleId: $fila->detalle_id !== null ? (int) $fila->detalle_id : null,
            zonaId: $fila->zona_id !== null ? (int) $fila->zona_id : null,
            zonaNombre: $fila->zona_nombre,
            rutaId: $fila->ruta_id !== null ? (int) $fila->ruta_id : null,
            rutaNombre: $fila->ruta_nombre,
            ordenVisita: $fila->orden_visita !== null ? (int) $fila->orden_visita : null,
            latitud: $fila->latitud !== null ? (float) $fila->latitud : null,
            longitud: $fila->longitud !== null ? (float) $fila->longitud : null,
            referenciaUbicacion: $fila->referencia_ubicacion,
            zonaRegistradaId: $fila->zona_registrada_id !== null ? (int) $fila->zona_registrada_id : null,
            zonaRegistradaNombre: $fila->zona_registrada_nombre,
        );
    }
}
