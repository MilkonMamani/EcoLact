<?php

namespace App\Http\Controllers\Web\Ventas;

use App\Application\Ventas\ObtenerProductoPrecios;
use App\Application\Ventas\RegistrarProductoPrecio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreProductoPrecioRequest;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoPrecioModel;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Configuración financiera exclusiva de ADMINISTRADOR (ProductoPrecioPolicy):
 * el precio de cada producto por tipo de cliente (proveedor, mayorista,
 * público).
 */
final class ProductoPrecioController extends Controller
{
    public function __construct(
        private readonly RegistrarProductoPrecio $registrarProductoPrecio,
        private readonly ObtenerProductoPrecios $obtenerProductoPrecios,
    ) {}

    public function index(ProductoModel $producto): View
    {
        Gate::authorize('viewAny', ProductoPrecioModel::class);

        return view('admin.producto-precios.index', [
            'producto' => $producto,
            'precios' => $this->obtenerProductoPrecios->execute($producto->id),
        ]);
    }

    public function store(StoreProductoPrecioRequest $request): RedirectResponse
    {
        Gate::authorize('create', ProductoPrecioModel::class);

        $datos = $request->validated();

        $this->registrarProductoPrecio->execute(
            productoId: (int) $datos['producto_id'],
            tipoCliente: $datos['tipo_cliente'],
            precio: (float) $datos['precio'],
            creadoPorUserId: $request->user()->id,
            fechaInicio: isset($datos['fecha_inicio']) ? new DateTimeImmutable($datos['fecha_inicio']) : null,
        );

        return redirect()->back()->with('success', 'Precio de producto registrado correctamente.');
    }
}
