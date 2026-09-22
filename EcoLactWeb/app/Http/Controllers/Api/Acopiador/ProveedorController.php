<?php

namespace App\Http\Controllers\Api\Acopiador;

use App\Application\Calidad\ObtenerAnalisisPorProveedor;
use App\Application\Proveedor\ObtenerProveedor;
use App\Application\Proveedor\ObtenerProveedores;
use App\Domain\Proveedor\Proveedor;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ProveedorController extends Controller
{
    public function __construct(
        private readonly ObtenerProveedor $obtenerProveedor,
        private readonly ObtenerProveedores $obtenerProveedores,
        private readonly ObtenerAnalisisPorProveedor $obtenerAnalisisPorProveedor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProveedorModel::class);

        $zonaId = $request->user()->acopiador?->zona_id;
        $proveedores = $zonaId !== null ? $this->obtenerProveedores->execute($zonaId) : [];

        $ids = collect($proveedores)->map(fn (Proveedor $p): int => $p->id())->all();

        $resumenEntregas = EntregaModel::whereIn('proveedor_id', $ids)
            ->selectRaw('proveedor_id, count(*) as entregas, max(fecha_hora) as ultima_entrega')
            ->groupBy('proveedor_id')
            ->get()
            ->keyBy('proveedor_id');

        $filtrados = collect($proveedores)->filter(function (Proveedor $p) use ($request): bool {
            if ($request->filled('estado') && $p->estado()->value !== $request->string('estado')->toString()) {
                return false;
            }

            if ($request->filled('buscar')) {
                $texto = mb_strtolower($request->string('buscar')->toString());
                $coincide = str_contains(mb_strtolower($p->nombres().' '.$p->apellidos()), $texto)
                    || str_contains($p->dni(), $texto);

                if (! $coincide) {
                    return false;
                }
            }

            return true;
        })->values();

        return response()->json([
            'data' => [
                'kpis' => [
                    'proveedores' => count($proveedores),
                    'activos' => collect($proveedores)->filter(fn (Proveedor $p): bool => $p->estado()->value === 'ACTIVO')->count(),
                    'inactivos' => collect($proveedores)->filter(fn (Proveedor $p): bool => $p->estado()->value !== 'ACTIVO')->count(),
                ],
                'filas' => $filtrados->map(fn (Proveedor $p): array => [
                    ...$this->serializarProveedor($p),
                    'entregas' => (int) ($resumenEntregas[$p->id()]->entregas ?? 0),
                    'ultima_entrega' => $resumenEntregas[$p->id()]->ultima_entrega ?? null,
                ])->all(),
            ],
        ]);
    }

    public function show(ProveedorModel $proveedor): JsonResponse
    {
        Gate::authorize('view', $proveedor);

        $resumen = [
            'entregas_totales' => EntregaModel::where('proveedor_id', $proveedor->id)->count(),
            'litros_entregados' => (float) EntregaModel::where('proveedor_id', $proveedor->id)->sum('litros'),
            'ultima_entrega' => EntregaModel::where('proveedor_id', $proveedor->id)->max('fecha_hora'),
            'pendientes_analisis' => EntregaModel::where('proveedor_id', $proveedor->id)->where('estado', 'PENDIENTE_ANALISIS')->count(),
        ];

        $ultimoAnalisis = collect($this->obtenerAnalisisPorProveedor->execute($proveedor->id))
            ->sortByDesc(fn ($a) => $a->fechaAnalisis())
            ->first();

        return response()->json([
            'data' => [
                'proveedor' => $this->serializarProveedor($this->obtenerProveedor->execute($proveedor->id)),
                'resumen' => $resumen,
                'ultimo_analisis_resultado' => $ultimoAnalisis?->resultado()?->value,
            ],
        ]);
    }

    /** @return array<string, mixed>|null */
    private function serializarProveedor(?Proveedor $proveedor): ?array
    {
        if ($proveedor === null) {
            return null;
        }

        return [
            'id' => $proveedor->id(),
            'dni' => $proveedor->dni(),
            'nombres' => $proveedor->nombres(),
            'apellidos' => $proveedor->apellidos(),
            'nombre_completo' => $proveedor->nombreCompleto(),
            'telefono' => $proveedor->telefono(),
            'zona_id' => $proveedor->zonaId(),
            'estado' => $proveedor->estado()->value,
        ];
    }
}
