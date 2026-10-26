<?php

namespace App\Http\Controllers\Web\Admin;

use App\Application\Produccion\BoletaIlegibleException;
use App\Application\Produccion\CalcularCompraInsumos;
use App\Application\Produccion\ObtenerDetalleCompraInsumo;
use App\Application\Produccion\ProcesarBoletaCompra;
use App\Application\Produccion\RegistrarCompraInsumos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produccion\CompraInsumosRequest;
use App\Http\Requests\Produccion\ProcesarBoletaCompraRequest;
use App\Infrastructure\Persistence\Models\InsumoModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Compra de insumos desde una boleta escaneada o registrada a mano:
 *
 *   procesarBoleta  guarda la foto original y devuelve lo que el OCR PROPONE
 *                   (no registra nada),
 *   previsualizar   calcula el total en unidad base y el stock resultante
 *                   (no mueve inventario),
 *   store           confirma: crea la compra y sus movimientos de entrada
 *                   (acepta una foto adjunta si no se escaneó),
 *   show            consulta una compra ya registrada.
 *
 * El actor es siempre el usuario autenticado, nunca un valor del cliente.
 */
final class CompraInsumoController extends Controller
{
    private const DISCO = 'public';

    /** Fotos originales de las boletas; CompraInsumosRequest solo acepta rutas de aquí. */
    public const CARPETA_BOLETAS = 'compras-insumo/boletas';

    private const MENSAJE_ILEGIBLE = 'No se pudo leer la boleta automáticamente.';

    public function __construct(
        private readonly ProcesarBoletaCompra $procesarBoleta,
        private readonly CalcularCompraInsumos $calcularCompra,
        private readonly RegistrarCompraInsumos $registrarCompra,
        private readonly ObtenerDetalleCompraInsumo $obtenerDetalle,
    ) {}

    public function procesarBoleta(ProcesarBoletaCompraRequest $request): JsonResponse
    {
        Gate::authorize('create', InsumoModel::class);

        $imagen = $request->file('imagen');
        // Nombre aleatorio: nunca se sobrescribe una boleta anterior.
        $ruta = $imagen->storeAs(self::CARPETA_BOLETAS, 'boleta-'.Str::lower(Str::random(24)).'.'.$imagen->extension(), self::DISCO);
        $foto = ['imagen_boleta' => $ruta, 'imagen_url' => Storage::disk(self::DISCO)->url($ruta)];

        try {
            $lectura = $this->procesarBoleta->execute(Storage::disk(self::DISCO)->path($ruta));
        } catch (BoletaIlegibleException $excepcion) {
            Log::warning('OCR de la boleta de compra falló.', ['error' => $excepcion->getMessage()]);

            // La foto se conserva: se puede registrar la compra a mano con la boleta como respaldo.
            return response()->json(['success' => false, 'mensaje' => self::MENSAJE_ILEGIBLE] + $foto, 422);
        }

        if ($lectura['lineas'] === []) {
            return response()->json([
                'success' => false,
                'mensaje' => self::MENSAJE_ILEGIBLE.' No se reconoció ninguna línea de compra.',
                'cabecera' => $lectura['cabecera'],
                'texto_ocr' => config('app.debug') ? $lectura['texto_ocr'] : null,
            ] + $foto, 422);
        }

        return response()->json([
            'success' => true,
            'lineas' => $lectura['lineas'],
            'cabecera' => $lectura['cabecera'],
            // Solo en desarrollo: ayuda a ajustar el parser con boletas reales.
            'texto_ocr' => config('app.debug') ? $lectura['texto_ocr'] : null,
        ] + $foto);
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

    public function store(CompraInsumosRequest $request): RedirectResponse
    {
        Gate::authorize('create', InsumoModel::class);

        $datos = $request->validated();

        // Compra manual con foto adjunta: se guarda igual que la de una boleta escaneada.
        if ($request->hasFile('imagen')) {
            $imagen = $request->file('imagen');
            $datos['imagen_boleta'] = $imagen->storeAs(self::CARPETA_BOLETAS, 'boleta-'.Str::lower(Str::random(24)).'.'.$imagen->extension(), self::DISCO);
        }

        try {
            $compra = $this->registrarCompra->execute($datos['lineas'], $datos, $request->user()->id);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.compras-insumo.show', $compra->id())
            ->with('success', "Compra #{$compra->id()} registrada. El stock de los insumos ya fue actualizado.");
    }

    public function show(int $compra): View
    {
        Gate::authorize('viewAny', InsumoModel::class);

        $detalle = $this->obtenerDetalle->execute($compra) ?? abort(404);

        return view('admin.insumos.compra', [
            'detalle' => $detalle,
            'imagenUrl' => $detalle['compra']->imagenBoleta() !== null ? Storage::disk(self::DISCO)->url($detalle['compra']->imagenBoleta()) : null,
        ]);
    }
}
