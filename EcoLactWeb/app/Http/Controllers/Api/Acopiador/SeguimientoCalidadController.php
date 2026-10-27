<?php

namespace App\Http\Controllers\Api\Acopiador;

use App\Application\Acopio\ObtenerEntrega;
use App\Application\Acopio\ObtenerEntregas;
use App\Application\Calidad\ControlCalidadEntrega;
use App\Application\Calidad\ObtenerAnalisisCalidad;
use App\Domain\Entrega\Entrega;
use App\Http\Controllers\Api\Acopiador\Concerns\SerializaEntregasAcopiador;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\MuestreoCalidadModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Seguimiento de calidad (solo lectura) de las entregas de la zona. Con el
 * muestreo, no toda entrega se analiza: cada fila trae `control_calidad`
 * (SIN_MUESTREO, SELECCIONADA, EN_ANALISIS, ACEPTADA, OBSERVADA, RECHAZADA o
 * ANULADA) en vez de dar a entender que todas esperan análisis.
 */
final class SeguimientoCalidadController extends Controller
{
    use SerializaEntregasAcopiador;

    public function __construct(
        private readonly ObtenerEntregas $obtenerEntregas,
        private readonly ObtenerEntrega $obtenerEntrega,
        private readonly ObtenerAnalisisCalidad $obtenerAnalisisCalidad,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AnalisisCalidadModel::class);

        $zonaId = $request->user()->acopiador?->zona_id;
        $entregas = $zonaId !== null ? $this->obtenerEntregas->execute($zonaId) : [];

        $ordenadas = collect($entregas)
            ->filter(fn (Entrega $e): bool => ! $request->filled('proveedor_id') || $e->proveedorId() === $request->integer('proveedor_id'))
            ->sortByDesc(fn (Entrega $e) => $e->fechaHora())
            ->values();

        $filas = collect($this->serializarEntregas($ordenadas->all()))->filter(function (array $fila) use ($request): bool {
            if ($request->filled('estado_analisis') && $fila['estado'] !== $request->string('estado_analisis')->toString()) {
                return false;
            }

            if ($request->filled('control_calidad') && $fila['control_calidad'] !== $request->string('control_calidad')->toString()) {
                return false;
            }

            if ($request->filled('resultado')) {
                $filtro = $request->string('resultado')->toString();

                return $filtro === 'sin_resultado' ? $fila['resultado'] === null : $fila['resultado'] === $filtro;
            }

            return true;
        })->values();

        $contar = fn (array $controles): int => $filas->filter(fn (array $f): bool => in_array($f['control_calidad'], $controles, true))->count();

        return response()->json([
            'data' => [
                'kpis' => [
                    'sin_muestreo' => $contar([ControlCalidadEntrega::SIN_MUESTREO]),
                    'en_control' => $contar([ControlCalidadEntrega::SELECCIONADA, ControlCalidadEntrega::EN_ANALISIS]),
                    'aceptadas' => $contar(['ACEPTADA']),
                    'con_observaciones' => $contar(['OBSERVADA', 'RECHAZADA']),
                    // Compatibilidad con versiones anteriores de la app.
                    'pendientes' => $contar([ControlCalidadEntrega::SELECCIONADA, ControlCalidadEntrega::EN_ANALISIS]),
                    'analizadas' => $contar(['ACEPTADA', 'OBSERVADA', 'RECHAZADA']),
                ],
                'filas' => $filas->all(),
            ],
        ]);
    }

    public function show(EntregaModel $entrega): JsonResponse
    {
        Gate::authorize('view', $entrega);

        $fila = $this->serializarEntrega($this->obtenerEntrega->execute($entrega->id));
        $analisis = $this->obtenerAnalisisCalidad->porEntrega($entrega->id);
        $muestreo = MuestreoCalidadModel::where('entrega_id', $entrega->id)->first();

        return response()->json([
            'data' => [
                'entrega_id' => $entrega->id,
                'estado' => $entrega->estado,
                'control_calidad' => $fila['control_calidad'],
                'entrega' => $fila,
                'muestreo' => $muestreo === null ? null : [
                    'estado' => $muestreo->estado,
                    'fecha_seleccion' => $muestreo->fecha_seleccion?->format(DATE_ATOM),
                ],
                // Solo el resultado confirmado: el acopiador no ve borradores ni parámetros técnicos.
                'analisis' => $analisis === null || $analisis->estado()->value !== 'CONFIRMADO' ? null : [
                    'id' => $analisis->id(),
                    'estado' => $analisis->estado()->value,
                    'resultado' => $analisis->resultado()?->value,
                    'fecha_analisis' => $analisis->fechaAnalisis()->format(DATE_ATOM),
                    'observacion' => $analisis->observacion(),
                ],
            ],
        ]);
    }
}
