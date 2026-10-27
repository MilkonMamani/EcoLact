<?php

namespace App\Http\Controllers\Web\Planta;

use App\Application\Produccion\ActualizarInsumo;
use App\Application\Produccion\ObtenerInsumo;
use App\Application\Produccion\ObtenerInsumos;
use App\Application\Produccion\ObtenerInventarioInsumos;
use App\Application\Produccion\ObtenerMovimientosInsumos;
use App\Application\Produccion\RegistrarAjusteInsumo;
use App\Application\Produccion\RegistrarInsumo;
use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\UnidadMedida;
use App\Domain\Shared\Enums\TipoInsumo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produccion\StoreInsumoRequest;
use App\Http\Requests\Produccion\StoreMovimientoInsumoRequest;
use App\Http\Requests\Produccion\UpdateInsumoRequest;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portal del ENCARGADO_PLANTA: "Insumos" (Inventario + Movimientos). Ya NO
 * se comparte con ADMIN (ver Web\Admin\InsumoController, que sirve su
 * propia vista con los mismos Use Cases).
 */
final class InsumoController extends Controller
{
    public function __construct(
        private readonly RegistrarInsumo $registrarInsumo,
        private readonly ActualizarInsumo $actualizarInsumo,
        private readonly RegistrarAjusteInsumo $registrarAjusteInsumo,
        private readonly ObtenerInventarioInsumos $obtenerInventario,
        private readonly ObtenerInsumos $obtenerInsumos,
        private readonly ObtenerInsumo $obtenerInsumo,
        private readonly ObtenerMovimientosInsumos $obtenerMovimientosInsumos,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', InsumoModel::class);

        $inventario = $this->obtenerInventario->execute(
            busqueda: $request->query('buscar'),
            nivel: $request->query('nivel'),
            tipo: $request->query('tipo'),
        );

        return view('planta.insumos.index', $inventario + [
            'tipos' => TipoInsumo::opciones(),
            'unidades' => UnidadMedida::opciones(),
        ]);
    }

    public function show(InsumoModel $insumo): View
    {
        Gate::authorize('view', $insumo);

        return view('planta.insumos.show', [
            'insumo' => $this->obtenerInsumo->execute($insumo->id),
            'movimientos' => InsumoMovimientoModel::where('insumo_id', $insumo->id)->orderByDesc('fecha')->orderByDesc('id')->limit(25)->get(),
        ]);
    }

    public function store(StoreInsumoRequest $request): RedirectResponse
    {
        Gate::authorize('create', InsumoModel::class);

        $datos = $request->validated();

        try {
            $this->registrarInsumo->execute(
                nombre: $datos['nombre'],
                unidadBase: $datos['unidad_base'],
                stockMinimo: (float) $datos['stock_minimo'],
                tipo: TipoInsumo::from($datos['tipo']),
                descripcion: $datos['descripcion'] ?? null,
                estado: $datos['estado'],
            );
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('planta.insumos.index')->with('success', 'Insumo registrado correctamente.');
    }

    public function update(UpdateInsumoRequest $request, InsumoModel $insumo): RedirectResponse
    {
        Gate::authorize('update', $insumo);

        $datos = $request->validated();

        try {
            $this->actualizarInsumo->execute(
                insumoId: $insumo->id,
                nombre: $datos['nombre'],
                descripcion: $datos['descripcion'] ?? null,
                stockMinimo: (float) $datos['stock_minimo'],
                estado: $datos['estado'],
                tipo: isset($datos['tipo']) ? TipoInsumo::from($datos['tipo']) : null,
            );
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Insumo actualizado correctamente.');
    }

    public function movimientos(Request $request): View
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

        $kpis = [
            'total' => $filtrados->count(),
            'entradas' => $filtrados->filter(fn (InsumoMovimiento $m): bool => $m->tipo() === 'ENTRADA')->count(),
            'salidas' => $filtrados->filter(fn (InsumoMovimiento $m): bool => $m->tipo() === 'SALIDA')->count(),
        ];

        return view('planta.insumos.movimientos', [
            'movimientos' => $filtrados->all(),
            'insumosPorId' => $insumos->map(fn (Insumo $i) => $i->nombre()),
            'insumosDisponibles' => $insumos->map(fn (Insumo $i) => $i->nombre())->sort(),
            'kpis' => $kpis,
        ]);
    }

    public function registrarMovimiento(StoreMovimientoInsumoRequest $request, InsumoModel $insumo): RedirectResponse
    {
        Gate::authorize('update', $insumo);

        $datos = $request->validated();

        // La diferencia se aplica con el insumo bloqueado (sin pisar consumos
        // que hayan llegado entre la consulta y la confirmación); una salida
        // mayor al stock la rechaza el caso de uso.
        try {
            $this->registrarAjusteInsumo->porDiferencia(
                insumoId: $insumo->id,
                direccion: $datos['tipo'],
                cantidad: (float) $datos['cantidad'],
                motivo: $datos['motivo'],
                registradoPorUserId: $request->user()->id,
                observacion: $datos['observacion'] ?? null,
            );
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('planta.insumos.show', $insumo->id)->with('success', 'Movimiento registrado correctamente.');
    }
}
