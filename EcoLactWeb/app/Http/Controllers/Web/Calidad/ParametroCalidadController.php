<?php

namespace App\Http\Controllers\Web\Calidad;

use App\Application\Calidad\ActualizarParametroCalidad;
use App\Application\Calidad\Contracts\ParametrosCalidadListadoQuery;
use App\Application\Calidad\RegistrarParametroCalidad;
use App\Application\Shared\CampoActualizable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calidad\StoreParametroCalidadRequest;
use App\Http\Requests\Calidad\UpdateParametroCalidadRequest;
use App\Infrastructure\Persistence\Models\ParametroCalidadModel;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Configuración de QUÉ se mide (código, nombre, unidad, descripción). Los límites de
 * evaluación viven en ReglaCalidadController, nunca aquí.
 *
 * Pantalla única "tabla + panel lateral": ?parametro=ID deja seleccionado un parámetro
 * (recargar o volver tras guardar mantiene la selección) y el panel se pide por fetch.
 */
final class ParametroCalidadController extends Controller
{
    public function __construct(
        private readonly RegistrarParametroCalidad $registrarParametroCalidad,
        private readonly ActualizarParametroCalidad $actualizarParametroCalidad,
        private readonly ParametrosCalidadListadoQuery $parametrosCalidadListadoQuery,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ParametroCalidadModel::class);

        return view('admin.parametros-calidad.index', [
            'parametros' => $this->parametrosCalidadListadoQuery->listar(),
            'panel' => $request->filled('parametro')
                ? $this->parametrosCalidadListadoQuery->panel($request->integer('parametro'))
                : null,
        ]);
    }

    /** Fragmento HTML del panel lateral (lo pide admin-fichas.js al elegir una fila). */
    public function panel(ParametroCalidadModel $parametro): View
    {
        Gate::authorize('view', $parametro);

        return view('admin.parametros-calidad._panel', [
            'panel' => $this->parametrosCalidadListadoQuery->panel($parametro->id),
        ]);
    }

    /** Ya no hay página de detalle aparte: el parámetro se ve en el panel del listado. */
    public function show(ParametroCalidadModel $parametro): RedirectResponse
    {
        Gate::authorize('view', $parametro);

        return redirect()->route('admin.parametros-calidad.index', ['parametro' => $parametro->id]);
    }

    public function store(StoreParametroCalidadRequest $request): RedirectResponse
    {
        Gate::authorize('create', ParametroCalidadModel::class);

        $datos = $request->validated();

        $parametro = $this->registrarParametroCalidad->execute(
            codigo: $datos['codigo'],
            nombre: $datos['nombre'],
            unidad: $datos['unidad'] ?? null,
            estado: $datos['estado'],
            descripcion: $datos['descripcion'] ?? null,
        );

        return redirect()
            ->route('admin.parametros-calidad.index', ['parametro' => $parametro->id()])
            ->with('success', 'Parámetro de calidad registrado correctamente.');
    }

    /**
     * NO admite "codigo": ActualizarParametroCalidad no lo acepta (es el
     * identificador técnico estable). unidad y descripcion son nullable en el
     * esquema, por lo que "no enviada" vs "enviada como null" se distingue con
     * CampoActualizable (ver Etapa 07.2 / sección 14).
     *
     * Si el dominio rechaza el cambio (p. ej. desactivar con reglas activas) se
     * vuelve con el mensaje en lugar de un error 500.
     */
    public function update(UpdateParametroCalidadRequest $request, ParametroCalidadModel $parametro): RedirectResponse
    {
        Gate::authorize('update', $parametro);

        $datos = $request->validated();

        try {
            $this->actualizarParametroCalidad->execute(
                parametroId: $parametro->id,
                nombre: $datos['nombre'] ?? null,
                unidad: $request->has('unidad') ? CampoActualizable::conValor($datos['unidad'] ?? null) : null,
                estado: $datos['estado'] ?? null,
                descripcion: $request->has('descripcion') ? CampoActualizable::conValor($datos['descripcion'] ?? null) : null,
            );
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Parámetro de calidad actualizado correctamente.');
    }
}
