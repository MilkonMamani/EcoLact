<?php

namespace App\Http\Controllers\Web\Analista;

use App\Application\Calidad\Contracts\MuestreosCalidadQuery;
use App\Application\Calidad\ElegirMuestraAleatoria;
use App\Application\Calidad\RangoMuestreo;
use App\Application\Calidad\SeleccionarMuestraCalidad;
use App\Domain\Shared\Enums\MetodoSeleccionMuestreo;
use App\Domain\Shared\Enums\TipoControlMuestreo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calidad\SeleccionarMuestraRequest;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "Seleccionar muestra" del portal CALIDAD: mismos Use Cases que la API del
 * móvil (ElegirMuestraAleatoria / SeleccionarMuestraCalidad), así Web y
 * móvil aplican exactamente la misma regla de muestreo.
 * - ?modo=aleatoria → el SERVIDOR sortea y se muestra la propuesta;
 * - ?modo=manual    → lista de entregas elegibles con filtros;
 * - POST            → confirma la muestra y la deja en "Muestras pendientes".
 */
final class MuestreoController extends Controller
{
    public function __construct(
        private readonly MuestreosCalidadQuery $muestreos,
        private readonly ElegirMuestraAleatoria $elegirMuestraAleatoria,
        private readonly SeleccionarMuestraCalidad $seleccionarMuestra,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('create', AnalisisCalidadModel::class);

        $modo = in_array($request->query('modo'), ['aleatoria', 'manual'], true) ? $request->query('modo') : null;
        $fecha = in_array($request->query('fecha'), ['ayer', 'semana'], true) ? $request->query('fecha') : 'hoy';
        $zonaId = $request->filled('zona_id') ? $request->integer('zona_id') : null;
        $rango = RangoMuestreo::resolver($fecha);

        $propuesta = null;
        $vistas = [];
        if ($modo === 'aleatoria') {
            $vistas = array_values(array_filter(array_map('intval', (array) $request->query('excluir', []))));
            $propuesta = $this->elegirMuestraAleatoria->execute($rango, $request->user()->id, $zonaId, $vistas);
        }

        return view('calidad.muestreo.index', [
            'modo' => $modo,
            'fecha' => $fecha,
            'propuesta' => $propuesta,
            'vistas' => $vistas,
            'zonas' => $this->muestreos->zonas(),
            'elegibles' => $modo === 'manual'
                ? $this->muestreos->elegibles(
                    busqueda: $request->string('q')->toString() ?: null,
                    zonaId: $zonaId,
                    desde: $rango->desde->toDateTimeImmutable(),
                    hasta: $rango->hasta->toDateTimeImmutable(),
                )
                : [],
        ]);
    }

    public function store(SeleccionarMuestraRequest $request): RedirectResponse
    {
        Gate::authorize('create', AnalisisCalidadModel::class);

        $datos = $request->validated();

        try {
            $this->seleccionarMuestra->execute(
                entregaId: (int) $datos['entrega_id'],
                analistaUserId: $request->user()->id,
                metodo: MetodoSeleccionMuestreo::from($datos['metodo_seleccion']),
                tipoControl: isset($datos['tipo_control']) ? TipoControlMuestreo::from($datos['tipo_control']) : null,
                observacion: $datos['observacion'] ?? null,
                propuestaToken: $datos['propuesta_token'] ?? null,
            );
        } catch (DomainException $excepcion) {
            // Incluye MuestraYaSeleccionadaException: "Esta entrega ya fue seleccionada para análisis."
            return redirect()->back()->with('error', $excepcion->getMessage());
        }

        return redirect()->route('calidad.analisis-calidad.index')->with('success', 'Muestra seleccionada. Ya aparece en Muestras pendientes.');
    }
}
