<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Zona\DelimitacionZona;
use App\Domain\Zona\Zona;
use App\Domain\Zona\ZonaRepository;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Support\Facades\DB;

class EloquentZonaRepository implements ZonaRepository
{
    public function guardar(Zona $zona): Zona
    {
        $model = ZonaModel::create($this->toModelData($zona));

        return $this->toEntity($model);
    }

    public function actualizar(Zona $zona): Zona
    {
        $model = ZonaModel::findOrFail($zona->id());
        $model->fill($this->toModelData($zona));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?Zona
    {
        $model = ZonaModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function eliminar(int $id): void
    {
        ZonaModel::whereKey($id)->delete();
    }

    /**
     * @return array<string, int>
     */
    public function contarDependencias(int $id): array
    {
        $tablas = [
            'ruta(s)' => 'rutas',
            'proveedor(es) asignado(s)' => 'proveedores',
            'acopiador(es) asignado(s)' => 'acopiadores',
            'registro(s) en el historial de proveedores' => 'proveedor_zona_historial',
            'registro(s) en el historial de acopiadores' => 'acopiador_zona_historial',
            'entrega(s) registrada(s)' => 'entregas',
            'comunicado(s)' => 'comunicados',
        ];

        $conteos = [];

        foreach ($tablas as $etiqueta => $tabla) {
            $conteos[$etiqueta] = DB::table($tabla)->where('zona_id', $id)->count();
        }

        return $conteos;
    }

    public function buscarPorNombre(string $nombre): ?Zona
    {
        $model = ZonaModel::where('nombre', $nombre)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function guardarDelimitacion(int $zonaId, string $colorMapa, ?DelimitacionZona $delimitacion): void
    {
        ZonaModel::whereKey($zonaId)->update([
            'color_mapa' => $colorMapa,
            'poligono_geojson' => $delimitacion !== null ? json_encode($delimitacion->aGeoJson()) : null,
        ]);
    }

    /**
     * @return Zona[]
     */
    public function listar(): array
    {
        return ZonaModel::orderBy('nombre')
            ->get()
            ->map(fn (ZonaModel $model): Zona => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Zona[]
     */
    public function listarActivas(): array
    {
        return ZonaModel::where('estado', 'ACTIVO')
            ->get()
            ->map(fn (ZonaModel $model): Zona => $this->toEntity($model))
            ->all();
    }

    private function toEntity(ZonaModel $model): Zona
    {
        return new Zona(
            id: $model->id,
            nombre: $model->nombre,
            descripcion: $model->descripcion,
            estado: $model->estado,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(Zona $zona): array
    {
        return [
            'nombre' => $zona->nombre(),
            'descripcion' => $zona->descripcion(),
            'estado' => $zona->estado(),
        ];
    }
}
