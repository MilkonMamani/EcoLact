<?php

namespace App\Http\Controllers\Web\Comunicacion;

use App\Application\Comunicacion\ActualizarComunicado;
use App\Application\Comunicacion\ComunicadosFiltro;
use App\Application\Comunicacion\Contracts\ComunicadosListadoQuery;
use App\Application\Comunicacion\CrearComunicado;
use App\Application\Comunicacion\ObtenerDetalleComunicado;
use App\Application\Comunicacion\PublicarComunicado;
use App\Application\Proveedor\ObtenerProveedores;
use App\Application\Zona\ObtenerZonas;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comunicacion\StoreComunicadoRequest;
use App\Http\Requests\Comunicacion\UpdateComunicadoRequest;
use App\Infrastructure\Persistence\Models\ComunicadoModel;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ComunicadoController extends Controller
{
    public function __construct(
        private readonly CrearComunicado $crearComunicado,
        private readonly ActualizarComunicado $actualizarComunicado,
        private readonly PublicarComunicado $publicarComunicado,
        private readonly ComunicadosListadoQuery $comunicadosListadoQuery,
        private readonly ObtenerDetalleComunicado $obtenerDetalleComunicado,
        private readonly ObtenerProveedores $obtenerProveedores,
        private readonly ObtenerZonas $obtenerZonas,
    ) {}

    /**
     * ADMINISTRADOR ve todos (Gate::before); esta es la única ruta HTTP que
     * llega a este método (role:ADMINISTRADOR en routes/web.php). El
     * recorte por userId de ObtenerComunicados existe para el otro
     * consumidor de ese Use Case: Web\Proveedor\ComunicadoController.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ComunicadoModel::class);

        $filtro = ComunicadosFiltro::resolver(
            busqueda: $request->query('q'),
            tipo: $request->query('tipo'),
            estado: $request->query('estado'),
            periodo: $request->query('periodo'),
            desde: $request->query('desde'),
            hasta: $request->query('hasta'),
        );

        return view('admin.comunicados.index', [
            'datos' => $this->comunicadosListadoQuery->obtener($filtro),
            'proveedores' => $this->obtenerProveedores->execute(),
            // Se mapea a array asociativo aquí (y no en la vista con
            // collect()->pluck()) porque Zona expone sus datos mediante
            // getters, no propiedades públicas: pluck()/data_get() no
            // puede leerlos y devolvería una lista vacía en silencio (el
            // mismo problema ya detectado en el módulo Stock).
            'zonas' => collect($this->obtenerZonas->execute())
                ->mapWithKeys(fn ($zona): array => [$zona->id() => $zona->nombre()])
                ->all(),
        ]);
    }

    public function show(ComunicadoModel $comunicado): View
    {
        Gate::authorize('view', $comunicado);

        return view('admin.comunicados.show', [
            'detalle' => $this->obtenerDetalleComunicado->execute($comunicado->id),
        ]);
    }

    public function store(StoreComunicadoRequest $request): RedirectResponse
    {
        Gate::authorize('create', ComunicadoModel::class);

        $datos = $request->validated();

        $this->crearComunicado->execute(
            titulo: $datos['titulo'],
            contenido: $datos['contenido'],
            tipo: $datos['tipo'],
            alcance: $datos['alcance'],
            // El actor (quien crea el comunicado) es siempre el usuario
            // autenticado, nunca un valor enviado por el cliente.
            creadoPorUserId: $request->user()->id,
            destinatarioUserIds: $datos['destinatarios'] ?? [],
            zonaId: isset($datos['zona_id']) ? (int) $datos['zona_id'] : null,
            fechaEvento: isset($datos['fecha_evento']) ? new DateTimeImmutable($datos['fecha_evento']) : null,
            estado: $datos['estado'] ?? 'PUBLICADO',
            fechaPublicacion: isset($datos['fecha_publicacion']) ? new DateTimeImmutable($datos['fecha_publicacion']) : null,
        );

        return redirect()->back()->with('success', 'Comunicado creado correctamente.');
    }

    public function update(UpdateComunicadoRequest $request, ComunicadoModel $comunicado): RedirectResponse
    {
        Gate::authorize('update', $comunicado);

        $datos = $request->validated();

        $this->actualizarComunicado->execute(
            comunicadoId: $comunicado->id,
            titulo: $datos['titulo'] ?? null,
            contenido: $datos['contenido'] ?? null,
            tipo: $datos['tipo'] ?? null,
            fechaEvento: isset($datos['fecha_evento']) ? new DateTimeImmutable($datos['fecha_evento']) : null,
        );

        return redirect()->back()->with('success', 'Comunicado actualizado correctamente.');
    }

    public function publicar(ComunicadoModel $comunicado): RedirectResponse
    {
        Gate::authorize('update', $comunicado);

        $this->publicarComunicado->execute($comunicado->id);

        return redirect()->back()->with('success', 'Comunicado publicado correctamente.');
    }
}
