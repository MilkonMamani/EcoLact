<?php

namespace App\Http\Controllers\Web;

use App\Application\Pago\ObtenerComprobantesPagoProveedor;
use App\Application\Pago\ObtenerHistorialPagosProveedor;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Datos del modal "Ver pagos" del detalle de un proveedor. NO son pantallas: son los
 * recursos que alimentan el modal (JSON con HTML ya renderizado) sin salir de
 * /admin/proveedores/{id}. Si alguien abre estas rutas directamente en el navegador,
 * se le devuelve al detalle del proveedor.
 *
 * Las cifras salen de los mismos registros y snapshots que "Pagar a proveedores".
 */
final class ProveedorPagosHistorialController extends Controller
{
    public function __construct(
        private readonly ObtenerHistorialPagosProveedor $obtenerHistorial,
        private readonly ObtenerComprobantesPagoProveedor $obtenerComprobantes,
    ) {}

    /** Historial paginado y filtrado por semana y estado, solo del proveedor de la URL. */
    public function index(Request $request, ProveedorModel $proveedor): JsonResponse|RedirectResponse
    {
        $this->autorizar($proveedor);

        if (! $request->expectsJson() && ! $request->ajax()) {
            return redirect()->route('admin.proveedores.show', $proveedor->id);
        }

        $datos = $this->obtenerHistorial->execute(
            proveedorId: $proveedor->id,
            semana: $request->query('semana') !== null ? (string) $request->query('semana') : null,
            estado: $request->query('estado') !== null ? (string) $request->query('estado') : null,
            pagina: max(1, (int) $request->query('page', 1)),
        );

        return response()->json([
            'html' => view('admin.proveedores._pagos-tabla', ['datos' => $datos])->render(),
            'semanas' => array_map(
                fn (array $semana): array => ['valor' => $semana['inicio'], 'etiqueta' => $semana['etiqueta'], 'rango' => $semana['rango']],
                $datos['semanas'],
            ),
            'semana' => $datos['semana'] ?? 'todos',
            'estado' => $datos['estado'],
            'pagina' => $datos['pagina'],
            'total' => $datos['total'],
        ]);
    }

    /** Comprobante (hoja) de un pago del proveedor: el histórico si ya se pagó. */
    public function show(Request $request, ProveedorModel $proveedor, ObligacionPagoModel $obligacion): JsonResponse|RedirectResponse
    {
        $this->autorizar($proveedor);

        // Un pago de otro proveedor nunca se sirve por la URL de este proveedor.
        abort_unless(
            $obligacion->tipo_beneficiario === TipoBeneficiarioPago::PROVEEDOR->value
                && (int) $obligacion->proveedor_id === (int) $proveedor->id,
            404,
        );

        if (! $request->expectsJson() && ! $request->ajax()) {
            return redirect()->route('admin.proveedores.show', $proveedor->id);
        }

        $comprobante = $this->obtenerComprobantes->execute([$obligacion->id])[0] ?? abort(404);

        return response()->json([
            'id' => $comprobante->obligacionId,
            'estado' => $comprobante->estado,
            'semana' => $comprobante->semanaEtiqueta,
            'rango' => $comprobante->rango,
            'html' => view('admin.pagos.proveedores._comprobante', ['c' => $comprobante])->render(),
        ]);
    }

    private function autorizar(ProveedorModel $proveedor): void
    {
        Gate::authorize('view', $proveedor);
        Gate::authorize('viewAny', ObligacionPagoModel::class);
    }
}
