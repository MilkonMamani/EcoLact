<?php

namespace App\Http\Controllers\Web;

use App\Application\Acopio\ActualizarEntrega;
use App\Application\Acopio\AnularEntrega;
use App\Application\Acopio\Contracts\EntregasListadoQuery;
use App\Application\Acopio\CorregirLitrosEntrega;
use App\Application\Acopio\EntregasFiltro;
use App\Application\Acopio\ObtenerEntrega;
use App\Application\Acopio\ObtenerEntregas;
use App\Application\Acopio\ObtenerEntregasProveedor;
use App\Application\Acopio\RegistrarEntrega;
use App\Http\Controllers\Controller;
use App\Http\Requests\Entrega\AnularEntregaRequest;
use App\Http\Requests\Entrega\CorregirLitrosEntregaRequest;
use App\Http\Requests\Entrega\StoreEntregaRequest;
use App\Http\Requests\Entrega\UpdateEntregaRequest;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class EntregaController extends Controller
{
    /** Filas por página en la pantalla principal de Acopio (paginación de servidor). */
    private const ENTREGAS_POR_PAGINA = 100;

    public function __construct(
        private readonly RegistrarEntrega $registrarEntrega,
        private readonly ActualizarEntrega $actualizarEntrega,
        private readonly AnularEntrega $anularEntrega,
        private readonly ObtenerEntregas $obtenerEntregas,
        private readonly ObtenerEntregasProveedor $obtenerEntregasProveedor,
        private readonly ObtenerEntrega $obtenerEntrega,
        private readonly EntregasListadoQuery $entregasListadoQuery,
        private readonly CorregirLitrosEntrega $corregirLitrosEntrega,
    ) {}

    /**
     * Vista futura (Etapa 10.3): admin.entregas.index
     *
     * EntregaPolicy::viewAny autoriza a ACOPIADOR y PROVEEDOR por igual
     * (ADMINISTRADOR entra vía Gate::before), pero cada rol ve un recorte
     * distinto: el ACOPIADOR siempre su propia zona, el PROVEEDOR siempre
     * sus propias entregas (nunca un ?zona_id= arbitrario, que expondría
     * entregas de otros proveedores en esa zona) y el ADMINISTRADOR todas,
     * salvo que filtre explícitamente por zona.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EntregaModel::class);

        $user = $request->user();

        if ($user->esProveedor()) {
            $proveedorId = $user->proveedor?->id;
            $entregas = $proveedorId !== null ? $this->obtenerEntregasProveedor->execute($proveedorId) : [];

            return view('admin.entregas.index', ['entregas' => $entregas]);
        }

        if ($user->esAcopiador()) {
            $zonaId = $user->acopiador?->zona_id;
            $entregas = $zonaId !== null ? $this->obtenerEntregas->execute($zonaId) : [];

            return view('admin.entregas.index', ['entregas' => $entregas]);
        }

        $filtro = EntregasFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
            acopiadorId: $request->filled('acopiador_id') ? $request->integer('acopiador_id') : null,
            estado: $request->string('estado')->toString() ?: null,
            fecha: $request->string('fecha')->toString() ?: null,
            desde: $request->string('desde')->toString() ?: null,
            hasta: $request->string('hasta')->toString() ?: null,
        );

        return view('admin.entregas.index', ['datos' => $this->entregasListadoQuery->paginar($filtro, self::ENTREGAS_POR_PAGINA)]);
    }

    public function show(EntregaModel $entrega): View
    {
        Gate::authorize('view', $entrega);

        $entrega->load(['proveedor', 'zona', 'acopiador', 'registradoPor']);

        return view('admin.entregas.show', [
            'entrega' => $this->obtenerEntrega->execute($entrega->id),
            'registro' => $entrega,
        ]);
    }

    /**
     * Fragmento HTML (sin layout) del modal "Ver entrega" de la pantalla de
     * Acopio; se carga por fetch al pulsar el ícono de la fila.
     */
    public function detalle(EntregaModel $entrega): View
    {
        Gate::authorize('view', $entrega);

        $entrega->load(['proveedor', 'zona', 'acopiador', 'registradoPor', 'analisisCalidad']);

        return view('admin.entregas._detalle', [
            'entrega' => $this->obtenerEntrega->execute($entrega->id),
            'registro' => $entrega,
        ]);
    }

    public function buscarProveedorPorDni(Request $request): JsonResponse
    {
        Gate::authorize('create', EntregaModel::class);

        $dni = preg_replace('/\D/', '', $request->string('q')->toString());
        $consulta = ProveedorModel::query()
            ->where('estado', 'ACTIVO')
            ->where('dni', 'like', $dni.'%')
            ->with('zona')
            ->orderBy('dni')
            ->limit(50);

        if ($request->user()->esAcopiador()) {
            $consulta->where('zona_id', $request->user()->acopiador?->zona_id);
        }

        return response()->json($consulta->get()->map(fn (ProveedorModel $proveedor): array => [
            'dni' => $proveedor->dni,
            'nombre' => trim($proveedor->nombres.' '.$proveedor->apellidos),
            'zona' => $proveedor->zona?->nombre,
        ])->values());
    }

    public function update(UpdateEntregaRequest $request, EntregaModel $entrega): RedirectResponse
    {
        Gate::authorize('update', $entrega);

        $datos = $request->validated();

        try {
            $this->actualizarEntrega->execute(
                entregaId: $entrega->id,
                motivoModificacion: $datos['motivo_modificacion'],
                proveedorId: isset($datos['proveedor_id']) ? (int) $datos['proveedor_id'] : null,
                zonaId: isset($datos['zona_id']) ? (int) $datos['zona_id'] : null,
                acopiadorId: isset($datos['acopiador_id']) ? (int) $datos['acopiador_id'] : null,
                fechaHora: isset($datos['fecha_hora']) ? new DateTimeImmutable($datos['fecha_hora']) : null,
                litros: isset($datos['litros']) ? (float) $datos['litros'] : null,
                metodoIdentificacion: $datos['metodo_identificacion'] ?? null,
                observacion: $datos['observacion'] ?? null,
                actorUserId: $request->user()->id,
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.entregas.show', $entrega->id)->with('success', 'Entrega actualizada correctamente.');
    }

    /**
     * Edición inline de litros del modal "Ver entrega" (fetch/JSON). Es el
     * único campo que acepta; el cambio queda registrado en `auditorias`.
     */
    public function actualizarLitros(CorregirLitrosEntregaRequest $request, EntregaModel $entrega): JsonResponse
    {
        Gate::authorize('update', $entrega);

        try {
            $actualizada = $this->corregirLitrosEntrega->execute(
                entregaId: $entrega->id,
                litros: (float) $request->validated()['litros'],
                adminUserId: $request->user()->id,
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['litros' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'message' => 'Litros actualizados correctamente.',
            'litros' => $actualizada->litros(),
            'litros_formateados' => number_format($actualizada->litros(), 2).' L',
        ]);
    }

    /**
     * Anula una entrega registrada por error. Si ya había ingresado leche al
     * inventario, se registra un REVERSO (el ingreso original se conserva).
     */
    public function anular(AnularEntregaRequest $request, EntregaModel $entrega): RedirectResponse
    {
        Gate::authorize('update', $entrega);

        try {
            $this->anularEntrega->execute($entrega->id, $request->validated()['motivo'], $request->user()->id);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.entregas.show', $entrega->id)->with('success', 'Entrega anulada. Su leche fue descontada del inventario.');
    }

    /**
     * Vista futura (Etapa 10.3): admin.entregas.por-proveedor
     */
    public function porProveedor(ProveedorModel $proveedor): View
    {
        // Reutiliza ProveedorPolicy::view: mismo criterio que "puedo ver a
        // este proveedor" ya cubre acopiador-misma-zona / proveedor-propio.
        Gate::authorize('view', $proveedor);

        return view('admin.entregas.por-proveedor', [
            'entregas' => $this->obtenerEntregasProveedor->execute($proveedor->id),
        ]);
    }

    public function store(StoreEntregaRequest $request): RedirectResponse
    {
        Gate::authorize('create', EntregaModel::class);

        $datos = $request->validated();

        try {
            $this->registrarEntrega->execute(
                metodoIdentificacion: $datos['metodo_identificacion'],
                identificador: $datos['identificador'],
                // El actor (acopiador que registra) es siempre el usuario
                // autenticado; nunca se toma de un campo del formulario, que un
                // cliente malicioso podría manipular para actuar "a nombre de"
                // otro acopiador.
                acopiadorUserId: $request->user()->id,
                litros: (float) $datos['litros'],
                fechaHora: isset($datos['fecha_hora']) ? new DateTimeImmutable($datos['fecha_hora']) : null,
                observacion: $datos['observacion'] ?? null,
                esAdministrador: $request->user()->esAdministrador(),
            );
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Entrega registrada correctamente.');
    }
}
