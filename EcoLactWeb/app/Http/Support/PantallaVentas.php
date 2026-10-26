<?php

namespace App\Http\Support;

use App\Application\Proveedor\ObtenerProveedores;
use App\Application\Ventas\ConsultarSaldoDescuentoProveedor;
use App\Application\Ventas\Contracts\VentasListadoQuery;
use App\Application\Ventas\ObtenerCatalogoVenta;
use App\Application\Ventas\ObtenerCompradores;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Application\Ventas\VentasFiltro;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Produccion\ProductoStockRepository;
use App\Domain\Proveedor\Proveedor;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Ventas\Comprador;
use App\Domain\Ventas\VentaProducto;
use App\Http\Requests\Ventas\StoreVentaProductoRequest;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Presentación compartida de "Ventas / POS" para ADMINISTRADOR (admin.ventas.*)
 * y PERSONAL DE VENTAS (ventas.ventas.*): las dos pestañas (Nueva venta e
 * Historial), el registro y el detalle. Solo cambian el layout y el prefijo
 * de rutas; Use Cases y reglas son los mismos (RegistrarVentaProducto).
 */
final class PantallaVentas
{
    public function __construct(
        private readonly RegistrarVentaProducto $registrarVenta,
        private readonly VentasListadoQuery $ventasListado,
        private readonly ObtenerCatalogoVenta $catalogo,
        private readonly ObtenerCompradores $compradores,
        private readonly ObtenerProveedores $proveedores,
        private readonly ConsultarSaldoDescuentoProveedor $saldoProveedor,
        private readonly ProductoRepository $productos,
        private readonly ProductoStockRepository $stock,
    ) {}

    /**
     * @param  'admin'|'ventas'  $portal
     */
    public function index(string $portal, Request $request, ?VentaProductoModel $seleccion = null): View
    {
        $filtro = VentasFiltro::resolver(
            periodo: $request->string('periodo')->toString() ?: null,
            desde: $request->string('desde')->toString() ?: null,
            hasta: $request->string('hasta')->toString() ?: null,
            busqueda: $request->string('q')->toString() ?: null,
            tipoCliente: null,
            formaPago: $request->string('forma_pago')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
        );

        $tab = $seleccion !== null || $request->query('tab') === 'historial' ? 'historial' : 'nueva';

        return view($portal === 'admin' ? 'ventas.ventas.general' : 'ventas-panel.ventas.index', [
            'portal' => $portal,
            'tab' => $tab,
            'filtro' => $filtro,
            'datos' => $this->ventasListado->obtener($filtro),
            'catalogo' => $this->catalogo->execute(),
            'compradores' => array_map(fn (Comprador $c): array => self::comprador($c), $this->compradores->execute()),
            'proveedores' => array_values(array_map(
                fn (Proveedor $p): array => ['id' => $p->id(), 'nombre' => $p->nombreCompleto(), 'documento' => $p->dni()],
                array_filter($this->proveedores->execute(), fn (Proveedor $p): bool => $p->estado() === EstadoUsuario::ACTIVO),
            )),
            'seleccion' => $seleccion !== null ? $this->detalle($seleccion) : null,
        ]);
    }

    /**
     * Con fetch (panel lateral) devuelve solo el fragmento; si no, la pantalla
     * con el Historial y el detalle abierto (enlaces directos, p. ej. desde Caja).
     *
     * @param  'admin'|'ventas'  $portal
     */
    public function show(string $portal, Request $request, VentaProductoModel $venta): View
    {
        if ($request->ajax()) {
            return view('ventas.pos._detalle', ['venta' => $this->detalle($venta)]);
        }

        return $this->index($portal, $request, $venta);
    }

    /**
     * @param  'admin'|'ventas'  $portal
     */
    public function store(string $portal, StoreVentaProductoRequest $request): RedirectResponse
    {
        $datos = $request->venta();

        try {
            $venta = $this->registrarVenta->execute(
                productoId: $datos['producto_id'],
                cantidad: $datos['cantidad'],
                formaPago: $datos['forma_pago'],
                registradoPorUserId: $request->user()->id,
                proveedorId: $datos['proveedor_id'],
                compradorId: $datos['comprador_id'],
            );
        } catch (DomainException|InvalidArgumentException $e) {
            return redirect()->route($portal.'.ventas.index')->withInput()->with('venta_error', $e->getMessage());
        }

        return redirect()->route($portal.'.ventas.index')->with('venta_registrada', $this->confirmacion($venta));
    }

