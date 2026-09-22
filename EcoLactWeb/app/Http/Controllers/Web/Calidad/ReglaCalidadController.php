<?php

namespace App\Http\Controllers\Web\Calidad;

use App\Application\Calidad\ActualizarReglaCalidad;
use App\Application\Calidad\ObtenerParametrosCalidad;
use App\Application\Calidad\ObtenerReglasCalidad;
use App\Application\Calidad\ObtenerReglasCalidadPorParametro;
use App\Application\Calidad\RegistrarReglaCalidad;
use App\Application\Shared\CampoActualizable;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calidad\StoreReglaCalidadRequest;
use App\Http\Requests\Calidad\UpdateReglaCalidadRequest;
use App\Infrastructure\Persistence\Models\ParametroCalidadModel;
use App\Infrastructure\Persistence\Models\ReglaCalidadModel;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Configuración de los LÍMITES usados para evaluar un parámetro. NO calcula
 * ni decide resultados de calidad aquí: solo guarda lo que envía el
 * administrador (CalcularResultadoCalidad, en Application, hace la
 * evaluación real cuando corresponde).
 */
final class ReglaCalidadController extends Controller
{
    /**
     * Prioridad por defecto cuando el formulario no la envía: refleja el
     * mismo default ya declarado en la migration de reglas_calidad
     * (unsignedTinyInteger('prioridad')->default(1)), no un valor de
     * negocio inventado aquí.
     */
    private const PRIORIDAD_POR_DEFECTO = 1;

    public function __construct(
        private readonly RegistrarReglaCalidad $registrarReglaCalidad,
        private readonly ActualizarReglaCalidad $actualizarReglaCalidad,
        private readonly ObtenerReglasCalidad $obtenerReglasCalidad,
        private readonly ObtenerReglasCalidadPorParametro $obtenerReglasCalidadPorParametro,
        private readonly ObtenerParametrosCalidad $obtenerParametrosCalidad,
    ) {}

    /**
     * Vista futura (Etapa 10.3): admin.reglas-calidad.index
     */
    public function index(): View
    {
        Gate::authorize('viewAny', ReglaCalidadModel::class);

        return view('admin.reglas-calidad.index', [
            'reglas' => $this->obtenerReglasCalidad->execute(),
            'parametros' => $this->obtenerParametrosCalidad->execute(),
        ]);
    }

    /**
     * Vista futura (Etapa 10.3): admin.reglas-calidad.por-parametro
     */
    public function porParametro(ParametroCalidadModel $parametro): View
    {
        Gate::authorize('view', $parametro);

        return view('admin.reglas-calidad.por-parametro', [
            'reglas' => $this->obtenerReglasCalidadPorParametro->execute($parametro->id),
        ]);
    }

    public function store(StoreReglaCalidadRequest $request): RedirectResponse
    {
        Gate::authorize('create', ReglaCalidadModel::class);

        $datos = $request->validated();

        $this->registrarReglaCalidad->execute(
            parametroId: (int) $datos['parametro_id'],
            valorMinimo: isset($datos['valor_minimo']) ? (float) $datos['valor_minimo'] : null,
            valorMaximo: isset($datos['valor_maximo']) ? (float) $datos['valor_maximo'] : null,
            resultadoIncumplimiento: ResultadoCalidad::from($datos['resultado_incumplimiento']),
            prioridad: isset($datos['prioridad']) ? (int) $datos['prioridad'] : self::PRIORIDAD_POR_DEFECTO,
            fechaInicio: new DateTimeImmutable($datos['fecha_inicio']),
            fechaFin: isset($datos['fecha_fin']) ? new DateTimeImmutable($datos['fecha_fin']) : null,
            estado: $datos['estado'],
        );

        return redirect()->back()->with('success', 'Regla de calidad registrada correctamente.');
    }

    /**
     * valor_minimo, valor_maximo y fecha_fin son nullable en el esquema:
     * "no enviado" (conservar) vs "enviado como null" (limpiar) se
     * distingue con CampoActualizable en los tres, tal como exige
     * ActualizarReglaCalidad (Etapa 07.2 / sección 14).
     */
    public function update(UpdateReglaCalidadRequest $request, ReglaCalidadModel $regla): RedirectResponse
    {
        Gate::authorize('update', $regla);

        $datos = $request->validated();

        $this->actualizarReglaCalidad->execute(
            reglaId: $regla->id,
            valorMinimo: $request->has('valor_minimo')
                ? CampoActualizable::conValor(isset($datos['valor_minimo']) ? (float) $datos['valor_minimo'] : null)
                : null,
            valorMaximo: $request->has('valor_maximo')
                ? CampoActualizable::conValor(isset($datos['valor_maximo']) ? (float) $datos['valor_maximo'] : null)
                : null,
            resultadoIncumplimiento: isset($datos['resultado_incumplimiento'])
                ? ResultadoCalidad::from($datos['resultado_incumplimiento'])
                : null,
            prioridad: isset($datos['prioridad']) ? (int) $datos['prioridad'] : null,
            fechaInicio: isset($datos['fecha_inicio']) ? new DateTimeImmutable($datos['fecha_inicio']) : null,
            fechaFin: $request->has('fecha_fin')
                ? CampoActualizable::conValor(isset($datos['fecha_fin']) ? new DateTimeImmutable($datos['fecha_fin']) : null)
                : null,
            estado: $datos['estado'] ?? null,
        );

        return redirect()->back()->with('success', 'Regla de calidad actualizada correctamente.');
    }
}
