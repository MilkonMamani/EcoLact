<?php

namespace App\Http\Controllers\Web\Admin;

use App\Application\Compra\AdjuntarComprobanteCompra;
use App\Application\Compra\CalcularCompra;
use App\Application\Compra\ComprasFiltro;
use App\Application\Compra\Contracts\ComprasQuery;
use App\Application\Compra\ObtenerDetalleCompra;
use App\Application\Compra\RecibirCompra;
use App\Application\Compra\RegistrarCompra;
use App\Application\Compra\RegistrarPagoCompra;
use App\Application\Produccion\BoletaIlegibleException;
use App\Application\Produccion\ObtenerCatalogoCompraInsumos;
use App\Application\Produccion\ProcesarBoletaCompra;
use App\Domain\Compra\CategoriaCompra;
use App\Domain\Compra\CategoriaCompraRepository;
use App\Domain\Compra\ComprobanteCompraRepository;
use App\Domain\Compra\ProveedorComercialRepository;
use App\Domain\Produccion\CompraInsumo;
use App\Domain\Shared\Enums\EstadoRecepcionCompra;
use App\Domain\Shared\Enums\MetodoPago;
use App\Domain\Shared\Enums\TipoDocumentoCompra;
use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\AdjuntarComprobanteCompraRequest;
use App\Http\Requests\Compra\PrevisualizarCompraRequest;
use App\Http\Requests\Compra\RegistrarPagoCompraRequest;
use App\Http\Requests\Compra\StoreCompraRequest;
use App\Http\Requests\Produccion\ProcesarBoletaCompraRequest;
use App\Http\Support\ComprobantesCompra;
use App\Infrastructure\Persistence\Models\CompraInsumoModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Inventario > Compras y gastos: el ÚNICO lugar donde se registran
 * adquisiciones (insumos, limpieza, refrigerios, equipos, servicios...).
 *
 *   index            indicadores + historial paginado con filtros,
 *   procesarBoleta   guarda la foto en el disco privado y devuelve lo que el
 *                    OCR PROPONE (no registra nada),
 *   previsualizar    calcula totales, conversiones y stock (no mueve nada),
 *   store            confirma la compra (RegistrarCompra): líneas, stock si ya
 *                    llegó, comprobantes y el pago REALMENTE hecho,
 *   show             ficha con Resumen, Artículos, Comprobantes y Pagos,
 *   registrarPago    pago parcial o del saldo (una salida de caja por pago),
 *   recibir          confirma la llegada: recién ahí entra el stock,
 *   adjuntarComprobante / verComprobante  documentos privados, con permiso.
 *
 * El actor es siempre el usuario autenticado. Los formularios llevan una
 * `clave_envio` de un solo uso: un doble clic no registra dos compras ni dos pagos.
 */
final class CompraController extends Controller
{
    private const MENSAJE_ILEGIBLE = 'No se pudo leer la boleta automáticamente.';

    public function __construct(
        private readonly ComprasQuery $consultas,
        private readonly ObtenerDetalleCompra $obtenerDetalle,
        private readonly ProcesarBoletaCompra $procesarBoleta,
        private readonly CalcularCompra $calcular,
        private readonly RegistrarCompra $registrar,
        private readonly RegistrarPagoCompra $registrarPago,
        private readonly RecibirCompra $recibirCompra,
        private readonly AdjuntarComprobanteCompra $adjuntar,
        private readonly ObtenerCatalogoCompraInsumos $catalogo,
        private readonly CategoriaCompraRepository $categorias,
        private readonly ProveedorComercialRepository $proveedores,
        private readonly ComprobanteCompraRepository $comprobantes,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CompraInsumoModel::class);

        $filtro = ComprasFiltro::resolver(
            desde: $request->string('desde')->toString() ?: null,
            hasta: $request->string('hasta')->toString() ?: null,
            busqueda: $request->string('q')->toString() ?: null,
            categoria: $request->string('categoria')->toString() ?: null,
            estadoPago: $request->string('estado')->toString() ?: null,
            destino: $request->string('destino')->toString() ?: null,
        );