    public function saldoProveedor(int $proveedorId): JsonResponse
    {
        try {
            return response()->json(['saldo' => $this->saldoProveedor->execute($proveedorId)]);
        } catch (DomainException $e) {
            return response()->json(['saldo' => null, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * @return array{id: int, nombre: string, documento: ?string, telefono: ?string}
     */
    public static function comprador(Comprador $comprador): array
    {
        return [
            'id' => (int) $comprador->id(),
            'nombre' => $comprador->nombre(),
            'documento' => $comprador->documento(),
            'telefono' => $comprador->telefono(),
        ];
    }

    public static function cantidad(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }

    public static function tipoPrecio(string $tipoCliente): string
    {
        return match ($tipoCliente) {
            'PROVEEDOR' => 'Proveedor',
            'MAYORISTA' => 'Mayorista',
            default => 'Público',
        };
    }

    public static function formaPago(string $formaPago): string
    {
        return $formaPago === 'DESCUENTO_PLANILLA' ? 'Descuento semanal' : 'Contado';
    }

    /**
     * Lo que ve quien vende tras confirmar (Nueva venta). Solo hay entrada de
     * caja si entró dinero real (CONTADO).
     *
     * @return array<string, mixed>
     */
    private function confirmacion(VentaProducto $venta): array
    {
        $modelo = VentaProductoModel::with(['proveedor', 'comprador'])->findOrFail($venta->id());
        $stockDespues = $this->stock->buscarPorProductoId($venta->productoId())?->cantidadActual() ?? 0.0;

        return [
            'id' => $venta->id(),
            'codigo' => VentaProducto::codigo((int) $venta->id()),
            'cliente' => self::cliente($modelo),
            'esProveedor' => $venta->proveedorId() !== null,
            'producto' => $this->producto($venta->productoId()),
            'cantidad' => self::cantidad($venta->cantidad()),
            'total' => $venta->importeTotal(),
            'stockAntes' => self::cantidad($stockDespues + $venta->cantidad()),
            'stockDespues' => self::cantidad($stockDespues),
            'descuento' => $venta->formaPago() === 'DESCUENTO_PLANILLA',
        ];
    }

    /**
     * Detalle comercial de una venta (sin datos técnicos de inventario).
     *
     * @return array<string, mixed>
     */
    private function detalle(VentaProductoModel $venta): array
    {
        $venta->loadMissing(['proveedor', 'comprador', 'registradoPor']);

        return [
            'id' => $venta->id,
            'codigo' => VentaProducto::codigo($venta->id),
            'fecha' => $venta->fecha->timezone(config('app.timezone'))->format('d/m/Y · H:i'),
            'cliente' => self::cliente($venta),
            'clienteDocumento' => $venta->proveedor?->dni ?? $venta->comprador?->documento,
            'producto' => $this->producto($venta->producto_id),
            'cantidad' => self::cantidad((float) $venta->cantidad),
            'tipoPrecio' => self::tipoPrecio($venta->tipo_cliente),
            'precioUnitario' => (float) $venta->precio_unitario,
            'total' => (float) $venta->importe_total,
            'formaPago' => self::formaPago($venta->forma_pago),
            'descuento' => $venta->forma_pago === 'DESCUENTO_PLANILLA',
            'registradoPor' => $venta->registradoPor?->username ?? '—',
            'estado' => $venta->estado,
        ];
    }

    private static function cliente(VentaProductoModel $venta): string
    {
        if ($venta->proveedor !== null) {
            return trim($venta->proveedor->nombres.' '.$venta->proveedor->apellidos);
        }

        return $venta->comprador?->nombre ?? 'Público general';
    }

    /** "Queso Paria · 1 kg" (nombre completo en productos sin presentación). */
    private function producto(int $productoId): string
    {
        return $this->productos->buscarPorId($productoId)?->etiqueta() ?? '—';
    }
}
