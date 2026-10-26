<?php

namespace App\Http\Controllers\Web\Produccion;

use App\Application\Produccion\Contracts\ProductosListadoQuery;
use App\Application\Produccion\ProductosFiltro;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * INVENTARIO -> Productos (ADMINISTRADOR): consulta de productos terminados y
 * gestión de sus precios, en una sola pantalla (fila desplegable). Un producto
 * no se crea, edita ni desactiva desde aquí: nace desde su RECETA, y su stock
 * lo mueven la producción (al finalizar) y las ventas.
 */
final class ProductoController extends Controller
{
    public function __construct(
        private readonly ProductosListadoQuery $productosListadoQuery,
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
}