        return view('admin.compras.index', [
            'filtro' => $filtro,
            'resumen' => $this->consultas->resumen($filtro),
            'compras' => $this->consultas->listar($filtro),
            'categorias' => $this->categorias->listar(),
            ...$this->datosFormulario(),
            // "Comprar este insumo" (Insumos) abre el formulario con el insumo elegido.
            'abrirFormulario' => $request->boolean('nueva') ? ($request->string('modo')->toString() === 'boleta' ? 'boleta' : 'manual') : null,
            'insumoPreseleccionado' => $request->integer('insumo') ?: null,
        ]);
    }

    public function show(int $compra): View
    {
        Gate::authorize('view', CompraInsumoModel::findOrFail($compra));

        return view('admin.compras.show', [
            'detalle' => $this->obtenerDetalle->execute($compra) ?? abort(404),
            'metodosPago' => MetodoPago::opciones(),
            'tiposDocumento' => TipoDocumentoCompra::opciones(),
        ]);
    }

    /** Rutas anteriores (admin/compras-insumo/{id}): la misma compra en su ficha nueva. */
    public function redirigirHistorica(int $compra): RedirectResponse
    {
        return redirect()->route('admin.compras.show', $compra, 301);
    }

    public function procesarBoleta(ProcesarBoletaCompraRequest $request): JsonResponse
    {
        Gate::authorize('create', CompraInsumoModel::class);

        $documento = ComprobantesCompra::guardar($request->file('imagen'), 'escaneo');
        $respuesta = ['documento_escaneado' => $documento['ruta']];

        try {
            $lectura = $this->procesarBoleta->execute(Storage::disk($documento['disco'])->path($documento['ruta']));
        } catch (BoletaIlegibleException $excepcion) {
            Log::warning('OCR de la boleta de compra falló.', ['error' => $excepcion->getMessage()]);

            // La foto se conserva: se puede registrar la compra a mano con la boleta como respaldo.
            return response()->json(['success' => false, 'mensaje' => self::MENSAJE_ILEGIBLE] + $respuesta, 422);
        }

        return response()->json([
            'success' => true,
            'lineas' => $lectura['lineas'],
            'cabecera' => $lectura['cabecera'],
            'advertencia' => $lectura['lineas'] === [] ? 'No se reconoció ningún artículo: agrégalos a mano mirando la boleta.' : null,
            // Solo en desarrollo: ayuda a ajustar el parser con boletas reales.
            'texto_ocr' => config('app.debug') ? $lectura['texto_ocr'] : null,
        ] + $respuesta);
    }

    public function previsualizar(PrevisualizarCompraRequest $request): JsonResponse
    {
        Gate::authorize('create', CompraInsumoModel::class);

        try {
            return response()->json(['data' => $this->calcular->execute($request->validated()['lineas'], $request->validated()['impuesto'] ?? 0)]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(StoreCompraRequest $request): RedirectResponse
    {
        Gate::authorize('create', CompraInsumoModel::class);

        $datos = $request->validated();
        $clave = $this->reservarEnvio('compra', $datos['clave_envio'] ?? null, $request->user()->id);

        if ($clave instanceof RedirectResponse) {
            return $clave;
        }

        $subidos = array_map(
            fn ($archivo): array => ['tipo' => $this->tipoDocumento($datos['tipo_comprobante'])->value] + ComprobantesCompra::guardar($archivo),
            $request->file('documentos', []),
        );
        $documentos = $subidos;

        if (! empty($datos['documento_escaneado'])) {
            $disco = Storage::disk(ComprobantesCompra::DISCO);
            $documentos[] = [
                'tipo' => $this->tipoDocumento($datos['tipo_comprobante'])->value,
                'disco' => ComprobantesCompra::DISCO,
                'ruta' => $datos['documento_escaneado'],
                'nombre_original' => 'Boleta escaneada.'.pathinfo($datos['documento_escaneado'], PATHINFO_EXTENSION),
                'mime' => $disco->mimeType($datos['documento_escaneado']) ?: null,
                'tamano' => (int) $disco->size($datos['documento_escaneado']),
                'procesado_ocr' => true,
            ];
        }

        try {
            $compra = $this->registrar->execute($datos['lineas'], ['documentos' => $documentos] + $datos, $request->user()->id);
        } catch (DomainException $e) {
            $this->liberarEnvio($clave);
            foreach ($subidos as $documento) {
                Storage::disk($documento['disco'])->delete($documento['ruta']);
            }

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $this->confirmarEnvio($clave, (int) $compra->id());

        return redirect()->route('admin.compras.show', $compra->id())->with('success', $this->mensajeRegistro($compra));
    }

    public function registrarPago(RegistrarPagoCompraRequest $request, int $compra): RedirectResponse
    {
        Gate::authorize('registrarPago', CompraInsumoModel::findOrFail($compra));

        $datos = $request->validated();
        $clave = $this->reservarEnvio('pago', $datos['clave_envio'] ?? null, $request->user()->id);

        if ($clave instanceof RedirectResponse) {
            return $clave;
        }

        try {
            $pago = $this->registrarPago->execute(
                compraId: $compra,
                monto: (float) $datos['monto'],
                metodoPago: MetodoPago::from($datos['metodo_pago']),
                numeroOperacion: $datos['numero_operacion'] ?? null,
                observacion: $datos['observacion'] ?? null,
                registradoPorUserId: $request->user()->id,
            );
        } catch (DomainException $e) {
            $this->liberarEnvio($clave);

            return redirect()->route('admin.compras.show', ['compra' => $compra, 'tab' => 'pagos'])->withInput()->with('error', $e->getMessage());
        }

        $this->confirmarEnvio($clave, $compra);

        return redirect()->route('admin.compras.show', ['compra' => $compra, 'tab' => 'pagos'])
            ->with('success', 'Pago de S/ '.number_format($pago->monto(), 2).' registrado. Se registró su salida en Caja.');
    }

    public function recibir(Request $request, int $compra): RedirectResponse
    {
        Gate::authorize('recibir', CompraInsumoModel::findOrFail($compra));

        try {
            $ingresadas = $this->recibirCompra->execute($compra, $request->user()->id);
        } catch (DomainException $e) {
            return redirect()->route('admin.compras.show', $compra)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.compras.show', ['compra' => $compra, 'tab' => 'articulos'])
            ->with('success', "Recepción confirmada. {$ingresadas} ".($ingresadas === 1 ? 'artículo ingresó' : 'artículos ingresaron').' al inventario.');
    }

    public function adjuntarComprobante(AdjuntarComprobanteCompraRequest $request, int $compra): RedirectResponse
    {
        Gate::authorize('adjuntarComprobante', CompraInsumoModel::findOrFail($compra));

        $documento = ComprobantesCompra::guardar($request->file('archivo'));

        try {
            $this->adjuntar->execute($compra, TipoDocumentoCompra::from($request->validated()['tipo']), $documento['disco'], $documento['ruta'], $documento['nombre_original'], $documento['mime'], $documento['tamano'], $request->user()->id);
        } catch (DomainException $e) {
            Storage::disk($documento['disco'])->delete($documento['ruta']);

            return redirect()->route('admin.compras.show', ['compra' => $compra, 'tab' => 'comprobantes'])->with('error', $e->getMessage());
        }

        return redirect()->route('admin.compras.show', ['compra' => $compra, 'tab' => 'comprobantes'])->with('success', 'Documento adjuntado.');
    }

    public function verComprobante(Request $request, int $compra, int $comprobante): StreamedResponse
    {
        Gate::authorize('verComprobante', CompraInsumoModel::findOrFail($compra));

        $documento = $this->comprobantes->buscarPorId($comprobante);

        // Un comprobante solo se sirve desde SU compra: el id no da acceso a documentos ajenos.
        if ($documento === null || $documento->compraInsumoId() !== $compra) {
            abort(404);
        }

        return ComprobantesCompra::respuesta($documento, $request->boolean('descargar')) ?? abort(404, 'El archivo del comprobante ya no está disponible.');
    }

    /**
     * Datos que necesita el formulario de registro.
     *
     * @return array<string, mixed>
     */
    private function datosFormulario(): array
    {
        return [
            'categoriasActivas' => array_values(array_filter($this->categorias->listar(), fn (CategoriaCompra $c): bool => $c->activa())),
            'catalogoCompra' => $this->catalogo->execute(),
            'proveedoresComerciales' => $this->proveedores->listarActivos(),
        ];
    }

    /** La boleta escaneada o adjunta se archiva con el tipo del comprobante de la compra. */
    private function tipoDocumento(string $tipoComprobante): TipoDocumentoCompra
    {
        return TipoDocumentoCompra::tryFrom($tipoComprobante) ?? TipoDocumentoCompra::OTRO;
    }

    /**
     * Reserva la clave de un solo uso del formulario. Si ya se usó, la
     * operación no se repite: se vuelve a la compra que registró.
     */
    private function reservarEnvio(string $operacion, ?string $clave, int $userId): string|RedirectResponse|null
    {
        if ($clave === null || $clave === '') {
            return null;
        }

        $llave = "compras:envio:{$operacion}:{$userId}:{$clave}";

        if (Cache::add($llave, 'EN_CURSO', now()->addHours(12))) {
            return $llave;
        }

        $compraId = Cache::get($llave);

        return is_int($compraId)
            ? redirect()->route('admin.compras.show', $compraId)->with('error', 'Esta operación ya se había registrado: no se volvió a registrar.')
            : redirect()->route('admin.compras.index')->with('error', 'Esta operación se está registrando. Espera un momento y revisa el historial.');
    }

    private function confirmarEnvio(?string $llave, int $compraId): void
    {
        if ($llave !== null) {
            Cache::put($llave, $compraId, now()->addHours(12));
        }
    }

    private function liberarEnvio(?string $llave): void
    {
        if ($llave !== null) {
            Cache::forget($llave);
        }
    }

    private function mensajeRegistro(CompraInsumo $compra): string
    {
        $mensaje = "Compra {$compra->codigo()} registrada.";

        $mensaje .= match ($compra->estadoRecepcion()) {
            EstadoRecepcionCompra::RECIBIDA => ' El stock de los insumos ya fue actualizado.',
            EstadoRecepcionCompra::PENDIENTE => ' El stock se actualizará al confirmar la recepción.',
            EstadoRecepcionCompra::NO_APLICA => '',
        };

        if ($compra->montoPagado() > 0.0) {
            $mensaje .= ' Salida de caja: S/ '.number_format($compra->montoPagado(), 2).'.';
        }
        if ($compra->saldoPendiente() > 0.0) {
            $mensaje .= ' Saldo pendiente: S/ '.number_format($compra->saldoPendiente(), 2).'.';
        }

        return $mensaje;
    }
}
