<?php

namespace App\Http\Controllers\Api\Planta;

use App\Application\Produccion\BoletaIlegibleException;
use App\Application\Produccion\CalcularCompraInsumos;
use App\Application\Produccion\ObtenerCatalogoCompraInsumos;
use App\Application\Produccion\ProcesarBoletaCompra;
use App\Application\Produccion\RegistrarCompraInsumos;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\CompraInsumoController as AdminCompraInsumoController;
use App\Http\Requests\Produccion\CompraInsumosRequest;
use App\Http\Requests\Produccion\ProcesarBoletaCompraRequest;
use App\Infrastructure\Persistence\Models\CompraInsumoDetalleModel;
use App\Infrastructure\Persistence\Models\CompraInsumoModel;
use App\Infrastructure\Persistence\Models\InsumoModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Compra de insumos desde la app del encargado de planta, con los MISMOS Use
 * Cases que la web del administrador:
 *
 *   catalogo       insumos comprables (sin leche) y sus presentaciones activas,
 *   boleta         guarda la foto y devuelve lo que el OCR PROPONE (no registra nada),
 *   previsualizar  total en unidad base y stock resultante (no mueve inventario),
 *   store          confirma: crea la compra y sus entradas de stock (idempotente),
 *   index          últimas compras registradas.
 *
 * El OCR nunca confirma: la persona revisa y confirma con `store`.
 */
final class CompraInsumoController extends Controller
{
    private const DISCO = 'public';

    private const MENSAJE_ILEGIBLE = 'No se pudo leer la boleta automáticamente.';

    private const MAX_RECIENTES = 30;

    public function __construct(
        private readonly ObtenerCatalogoCompraInsumos $catalogo,
        private readonly ProcesarBoletaCompra $procesarBoleta,
        private readonly CalcularCompraInsumos $calcularCompra,
        private readonly RegistrarCompraInsumos $registrarCompra,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', InsumoModel::class);

        $compras = CompraInsumoModel::with('detalles.insumo')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(self::MAX_RECIENTES)
            ->get()
            ->map(fn (CompraInsumoModel $compra): array => [
                'id' => $compra->id,
                'origen' => $compra->origen,
                'fecha' => $compra->fecha?->format('Y-m-d'),
                'registrada' => $compra->created_at?->format(DATE_ATOM),
                'proveedor_comercial' => $compra->proveedor_comercial,
                'numero_comprobante' => $compra->numero_comprobante,
                'total' => $compra->total !== null ? (float) $compra->total : null,
                'lineas' => $compra->detalles->map(fn (CompraInsumoDetalleModel $d): array => [
                    'insumo_id' => $d->insumo_id,
                    'insumo' => $d->insumo?->nombre,
                    'presentacion' => $d->presentacion_nombre.' '.rtrim(rtrim(number_format((float) $d->presentacion_cantidad, 4, '.', ''), '0'), '.').' '.$d->presentacion_unidad,
                    'cantidad' => (float) $d->cantidad_presentaciones,
                    'cantidad_base' => (float) $d->cantidad_base,
                    'unidad_base' => $d->unidad_base,
                ])->values()->all(),
            ]);

        return response()->json(['data' => $compras->values()->all()]);
    }

    public function catalogo(): JsonResponse
    {
        Gate::authorize('viewAny', InsumoModel::class);

        return response()->json(['data' => $this->catalogo->execute()]);
    }

    /**
     * Mismo contrato que la web del administrador: `success=false` con 422 si
     * la boleta no se pudo leer, conservando la foto para registrar a mano.
     */
    public function boleta(ProcesarBoletaCompraRequest $request): JsonResponse
    {
        Gate::authorize('create', InsumoModel::class);

        $imagen = $request->file('imagen');
        $ruta = $imagen->storeAs(AdminCompraInsumoController::CARPETA_BOLETAS, 'boleta-'.Str::lower(Str::random(24)).'.'.$imagen->extension(), self::DISCO);
        $foto = ['imagen_boleta' => $ruta, 'imagen_url' => Storage::disk(self::DISCO)->url($ruta)];

        try {
            $lectura = $this->procesarBoleta->execute(Storage::disk(self::DISCO)->path($ruta));
        } catch (BoletaIlegibleException $excepcion) {
            Log::warning('OCR de la boleta de compra (app planta) falló.', ['error' => $excepcion->getMessage()]);

            return response()->json(['success' => false, 'mensaje' => self::MENSAJE_ILEGIBLE, 'lineas' => [], 'cabecera' => null] + $foto, 422);
        }

        if ($lectura['lineas'] === []) {
            return response()->json([
                'success' => false,
                'mensaje' => self::MENSAJE_ILEGIBLE.' No se reconoció ninguna línea de compra.',
                'lineas' => [],
                'cabecera' => $lectura['cabecera'],
            ] + $foto, 422);
        }

        return response()->json(['success' => true, 'mensaje' => null, 'lineas' => $lectura['lineas'], 'cabecera' => $lectura['cabecera']] + $foto);
    }

    public function previsualizar(CompraInsumosRequest $request): JsonResponse
    {
        Gate::authorize('create', InsumoModel::class);

        try {
            $calculo = $this->calcularCompra->execute($request->validated()['lineas']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $calculo]);
    }

    public function store(CompraInsumosRequest $request): JsonResponse
    {
        Gate::authorize('create', InsumoModel::class);

        $datos = $request->validated();

        try {
            $compra = $this->registrarCompra->execute($datos['lineas'], $datos, $request->user()->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $compra->id(), 'referencia' => $compra->referencia(), 'total' => $compra->total()]], 201);
    }
}
