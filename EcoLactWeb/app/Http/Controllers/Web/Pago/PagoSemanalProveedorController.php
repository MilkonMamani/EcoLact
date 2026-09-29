<?php

namespace App\Http\Controllers\Web\Pago;

use App\Application\Pago\ComprobantePagoProveedor;
use App\Application\Pago\ImprimirYMarcarPagadosProveedores;
use App\Application\Pago\ObtenerComprobantesPagoProveedor;
use App\Application\Pago\ObtenerPagoSemanalProveedores;
use App\Application\Pago\PagoSemanalFiltro;
use App\Application\Pago\SincronizarPagosProveedor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pago\ImprimirYMarcarPagadosRequest;
use App\Http\Requests\Pago\PrevisualizarComprobantesRequest;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Pagar a proveedores: pantalla "Pago semanal a proveedores". El administrador
 * elige a quién pagar, revisa un comprobante por proveedor y, al confirmar la
 * impresión, los pagos pasan de PENDIENTE a PAGADO en efectivo. Aquí NO se crean
 * pagos: nacen de Configuración de pagos.
 */
final class PagoSemanalProveedorController extends Controller
{
    public function __construct(
        private readonly SincronizarPagosProveedor $sincronizarPagosProveedor,
        private readonly ObtenerPagoSemanalProveedores $obtenerPagoSemanal,
        private readonly ObtenerComprobantesPagoProveedor $obtenerComprobantes,
        private readonly ImprimirYMarcarPagadosProveedores $imprimirYMarcarPagados,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ObligacionPagoModel::class);

        // Red de seguridad (igual que Pagos a trabajadores): toda entrega válida de Acopio que haya
        // quedado sin pago (p. ej. registrada antes de configurar el precio o el pago por litros) se
        // asigna ahora a su pago pendiente. Idempotente: no duplica ni toca pagos ya realizados.
        $this->sincronizarPagosProveedor->execute(actorUserId: $request->user()?->id);

        $filtro = PagoSemanalFiltro::resolver(
            semana: $request->string('semana')->toString() ?: null,
            zona: $request->string('zona')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
        );

        return view('admin.pagos.proveedores.index', [
            'datos' => $this->obtenerPagoSemanal->execute($filtro),
            'filtro' => $filtro,
            'buscar' => Str::limit($request->string('buscar')->trim()->toString(), 100, ''),
            // Tras pagar, la hoja lateral sigue mostrando al proveedor recién pagado.
            'verId' => session('ver_pago_id'),
        ]);
    }

    /**
     * Hojas (HTML) de los proveedores seleccionados: alimentan la vista previa de
     * la derecha y el modal "Vista previa de impresión".
     */
    public function preview(PrevisualizarComprobantesRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', ObligacionPagoModel::class);

        $hojas = array_map(fn (ComprobantePagoProveedor $comprobante): array => [
            'id' => $comprobante->obligacionId,
            'proveedor' => $comprobante->proveedorNombre,
            'codigo' => $comprobante->codigo,
            'total' => $comprobante->totalFinal,
            'html' => view('admin.pagos.proveedores._comprobante', ['c' => $comprobante])->render(),
        ], $this->obtenerComprobantes->execute($request->validated('ids')));

        return response()->json(['hojas' => $hojas]);
    }

    /**
     * Se llama SOLO cuando el administrador confirma en el modal que imprimió los
     * comprobantes; abrir la vista previa no marca nada como pagado.
     */
    public function imprimirYMarcarPagados(ImprimirYMarcarPagadosRequest $request): RedirectResponse
    {
        Gate::authorize('pay', ObligacionPagoModel::class);

        $montos = collect($request->validated('montos') ?? [])
            ->mapWithKeys(fn (mixed $monto, mixed $id): array => [(int) $id => (float) $monto])
            ->all();

        // Se conservan los filtros (incluido "todos") y la búsqueda al volver a la pantalla.
        $filtros = array_filter(
            $request->only(['semana', 'zona', 'estado', 'buscar']),
            fn (mixed $valor): bool => $valor !== null && $valor !== '',
        );

        try {
            $resultado = $this->imprimirYMarcarPagados->execute(
                obligacionIds: $request->validated('ids'),
                montosVistos: $montos,
                registradoPorUserId: $request->user()->id,
            );
        } catch (DomainException $exception) {
            return redirect()->route('admin.pagos.proveedores.index', $filtros)
                ->with('error', $exception->getMessage());
        }

        $proveedores = $resultado->pagados === 1 ? '1 proveedor' : $resultado->pagados.' proveedores';
        $ids = array_map('intval', $request->validated('ids'));
        $ver = (int) $request->validated('ver');

        return redirect()->route('admin.pagos.proveedores.index', $filtros)
            ->with('ver_pago_id', in_array($ver, $ids, true) ? $ver : end($ids))
            ->with('success', "Se marcaron como pagados en efectivo {$proveedores} por S/ ".number_format($resultado->total, 2)." (lote {$resultado->loteCodigo}).");
    }

    /** Comprobante individual imprimible de un pago (pendiente o ya pagado). */
    public function receipt(ObligacionPagoModel $obligacion): View
    {
        Gate::authorize('view', $obligacion);

        $comprobante = $this->obtenerComprobantes->execute([$obligacion->id])[0] ?? abort(404);

        return view('admin.pagos.proveedores.comprobante', ['c' => $comprobante]);
    }
}
