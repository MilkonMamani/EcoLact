<?php

namespace App\Http\Support;

use App\Application\Pago\ObtenerControlPeriodoTrabajador;
use App\Application\Trabajador\Contracts\FichasPersonalQuery;
use App\Application\Trabajador\FichaPersonalPanelData;
use App\Application\Trabajador\FichasPersonalFiltro;
use App\Application\Trabajador\TipoFichaPersonal;
use App\Domain\AnalistaCalidad\AnalistaCalidad;
use App\Domain\EncargadoPlanta\EncargadoPlanta;
use App\Domain\PersonalVentas\PersonalVentas;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use App\Infrastructure\Persistence\Models\EncargadoPlantaModel;
use App\Infrastructure\Persistence\Models\PersonalVentasModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Presentación compartida de las pantallas "listado + panel" de analistas de
 * calidad, encargados de planta y personal de ventas: el mismo patrón que
 * Acopiadores (listado paginado a la izquierda, panel del seleccionado a la
 * derecha pedido por fetch a panel()). Solo cambian los textos y la
 * actividad de cada rol (ver config()); los Use Cases siguen siendo los de cada módulo.
 */
final class PantallaFichasPersonal
{
    private const POR_PAGINA = 12;

    public function __construct(
        private readonly FichasPersonalQuery $fichas,
        private readonly ObtenerControlPeriodoTrabajador $obtenerControlPeriodo,
    ) {}

    public function index(TipoFichaPersonal $tipo, Request $request): View
    {
        $config = $this->config($tipo);
        $filtro = FichasPersonalFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
        );

        $filas = $this->fichas->paginar($tipo, $filtro, self::POR_PAGINA);
        $seleccionadoId = $request->filled($config['param'])
            ? $request->integer($config['param'])
            : ($filas->first()['id'] ?? null);

        return view('admin.fichas-personal.index', [
            'config' => $config,
            'filas' => $filas,
            ...$this->datosPanel($tipo, $seleccionadoId, $request),
        ]);
    }

    public function panel(TipoFichaPersonal $tipo, int $fichaId, Request $request): View
    {
        return view('admin.fichas-personal._panel', [
            'config' => $this->config($tipo),
            ...$this->datosPanel($tipo, $fichaId, $request),
        ]);
    }

    /**
     * Ejecuta el Use Case de desactivar/reactivar y responde en JSON (switch del
     * listado) o con redirección (formularios clásicos). Una regla de negocio
     * incumplida (p. ej. ya estaba activo) es un 422 con su mensaje, nunca un 500.
     *
     * @param  callable(): (AnalistaCalidad|EncargadoPlanta|PersonalVentas)  $cambio
     */
    public function responderCambioDeEstado(Request $request, callable $cambio, string $mensaje): JsonResponse|RedirectResponse
    {
        try {
            $ficha = $cambio();
        } catch (DomainException $error) {
            return $request->expectsJson()
                ? response()->json(['message' => $error->getMessage()], 422)
                : redirect()->back()->with('error', $error->getMessage());
        }

        if (! $request->expectsJson()) {
            return redirect()->back()->with('success', $mensaje);
        }

        return response()->json([
            'message' => $mensaje,
            'ficha' => ['id' => $ficha->id(), 'estado' => $ficha->estado()->value],
        ]);
    }

    /** @return array{panel: ?FichaPersonalPanelData, controlPeriodo: mixed} */
    private function datosPanel(TipoFichaPersonal $tipo, ?int $fichaId, Request $request): array
    {
        $panel = $fichaId !== null
            ? $this->fichas->panel($tipo, $fichaId, $request->string('tipo_cliente')->toString() ?: null)
            : null;

        return [
            'panel' => $panel,
            'controlPeriodo' => $panel !== null
                ? $this->obtenerControlPeriodo->execute($panel->userId, $request->string('mes')->toString() ?: null)
                : null,
        ];
    }

    /**
     * Textos y rutas de cada pantalla; el resto de la interfaz es idéntico.
     *
     * @return array{tipo: TipoFichaPersonal, rutas: string, param: string, modelo: class-string, titulo: string, subtitulo: string, entidad: string, plural: string, nuevo: string, actividad: string, columna: string, sinActividad: string, vacio: string}
     */
    public function config(TipoFichaPersonal $tipo): array
    {
        return ['tipo' => $tipo] + match ($tipo) {
            TipoFichaPersonal::ANALISTA_CALIDAD => [
                'rutas' => 'admin.analistas-calidad',
                'param' => 'analista',
                'modelo' => AnalistaCalidadModel::class,
                'titulo' => 'Analistas de calidad',
                'subtitulo' => 'Personal encargado de evaluar y registrar la calidad de las entregas de leche.',
                'entidad' => 'analista de calidad',
                'plural' => 'analistas de calidad',
                'nuevo' => 'Nuevo analista',
                'actividad' => 'Análisis',
                'columna' => 'Análisis',
                'sinActividad' => 'Este analista todavía no confirmó análisis de calidad.',
                'vacio' => 'Haz click en una fila del listado para ver su resumen, análisis, asistencia y pagos.',
            ],
            TipoFichaPersonal::ENCARGADO_PLANTA => [
                'rutas' => 'admin.encargados-planta',
                'param' => 'encargado',
                'modelo' => EncargadoPlantaModel::class,
                'titulo' => 'Encargados de planta',
                'subtitulo' => 'Personal encargado de gestionar la producción y operaciones de planta.',
                'entidad' => 'encargado de planta',
                'plural' => 'encargados de planta',
                'nuevo' => 'Nuevo encargado',
                'actividad' => 'Producción',
                'columna' => 'Producciones',
                'sinActividad' => 'Este encargado todavía no registró lotes de producción.',
                'vacio' => 'Haz click en una fila del listado para ver su resumen, producción, asistencia y pagos.',
            ],
            TipoFichaPersonal::PERSONAL_VENTAS => [
                'rutas' => 'admin.personal-ventas',
                'param' => 'personal',
                'modelo' => PersonalVentasModel::class,
                'titulo' => 'Personal de ventas',
                'subtitulo' => 'Personal encargado de registrar y gestionar las ventas de productos de la planta.',
                'entidad' => 'vendedor',
                'plural' => 'vendedores',
                'nuevo' => 'Nuevo vendedor',
                'actividad' => 'Ventas',
                'columna' => 'Ventas',
                'sinActividad' => 'Este vendedor todavía no registró ventas.',
                'vacio' => 'Haz click en una fila del listado para ver su resumen, ventas, asistencia y pagos.',
            ],
        };
    }
}
