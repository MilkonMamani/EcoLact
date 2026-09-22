<?php

namespace App\Http\Controllers\Web\Produccion;

use App\Application\Produccion\Contracts\ProductoDetalleQuery;
use App\Application\Produccion\Contracts\ProductosListadoQuery;
use App\Application\Produccion\ProductosFiltro;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * INVENTARIO -> Productos (ADMINISTRADOR): inventario de productos terminados,
 * de SOLO CONSULTA. Un producto no se crea, edita ni desactiva desde aquí: nace
 * y se configura desde su RECETA, y su stock lo mueven la producción (al
 * finalizar) y las ventas.
 */
final class ProductoController extends Controller
{
    public function __construct(
        private readonly ProductosListadoQuery $productosListadoQuery,
        private readonly ProductoDetalleQuery $productoDetalleQuery,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ProductoModel::class);

        $filtro = ProductosFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
        );

        return view('admin.productos.index', [
            'datos' => $this->productosListadoQuery->obtener($filtro),
        ]);
    }

    public function show(ProductoModel $producto): View
    {
        Gate::authorize('view', $producto);

        return view('admin.productos.show', [
            'detalle' => $this->productoDetalleQuery->obtener($producto->id),
        ]);
    }
}
