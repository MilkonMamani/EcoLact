<?php

namespace App\Http\Controllers\Web\Admin;

use App\Application\Produccion\ActualizarInsumo;
use App\Application\Produccion\ObtenerInsumo;
use App\Application\Produccion\ObtenerInventarioInsumos;
use App\Application\Produccion\RegistrarAjusteInsumo;
use App\Application\Produccion\RegistrarCompraInsumo;
use App\Application\Produccion\RegistrarInsumo;
use App\Domain\Produccion\UnidadMedida;
use App\Domain\Shared\Enums\TipoInsumo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produccion\StoreCompraInsumoRequest;
use App\Http\Requests\Produccion\StoreInsumoRequest;
use App\Http\Requests\Produccion\StoreMovimientoInsumoRequest;
use App\Http\Requests\Produccion\UpdateInsumoRequest;
use App\Infrastructure\Persistence\Models\InsumoModel;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Puente HTTP -> Application para el catálogo e inventario de insumos,
 * vista administrativa (Gate::before ya concede acceso global). Reutiliza
 * los MISMOS Use Cases que Web\Planta\InsumoController, con vistas propias
 * (admin.insumos.*) en vez de las operativas de ENCARGADO_PLANTA.
 */
final class InsumoController extends Controller
{
    public function __construct(
        private readonly RegistrarInsumo $registrarInsumo,
        private readonly ActualizarInsumo $actualizarInsumo,
        private readonly RegistrarCompraInsumo $registrarCompraInsumo,
        private readonly RegistrarAjusteInsumo $registrarAjusteInsumo,
        private readonly ObtenerInventarioInsumos $obtenerInventario,
        private readonly ObtenerInsumo $obtenerInsumo,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', InsumoModel::class);

        $inventario = $this->obtenerInventario->execute(
            busqueda: $request->query('buscar'),
            nivel: $request->boolean('bajo_minimo') ? 'bajo' : $request->query('nivel'),
            tipo: $request->query('tipo'),
        );

        return view('admin.insumos.index', $inventario + [
            'tipos' => TipoInsumo::opciones(),
            'unidades' => UnidadMedida::opciones(),
        ]);
    }

    public function show(InsumoModel $insumo): View
    {
        Gate::authorize('view', $insumo);

        return view('admin.insumos.show', [
            'insumo' => $this->obtenerInsumo->execute($insumo->id),
            'movimientos' => $insumo->movimientos()->orderByDesc('fecha')->orderByDesc('id')->limit(25)->get(),
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
                stockInicial: (float) ($datos['stock_inicial'] ?? 0.0),
                tipo: TipoInsumo::from($datos['tipo']),
                descripcion: $datos['descripcion'] ?? null,
                registradoPorUserId: $request->user()->id,
            );
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Insumo registrado correctamente.');
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

    public function registrarCompra(StoreCompraInsumoRequest $request, InsumoModel $insumo): RedirectResponse
    {
        Gate::authorize('update', $insumo);

        $datos = $request->validated();

        try {
            $this->registrarCompraInsumo->execute(
                insumoId: $insumo->id,
                cantidad: (float) $datos['cantidad'],
                registradoPorUserId: $request->user()->id,
                observacion: $datos['observacion'] ?? null,
                unidad: $datos['unidad'] ?? null,
            );
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Compra de insumo registrada correctamente.');
    }

    public function registrarAjuste(StoreMovimientoInsumoRequest $request, InsumoModel $insumo): RedirectResponse
    {
        Gate::authorize('update', $insumo);

        $datos = $request->validated();
        $actual = $this->obtenerInsumo->execute($insumo->id);

        try {
            $nuevoStock = $datos['tipo'] === 'ENTRADA'
                ? $actual->stockActual() + (float) $datos['cantidad']
                : $actual->stockActual() - (float) $datos['cantidad'];

            if ($nuevoStock < 0.0) {
                throw new DomainException(sprintf(
                    'No hay suficiente %s. Disponible: %s %s, solicitado: %s %s.',
                    $actual->nombre(), $actual->stockActual(), $actual->unidadBase(), $datos['cantidad'], $actual->unidadBase(),
                ));
            }

            $this->registrarAjusteInsumo->execute(
                insumoId: $insumo->id,
                nuevoStock: round($nuevoStock, 4),
                motivo: $datos['motivo'],
                registradoPorUserId: $request->user()->id,
                observacion: $datos['observacion'] ?? null,
            );
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Ajuste registrado correctamente.');
    }
}
