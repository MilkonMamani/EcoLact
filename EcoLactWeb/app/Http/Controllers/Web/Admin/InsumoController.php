<?php

namespace App\Http\Controllers\Web\Admin;

use App\Application\Produccion\ActualizarInsumo;
use App\Application\Produccion\AjustarStockInsumo;
use App\Application\Produccion\ObtenerCatalogoCompraInsumos;
use App\Application\Produccion\ObtenerInsumo;
use App\Application\Produccion\ObtenerInsumos;
use App\Application\Produccion\ObtenerInventarioInsumos;
use App\Application\Produccion\ObtenerPanelInsumo;
use App\Application\Produccion\RegistrarInsumo;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\UnidadMedida;
use App\Domain\Shared\Enums\MotivoAjusteInsumo;
use App\Domain\Shared\Enums\TipoInsumo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produccion\AjustarStockInsumoRequest;
use App\Http\Requests\Produccion\StoreInsumoRequest;
use App\Http\Requests\Produccion\UpdateInsumoRequest;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Puente HTTP -> Application para el catálogo e inventario de insumos,
 * vista administrativa (Gate::before ya concede acceso global), con vistas
 * propias (admin.insumos.*): tabla + panel lateral del insumo elegido
 * (?insumo=ID). Cada acción hace UNA cosa:
 *
 *   store            crea la ficha del insumo (stock 0, sin compra),
 *   update           edita la ficha,
 *   registrarAjuste  corrige el stock (merma, pérdida, corrección, inventario).
 *
 * Las presentaciones viven en PresentacionInsumoController y las compras
 * (las únicas que suben stock) en Compras y gastos (CompraController):
 * Insumos no registra compras, solo enlaza allí con el insumo elegido.
 */
final class InsumoController extends Controller
{
    public function __construct(
        private readonly RegistrarInsumo $registrarInsumo,
        private readonly ActualizarInsumo $actualizarInsumo,
        private readonly AjustarStockInsumo $ajustarStock,
        private readonly ObtenerInventarioInsumos $obtenerInventario,
        private readonly ObtenerInsumo $obtenerInsumo,
        private readonly ObtenerInsumos $obtenerInsumos,
        private readonly ObtenerPanelInsumo $obtenerPanel,
        private readonly ObtenerCatalogoCompraInsumos $obtenerCatalogoCompra,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', InsumoModel::class);

        $inventario = $this->obtenerInventario->execute(
            busqueda: $request->query('buscar'),
            nivel: $request->boolean('bajo_minimo') ? 'bajo' : $request->query('nivel'),
            tipo: $request->query('tipo'),
            conMovimientos: false,
        );

        $seleccionado = $request->integer('insumo');

        return view('admin.insumos.index', $inventario + [
            'tipos' => TipoInsumo::opciones(),
            'unidades' => UnidadMedida::opciones(),
            'familiasUnidad' => UnidadMedida::familias(),
            'panel' => $seleccionado > 0 && InsumoModel::whereKey($seleccionado)->exists() ? $this->obtenerPanel->execute($seleccionado) : null,
            'catalogoCompra' => $this->obtenerCatalogoCompra->execute(),
            'motivosAjuste' => MotivoAjusteInsumo::opciones(),
            'insumosAjuste' => $this->insumosParaAjuste(),
        ]);
    }

    /**
     * Historial de movimientos de insumos, paginado en SQL, con su origen
     * (compra, producción, ajuste). Cada ingreso por compra enlaza con la
     * compra que lo generó (origen COMPRA_INSUMO #id). Excluye los movimientos
     * de leche por Acopio (se consultan en Acopio); no los borra.
     */
    public function movimientos(Request $request): View
    {
        Gate::authorize('viewAny', InsumoModel::class);

        $fecha = fn (string $campo): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($campo)) === 1 ? (string) $request->query($campo) : null;
        $filtros = [
            'insumo_id' => $request->integer('insumo_id') ?: null,
            'tipo' => in_array($request->query('tipo'), ['ENTRADA', 'SALIDA'], true) ? $request->query('tipo') : null,
            'desde' => $fecha('desde'),
            'hasta' => $fecha('hasta'),
        ];

