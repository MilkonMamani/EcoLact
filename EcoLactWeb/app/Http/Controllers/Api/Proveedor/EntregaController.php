<?php

namespace App\Http\Controllers\Api\Proveedor;

use App\Application\Acopio\ObtenerEntrega;
use App\Application\Acopio\ObtenerEntregasProveedor;
use App\Application\Calidad\ObtenerAnalisisProveedor;
use App\Domain\Entrega\Entrega;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\EntregaModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class EntregaController extends Controller
{
    public function __construct(
        private readonly ObtenerEntregasProveedor $obtenerEntregasProveedor,
        private readonly ObtenerEntrega $obtenerEntrega,
        private readonly ObtenerAnalisisProveedor $obtenerAnalisisProveedor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EntregaModel::class);

        $proveedorId = $request->user()->proveedor?->id;
        $entregas = $proveedorId !== null ? $this->obtenerEntregasProveedor->execute($proveedorId) : [];

        $resultadosPorEntrega = collect($this->obtenerAnalisisProveedor->execute($request->user()->id))
            ->mapWithKeys(fn ($a) => [$a->entregaId() => $a->resultado()?->value])
            ->all();

        $filtradas = collect($entregas)->filter(function (Entrega $entrega) use ($request): bool {
            if ($request->filled('estado') && $entrega->estado()->value !== $request->string('estado')->toString()) {
                return false;
            }

            return true;
        })->sortByDesc(fn (Entrega $e) => $e->fechaHora())->values();

        return response()->json([
            'data' => [
                'kpis' => [
                    'entregas' => $filtradas->count(),
                    'litros' => round((float) $filtradas->sum(fn (Entrega $e): float => $e->litros()), 2),
                    'analizadas' => $filtradas->filter(fn (Entrega $e): bool => $e->estado()->value === 'ANALIZADA')->count(),
                    'pendientes' => $filtradas->filter(fn (Entrega $e): bool => $e->estado()->value === 'PENDIENTE_ANALISIS')->count(),
                ],
                'filas' => $filtradas->map(fn (Entrega $e): array => [
                    ...$this->serializar($e),
                    'resultado' => $resultadosPorEntrega[$e->id()] ?? null,
                ])->all(),
            ],
        ]);
    }

    public function show(Request $request, EntregaModel $entrega): JsonResponse
    {
        Gate::authorize('view', $entrega);

        $analisis = collect($this->obtenerAnalisisProveedor->execute($request->user()->id))
            ->first(fn ($item) => $item->entregaId() === $entrega->id);

        $entrega->load(['zona', 'acopiador']);
        $acopiador = $entrega->acopiador;

        return response()->json([
            'data' => [
                'entrega' => [
                    ...$this->serializar($this->obtenerEntrega->execute($entrega->id)),
                    // Solo en el detalle: nombres legibles para el proveedor (sin ids técnicos).
                    'zona_nombre' => $entrega->zona?->nombre,
                    'acopiador_nombre' => $acopiador === null ? null : trim($acopiador->nombres.' '.$acopiador->apellidos),
                ],
                'analisis' => $analisis === null ? null : [
                    'id' => $analisis->id(),
                    'resultado' => $analisis->resultado()?->value,
                    'fecha_analisis' => $analisis->fechaAnalisis()->format(DATE_ATOM),
                ],
            ],
        ]);
    }

    /** @return array<string, mixed>|null */
    private function serializar(?Entrega $entrega): ?array
    {
        if ($entrega === null) {
            return null;
        }

        return [
            'id' => $entrega->id(),
            'fecha_hora' => $entrega->fechaHora()->format(DATE_ATOM),
            'litros' => $entrega->litros(),
            'zona_id' => $entrega->zonaId(),
            'estado' => $entrega->estado()->value,
        ];
    }
}
