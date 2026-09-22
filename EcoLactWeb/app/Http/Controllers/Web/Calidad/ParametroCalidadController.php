<?php

namespace App\Http\Controllers\Web\Calidad;

use App\Application\Calidad\ActualizarParametroCalidad;
use App\Application\Calidad\ObtenerParametroCalidad;
use App\Application\Calidad\ObtenerParametrosCalidad;
use App\Application\Calidad\RegistrarParametroCalidad;
use App\Application\Shared\CampoActualizable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calidad\StoreParametroCalidadRequest;
use App\Http\Requests\Calidad\UpdateParametroCalidadRequest;
use App\Infrastructure\Persistence\Models\ParametroCalidadModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Configuración de QUÉ se mide (código, nombre, unidad). Los límites de
 * evaluación viven en ReglaCalidadController, nunca aquí.
 */
final class ParametroCalidadController extends Controller
{
    public function __construct(
        private readonly RegistrarParametroCalidad $registrarParametroCalidad,
        private readonly ActualizarParametroCalidad $actualizarParametroCalidad,
        private readonly ObtenerParametroCalidad $obtenerParametroCalidad,
        private readonly ObtenerParametrosCalidad $obtenerParametrosCalidad,
    ) {}

    /**
     * Vista futura (Etapa 10.3): admin.parametros-calidad.index
     */
    public function index(): View
    {
        Gate::authorize('viewAny', ParametroCalidadModel::class);

        return view('admin.parametros-calidad.index', [
            'parametros' => $this->obtenerParametrosCalidad->execute(),
        ]);
    }

    /**
     * Vista futura (Etapa 10.3): admin.parametros-calidad.show
     */
    public function show(ParametroCalidadModel $parametro): View
    {
        Gate::authorize('view', $parametro);

        return view('admin.parametros-calidad.show', [
            'parametro' => $this->obtenerParametroCalidad->execute($parametro->id),
        ]);
    }

    public function store(StoreParametroCalidadRequest $request): RedirectResponse
    {
        Gate::authorize('create', ParametroCalidadModel::class);

        $datos = $request->validated();

        $this->registrarParametroCalidad->execute(
            codigo: $datos['codigo'],
            nombre: $datos['nombre'],
            unidad: $datos['unidad'] ?? null,
            estado: $datos['estado'],
        );

        return redirect()->back()->with('success', 'Parámetro de calidad registrado correctamente.');
    }

    /**
     * NO admite "codigo": ActualizarParametroCalidad no lo acepta (es el
     * identificador técnico estable). unidad es nullable en el esquema, por
     * lo que "no enviada" vs "enviada como null" se distingue con
     * CampoActualizable (ver Etapa 07.2 / sección 14).
     */
    public function update(UpdateParametroCalidadRequest $request, ParametroCalidadModel $parametro): RedirectResponse
    {
        Gate::authorize('update', $parametro);

        $datos = $request->validated();

        $this->actualizarParametroCalidad->execute(
            parametroId: $parametro->id,
            nombre: $datos['nombre'] ?? null,
            unidad: $request->has('unidad') ? CampoActualizable::conValor($datos['unidad'] ?? null) : null,
            estado: $datos['estado'] ?? null,
        );

        return redirect()->back()->with('success', 'Parámetro de calidad actualizado correctamente.');
    }
}
