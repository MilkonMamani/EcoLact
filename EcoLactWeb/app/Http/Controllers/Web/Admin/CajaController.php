<?php

namespace App\Http\Controllers\Web\Admin;

use App\Application\Caja\CajaFiltro;
use App\Application\Caja\Contracts\CajaQuery;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\MovimientoCajaModel;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Finanzas > Caja: saldo, totales de hoy e historial del dinero que entra y
 * sale. Es SOLO lectura: los movimientos nacen en Ventas, Pagos e Insumos
 * (RegistrarMovimientoCaja); aquí no se crean, editan ni borran.
 */
final class CajaController extends Controller
{
    public function __construct(
        private readonly CajaQuery $caja,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MovimientoCajaModel::class);

        $filtro = CajaFiltro::resolver(
            desde: $request->string('desde')->toString() ?: null,
            hasta: $request->string('hasta')->toString() ?: null,
            busqueda: $request->string('q')->toString() ?: null,
            tipo: $request->string('tipo')->toString() ?: null,
            categoria: $request->string('categoria')->toString() ?: null,
        );

        // Sin JS, "?movimiento=ID" abre el detalle en la misma pantalla.
        $seleccion = $request->integer('movimiento') > 0 ? $this->caja->buscar($request->integer('movimiento')) : null;

        return view('admin.caja.index', [
            'resumen' => $this->caja->resumen(DateTimeImmutable::createFromInterface(now())),
            'movimientos' => $this->caja->movimientos($filtro),
            'filtro' => $filtro,
            'seleccion' => $seleccion,
        ]);
    }

    /** Fragmento HTML del panel de detalle, pedido por fetch al pulsar una fila. */
    public function panel(int $movimiento): View
    {
        Gate::authorize('viewAny', MovimientoCajaModel::class);

        return view('admin.caja._detalle', [
            'movimiento' => $this->caja->buscar($movimiento) ?? abort(404),
        ]);
    }
}
