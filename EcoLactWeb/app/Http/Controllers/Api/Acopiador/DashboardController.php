<?php

namespace App\Http\Controllers\Api\Acopiador;

use App\Application\Acopiador\DashboardAcopiadorData;
use App\Application\Acopiador\ObtenerDashboardAcopiador;
use App\Domain\Comunicacion\Comunicado;
use App\Domain\Entrega\Entrega;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ObtenerDashboardAcopiador $obtenerDashboardAcopiador,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $zonaId = $request->user()->acopiador?->zona_id
            ?? throw new DomainException('No existe un acopiador asociado al usuario autenticado.');

        $dashboard = $this->obtenerDashboardAcopiador->execute(
            userId: $request->user()->id,
            zonaId: $zonaId,
            periodo: $request->query('periodo'),
            desde: $request->query('desde'),
            hasta: $request->query('hasta'),
        );

        return response()->json(['data' => $this->serializar($dashboard)]);
    }

    /** @return array<string, mixed> */
    private function serializar(DashboardAcopiadorData $dashboard): array
    {
        return [
            'kpis' => $dashboard->kpis,
            'ultimas_entregas' => array_map($this->serializarEntrega(...), $dashboard->ultimasEntregas),
            'resultados_por_entrega' => $dashboard->resultadosPorEntrega,
            'proveedores_atendidos' => $dashboard->proveedoresAtendidos,
            'comunicados_recientes' => array_map($this->serializarComunicado(...), $dashboard->comunicadosRecientes),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarEntrega(Entrega $entrega): array
    {
        return [
            'id' => $entrega->id(),
            'proveedor_id' => $entrega->proveedorId(),
            'acopiador_id' => $entrega->acopiadorId(),
            'zona_id' => $entrega->zonaId(),
            'registrado_por_user_id' => $entrega->registradoPorUserId(),
            'fecha_hora' => $entrega->fechaHora()->format(DATE_ATOM),
            'litros' => $entrega->litros(),
            'metodo_identificacion' => $entrega->metodoIdentificacion(),
            'estado' => $entrega->estado()->value,
            'observacion' => $entrega->observacion(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarComunicado(Comunicado $comunicado): array
    {
        return [
            'id' => $comunicado->id(),
            'titulo' => $comunicado->titulo(),
            'tipo' => $comunicado->tipo(),
            'alcance' => $comunicado->alcance(),
            'fecha_publicacion' => $comunicado->fechaPublicacion()->format(DATE_ATOM),
            'fecha_evento' => $comunicado->fechaEvento()?->format(DATE_ATOM),
            'estado' => $comunicado->estado(),
        ];
    }
}
