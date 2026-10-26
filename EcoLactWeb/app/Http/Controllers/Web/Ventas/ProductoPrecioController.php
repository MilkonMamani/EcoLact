<?php

namespace App\Http\Controllers\Web\Ventas;

use App\Application\Ventas\ConfigurarPreciosProducto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\UpdateProductoPreciosRequest;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoPrecioModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Configuración financiera exclusiva de ADMINISTRADOR (ProductoPrecioPolicy):
 * los precios de cada producto por tipo de cliente (proveedor, público,
 * mayorista desde N unidades). Se gestionan desde la fila desplegable de
 * INVENTARIO -> Productos; ambas acciones vuelven ahí con el producto abierto.
 */
final class ProductoPrecioController extends Controller
{
    public function __construct(
        private readonly ConfigurarPreciosProducto $configurarPrecios,
    ) {}

    public function update(UpdateProductoPreciosRequest $request, ProductoModel $producto): RedirectResponse
    {
        Gate::authorize('create', ProductoPrecioModel::class);

        $numero = fn (string $campo): ?float => $request->filled($campo) ? (float) $request->input($campo) : null;

        $this->configurarPrecios->execute(
            productoId: $producto->id,
            precioProveedor: $numero('precio_proveedor'),
            precioPublico: $numero('precio_publico'),
            precioMayorista: $numero('precio_mayorista'),
            cantidadMinimaMayorista: $numero('cantidad_minima_mayorista'),
            creadoPorUserId: $request->user()->id,
        );

        return $this->volver($producto)->with('success', 'Precios guardados correctamente.');
    }

    public function destroy(ProductoModel $producto): RedirectResponse
    {
        Gate::authorize('create', ProductoPrecioModel::class);

        $this->configurarPrecios->retirarTodos($producto->id);

        return $this->volver($producto)->with('success', 'Se eliminaron los precios del producto.');
    }

    private function volver(ProductoModel $producto): RedirectResponse
    {
        return redirect()->to(url()->previous(route('admin.productos.index')).'#producto-'.$producto->id)
            ->with('productoAbierto', $producto->id);
    }
}
