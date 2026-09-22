<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Comunicacion\ComunicadosFiltro;
use App\Application\Comunicacion\ComunicadosListadoData;
use App\Application\Comunicacion\Contracts\ComunicadosListadoQuery;
use App\Infrastructure\Persistence\Models\ComunicadoModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentComunicadosListadoQuery implements ComunicadosListadoQuery
{
    public function obtener(ComunicadosFiltro $filtro): ComunicadosListadoData
    {
        $comunicadoIds = $this->base($filtro)->pluck('c.id');

        $kpis = [
            'total' => (int) $comunicadoIds->count(),
            'publicados' => (int) $this->base($filtro)->where('c.estado', 'PUBLICADO')->count(),
            'borradores' => (int) $this->base($filtro)->where('c.estado', 'BORRADOR')->count(),
            'destinatariosAlcanzados' => (int) DB::table('comunicado_destinatarios')
                ->whereIn('comunicado_id', $comunicadoIds)
                ->count(),
        ];

        $filas = $this->base($filtro)
            ->select('c.id', 'c.titulo', 'c.tipo', 'c.fecha_publicacion', 'c.estado')
            ->selectRaw('(SELECT COUNT(*) FROM comunicado_destinatarios AS cd WHERE cd.comunicado_id = c.id) as destinatarios_count')
            ->orderByDesc('c.fecha_publicacion')
            ->get()
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'titulo' => $fila->titulo,
                'tipo' => $fila->tipo,
                'destinatarios' => (int) $fila->destinatarios_count,
                'fecha' => CarbonImmutable::parse($fila->fecha_publicacion, config('app.timezone'))->format('d/m/Y'),
                'estado' => $fila->estado,
            ])
            ->all();

        return new ComunicadosListadoData(kpis: $kpis, filas: $filas);
    }

    private function base(ComunicadosFiltro $filtro): Builder
    {
        $query = ComunicadoModel::query()->from('comunicados as c')->toBase();

        if ($filtro->busqueda !== null) {
            $query->where('c.titulo', 'like', '%'.$filtro->busqueda.'%');
        }

        if ($filtro->tipo !== null) {
            $query->where('c.tipo', $filtro->tipo);
        }

        if ($filtro->estado !== null) {
            $query->where('c.estado', $filtro->estado);
        }

        if ($filtro->fechaDesde !== null && $filtro->fechaHasta !== null) {
            $query->whereBetween('c.fecha_publicacion', [
                $filtro->fechaDesde->toDateTimeString(),
                $filtro->fechaHasta->toDateTimeString(),
            ]);
        }

        return $query;
    }
}