        $movimientos = InsumoMovimientoModel::query()
            ->with(['insumo:id,nombre,unidad_base', 'registradoPor:id,username'])
            ->leftJoin('compras_insumo as c', function ($join): void {
                $join->on('c.id', '=', 'insumo_movimientos.origen_id')
                    ->where('insumo_movimientos.origen_tipo', '=', InsumoMovimiento::ORIGEN_COMPRA_INSUMO);
            })
            ->select('insumo_movimientos.*', 'c.codigo as compra_codigo')
            ->sinMovimientosDeAcopio()
            ->when($filtros['insumo_id'], fn ($q, $id) => $q->where('insumo_movimientos.insumo_id', $id))
            ->when($filtros['tipo'], fn ($q, $tipo) => $q->where('insumo_movimientos.tipo', $tipo))
            ->when($filtros['desde'], fn ($q, $desde) => $q->where('insumo_movimientos.fecha', '>=', $desde.' 00:00:00'))
            ->when($filtros['hasta'], fn ($q, $hasta) => $q->where('insumo_movimientos.fecha', '<=', $hasta.' 23:59:59'))
            ->orderByDesc('insumo_movimientos.fecha')
            ->orderByDesc('insumo_movimientos.id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.insumos.movimientos', [
            'movimientos' => $movimientos,
            'filtros' => $filtros,
            'insumos' => InsumoModel::orderBy('nombre')->pluck('nombre', 'id')->all(),
        ]);
    }

    /**
     * Fragmento del panel lateral (lo pide la tabla por fetch al elegir una fila).
     */
    public function panel(InsumoModel $insumo): View
    {
        Gate::authorize('view', $insumo);

        return view('admin.insumos._panel', ['panel' => $this->obtenerPanel->execute($insumo->id)]);
    }

    public function show(InsumoModel $insumo): View
    {
        Gate::authorize('view', $insumo);

        return view('admin.insumos.show', [
            'insumo' => $this->obtenerInsumo->execute($insumo->id),
            'movimientos' => $insumo->movimientos()->sinMovimientosDeAcopio()->orderByDesc('fecha')->orderByDesc('id')->limit(25)->get(),
        ]);
    }

    /**
     * Crea solo la FICHA del insumo (stock 0). Vuelve con el insumo elegido para
     * que el siguiente paso, definir cómo se compra (presentaciones), quede a mano.
     */
    public function store(StoreInsumoRequest $request): RedirectResponse
    {
        Gate::authorize('create', InsumoModel::class);

        $datos = $request->validated();

        try {
            $insumo = $this->registrarInsumo->execute(
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

        return redirect()->route('admin.insumos.index', ['insumo' => $insumo->id()])
            ->with('success', "Insumo {$insumo->nombre()} creado con stock 0. Ahora agrega cómo se compra (presentación) para poder registrar sus compras.");
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
                unidadBase: $datos['unidad_base'] ?? null,
                userId: $request->user()->id,
            );
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Insumo actualizado correctamente.');
    }

    public function registrarAjuste(AjustarStockInsumoRequest $request, InsumoModel $insumo): RedirectResponse
    {
        Gate::authorize('update', $insumo);

        $datos = $request->validated();

        try {
            $this->ajustarStock->execute(
                insumoId: $insumo->id,
                motivo: MotivoAjusteInsumo::from($datos['motivo']),
                cantidad: (float) $datos['cantidad'],
                registradoPorUserId: $request->user()->id,
                direccion: $datos['direccion'] ?? null,
                observacion: $datos['observacion'] ?? null,
            );
        } catch (DomainException $e) {
            return redirect()->route('admin.insumos.index', ['insumo' => $insumo->id])->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.insumos.index', ['insumo' => $insumo->id])->with('success', 'Ajuste de stock registrado.');
    }

    /**
     * Insumos activos que se pueden ajustar desde "Ajustar stock" del encabezado
     * (la leche incluida: solo admite restas).
     *
     * @return list<array{id: int, nombre: string, unidad: string, stock: float, action: string, solo_resta: bool}>
     */
    private function insumosParaAjuste(): array
    {
        return array_map(fn ($insumo): array => [
            'id' => (int) $insumo->id(),
            'nombre' => $insumo->nombre(),
            'unidad' => UnidadMedida::simbolo($insumo->unidadBase()),
            'stock' => $insumo->stockActual(),
            'action' => route('admin.insumos.ajuste', $insumo->id()),
            'solo_resta' => $insumo->esLeche(),
        ], $this->obtenerInsumos->execute());
    }
}
