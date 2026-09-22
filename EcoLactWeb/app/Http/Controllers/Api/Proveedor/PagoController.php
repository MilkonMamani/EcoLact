<?php

namespace App\Http\Controllers\Api\Proveedor;

use App\Application\Liquidacion\ObtenerPago;
use App\Application\Liquidacion\ObtenerPagos;
use App\Domain\Pago\PagoProveedor;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\PagoProveedorModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * El pago al proveedor siempre es en efectivo (sobre sellado, sin método de
 * pago variable): PagoProveedor no expone campo forma_pago.
 */
final class PagoController extends Controller
{
    public function __construct(
        private readonly ObtenerPagos $obtenerPagos,
        private readonly ObtenerPago $obtenerPago,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PagoProveedorModel::class);

        $proveedorId = $request->user()->proveedor?->id;
        $pagos = $proveedorId !== null ? $this->obtenerPagos->execute($proveedorId) : [];

        return response()->json(['data' => array_map($this->serializar(...), $pagos)]);
    }

    public function show(PagoProveedorModel $pago): JsonResponse
    {
        Gate::authorize('view', $pago);

        return response()->json(['data' => $this->serializar($this->obtenerPago->execute($pago->id))]);
    }

    /** @return array<string, mixed>|null */
    private function serializar(?PagoProveedor $pago): ?array
    {
        if ($pago === null) {
            return null;
        }

        return [
            'id' => $pago->id(),
            'liquidacion_id' => $pago->liquidacionId(),
            'fecha_pago' => $pago->fechaPago()->format(DATE_ATOM),
            'monto' => $pago->monto(),
            'estado' => $pago->estado()->value,
            'observacion' => $pago->observacion(),
        ];
    }
}
