<?php

namespace App\Http\Controllers\Api\Calidad;

use App\Application\Calidad\Contracts\MuestreosCalidadQuery;
use App\Application\Calidad\ElegirMuestraAleatoria;
use App\Application\Calidad\RangoMuestreo;
use App\Application\Calidad\SeleccionarMuestraCalidad;
use App\Domain\AnalistaCalidad\AnalistaCalidadRepository;
use App\Domain\Calidad\MuestraYaSeleccionadaException;
use App\Domain\Shared\Enums\MetodoSeleccionMuestreo;
use App\Domain\Shared\Enums\TipoControlMuestreo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calidad\SeleccionarMuestraRequest;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Muestreo de calidad del móvil del analista (API delgada sobre
 * Application/Calidad):
 * - GET  muestreos            → "Muestras pendientes" + KPIs de Inicio;
 * - GET  muestreos/elegibles  → entregas que se pueden seleccionar a mano;
 * - POST muestreos/aleatorio  → el SERVIDOR sortea una entrega (no guarda);
 * - POST muestreos            → confirma la muestra (MANUAL o ALEATORIA);
 * - GET  muestreos/{id}       → detalle de una muestra.
 *
 * Un DomainException de negocio se responde 422 (nunca 500, así la cola
 * offline del móvil no lo reintenta) y una entrega ya elegida por otro
 * analista, 409.
 */
final class MuestreoController extends Controller
{
    public function __construct(
        private readonly MuestreosCalidadQuery $muestreos,
        private readonly ElegirMuestraAleatoria $elegirMuestraAleatoria,
        private readonly SeleccionarMuestraCalidad $seleccionarMuestra,
        private readonly AnalistaCalidadRepository $analistas,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AnalisisCalidadModel::class);
        // ?q= y ?zona_id= opcionales, mismos filtros que "Muestras pendientes" en la Web.

        // Para el saludo y el menú del móvil ("Hola, María" / "María Tito").
        $analista = $this->analistas->buscarPorUserId($request->user()->id);

        return response()->json(['data' => [
            'analista' => $analista === null ? null : ['nombres' => $analista->nombres(), 'apellidos' => $analista->apellidos()],
            'kpis' => $this->muestreos->kpis(new DateTimeImmutable),
            'filas' => $this->muestreos->pendientes(
                busqueda: $request->string('q')->toString() ?: null,
                zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
            ),
        ]]);
    }

    public function elegibles(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AnalisisCalidadModel::class);

        $rango = RangoMuestreo::resolver($request->string('fecha')->toString() ?: null);

        return response()->json(['data' => [
            'filas' => $this->muestreos->elegibles(
                busqueda: $request->string('q')->toString() ?: null,
                zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
                desde: $rango->desde->toDateTimeImmutable(),
                hasta: $rango->hasta->toDateTimeImmutable(),
            ),
        ]]);
    }

    public function aleatorio(Request $request): JsonResponse
    {
        Gate::authorize('create', AnalisisCalidadModel::class);

        $propuesta = $this->elegirMuestraAleatoria->execute(
            rango: RangoMuestreo::resolver($request->string('fecha')->toString() ?: null),
            analistaUserId: $request->user()->id,
            zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
            excluir: array_values(array_filter(array_map('intval', (array) $request->input('excluir', [])))),
        );

        if ($propuesta === null) {
            return response()->json(['message' => 'No hay entregas disponibles para muestrear en este momento.'], 422);
        }

        return response()->json(['data' => $propuesta]);
    }

    public function store(SeleccionarMuestraRequest $request): JsonResponse
    {
        Gate::authorize('create', AnalisisCalidadModel::class);

        $datos = $request->validated();

        try {
            $muestreo = $this->seleccionarMuestra->execute(
                entregaId: (int) $datos['entrega_id'],
                analistaUserId: $request->user()->id,
                metodo: MetodoSeleccionMuestreo::from($datos['metodo_seleccion']),
                tipoControl: isset($datos['tipo_control']) ? TipoControlMuestreo::from($datos['tipo_control']) : null,
                fechaSeleccion: isset($datos['fecha_seleccion']) ? new DateTimeImmutable($datos['fecha_seleccion']) : null,
                observacion: $datos['observacion'] ?? null,
                propuestaToken: $datos['propuesta_token'] ?? null,
            );
        } catch (MuestraYaSeleccionadaException $excepcion) {
            return response()->json(['message' => $excepcion->getMessage()], 409);
        } catch (DomainException $excepcion) {
            return response()->json(['message' => $excepcion->getMessage()], 422);
        }

        return response()->json(['data' => $this->muestreos->muestra((int) $muestreo->id())], 201);
    }

    public function show(int $muestreo): JsonResponse
    {
        Gate::authorize('viewAny', AnalisisCalidadModel::class);

        $fila = $this->muestreos->muestra($muestreo);

        if ($fila === null) {
            return response()->json(['message' => 'La muestra indicada no existe.'], 404);
        }

        return response()->json(['data' => $fila]);
    }
}
