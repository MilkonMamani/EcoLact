<?php

namespace App\Http\Controllers\Api\Planta;

use App\Application\Produccion\ObtenerInsumo;
use App\Application\Produccion\ObtenerInsumos;
use App\Application\Produccion\ObtenerMovimientosInsumos;
use App\Application\Produccion\RegistrarAjusteInsumo;
use App\Application\Produccion\RegistrarInsumo;
use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Shared\Enums\TipoInsumo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produccion\StoreInsumoRequest;
use App\Http\Requests\Produccion\StoreMovimientoInsumoRequest;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class InsumoController extends Controller
{
    public function __construct(
        private readonly RegistrarInsumo $registrarInsumo,
        private readonly RegistrarAjusteInsumo $registrarAjusteInsumo,
        private readonly ObtenerInsumos $obtenerInsumos,
        private readonly ObtenerInsumo $obtenerInsumo,
        private readonly ObtenerMovimientosInsumos $obtenerMovimientosInsumos,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', InsumoModel::class);

        $insumos = $this->obtenerInsumos->execute();

        $filtrados = collect($insumos)->filter(function (Insumo $insumo) use ($request): bool {
            if ($request->filled('buscar') && ! str_contains(mb_strtolower($insumo->nombre()), mb_strtolower($request->string('buscar')->toString()))) {
                return false;
            }

            if ($request->filled('nivel')) {
                $nivel = $request->string('nivel')->toString();
                $sinStock = $insumo->stockActual() <= 0.0;

                if ($nivel === 'sin_stock' && ! $sinStock) {
                    return false;
                }

                if ($nivel === 'bajo' && ! ($insumo->estaBajoMinimo() && ! $sinStock)) {
                    return false;
                }

                if ($nivel === 'normal' && ($insumo->estaBajoMinimo() || $sinStock)) {
                    return false;
                }
            }

            return true;
        })->values();

        return response()->json([
            'data' => [
                'kpis' => [
                    'insumos' => count($insumos),
                    'stock_bajo' => collect($insumos)->filter(fn (Insumo $i): bool => $i->estaBajoMinimo() && $i->stockActual() > 0.0)->count(),
                    'con_stock' => collect($insumos)->filter(fn (Insumo $i): bool => $i->stockActual() > 0.0)->count(),
                    'sin_stock' => collect($insumos)->filter(fn (Insumo $i): bool => $i->stockActual() <= 0.0)->count(),
                ],
                'filas' => $filtrados->map($this->serializar(...))->all(),
            ],
        ]);
    }

    public function show(InsumoModel $insumo): JsonResponse
    {
        Gate::authorize('view', $insumo);

        return response()->json([
            'data' => [
                'insumo' => $this->serializar($this->obtenerInsumo->execute($insumo->id)),
                'movimientos' => InsumoMovimientoModel::where('insumo_id', $insumo->id)
                    ->orderByDesc('fecha')
                    ->limit(10)
                    ->get()
                    ->map(fn (InsumoMovimientoModel $m): array => [
                        'id' => $m->id,
                        'tipo' => $m->tipo,
                        'motivo' => $m->motivo,
                        'cantidad' => (float) $m->cantidad,
                        'fecha' => $m->fecha?->format(DATE_ATOM),
                        'observacion' => $m->observacion,
                    ]),
            ],
        ]);
    }

    public function store(StoreInsumoRequest $request): JsonResponse
    {
        Gate::authorize('create', InsumoModel::class);

        $datos = $request->validated();

        $insumo = $this->registrarInsumo->execute(
            nombre: $datos['nombre'],
            unidadBase: $datos['unidad_base'] ?? $datos['unidad_medida'],
            stockMinimo: (float) $datos['stock_minimo'],
            stockInicial: (float) ($datos['stock_inicial'] ?? 0.0),
            tipo: TipoInsumo::from($datos['tipo'] ?? 'INSUMO'),
            descripcion: $datos['descripcion'] ?? null,
            registradoPorUserId: $request->user()->id,
        );

        return response()->json(['data' => $this->serializar($insumo)], 201);
    }

    public function movimientos(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', InsumoModel::class);

        $movimientos = collect($this->obtenerMovimientosInsumos->execute());
        $insumos = collect($this->obtenerInsumos->execute())->keyBy(fn (Insumo $i) => $i->id());

        $filtrados = $movimientos->filter(function (InsumoMovimiento $movimiento) use ($request): bool {
            if ($request->filled('insumo_id') && $movimiento->insumoId() !== $request->integer('insumo_id')) {
                return false;
            }

            if ($request->filled('tipo') && $movimiento->tipo() !== $request->string('tipo')->toString()) {
                return false;
            }

            if ($request->filled('desde') && $movimiento->fecha()->format('Y-m-d') < $request->string('desde')->toString()) {
                return false;
            }

            if ($request->filled('hasta') && $movimiento->fecha()->format('Y-m-d') > $request->string('hasta')->toString()) {
                return false;
            }

            return true;
        })->values();

        return response()->json([
            'data' => [
                'kpis' => [
                    'total' => $filtrados->count(),
                    'entradas' => $filtrados->filter(fn (InsumoMovimiento $m): bool => $m->tipo() === 'ENTRADA')->count(),
                    'salidas' => $filtrados->filter(fn (InsumoMovimiento $m): bool => $m->tipo() === 'SALIDA')->count(),
                ],
                'filas' => $filtrados->map(fn (InsumoMovimiento $m): array => [
                    'id' => $m->id(),
                    'insumo_id' => $m->insumoId(),
                    'insumo_nombre' => $insumos[$m->insumoId()]?->nombre(),
                    'tipo' => $m->tipo(),
                    'motivo' => $m->motivo(),
                    'cantidad' => $m->cantidad(),
                    'fecha' => $m->fecha()->format(DATE_ATOM),
                    'observacion' => $m->observacion(),
                ])->all(),
            ],
        ]);
    }

    public function registrarMovimiento(StoreMovimientoInsumoRequest $request, InsumoModel $insumo): JsonResponse
    {
        Gate::authorize('update', $insumo);

        $datos = $request->validated();

        $insumoActual = $this->obtenerInsumo->execute($insumo->id)
            ?? throw new DomainException('El insumo indicado no existe.');

        $nuevoStock = $datos['tipo'] === 'ENTRADA'
            ? $insumoActual->stockActual() + (float) $datos['cantidad']
            : $insumoActual->stockActual() - (float) $datos['cantidad'];

        if ($nuevoStock < 0.0) {
            return response()->json([
                'message' => sprintf('Stock insuficiente. Disponible: %s %s, solicitado: %s %s.', $insumoActual->stockActual(), $insumoActual->unidadBase(), $datos['cantidad'], $insumoActual->unidadBase()),
            ], 422);
        }

        $this->registrarAjusteInsumo->execute(
            insumoId: $insumo->id,
            nuevoStock: round($nuevoStock, 4),
            motivo: $datos['motivo'],
            registradoPorUserId: $request->user()->id,
            observacion: $datos['observacion'] ?? null,
        );

        return response()->json(['data' => $this->serializar($this->obtenerInsumo->execute($insumo->id))]);
    }

    /** @return array<string, mixed>|null */
    private function serializar(?Insumo $insumo): ?array
    {
        if ($insumo === null) {
            return null;
        }

        return [
            'id' => $insumo->id(),
            'nombre' => $insumo->nombre(),
            'unidad_medida' => $insumo->unidadBase(),
            'unidad_base' => $insumo->unidadBase(),
            'tipo' => $insumo->tipo()->value,
            'codigo' => $insumo->codigo(),
            'stock_actual' => $insumo->stockActual(),
            'stock_minimo' => $insumo->stockMinimo(),
            'estado' => $insumo->estado(),
            'esta_bajo_minimo' => $insumo->estaBajoMinimo(),
        ];
    }
}
