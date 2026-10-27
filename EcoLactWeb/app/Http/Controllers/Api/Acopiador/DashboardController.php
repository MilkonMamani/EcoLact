<?php

namespace App\Http\Controllers\Api\Acopiador;

use App\Application\Acopiador\DashboardAcopiadorData;
use App\Application\Acopiador\ObtenerDashboardAcopiador;
use App\Domain\Comunicacion\Comunicado;
use App\Http\Controllers\Api\Acopiador\Concerns\SerializaEntregasAcopiador;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    use SerializaEntregasAcopiador;

    public function __construct(
        private readonly ObtenerDashboardAcopiador $obtenerDashboardAcopiador,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $acopiador = $request->user()->acopiador
            ?? throw new DomainException('No existe un acopiador asociado al usuario autenticado.');

        $dashboard = $this->obtenerDashboardAcopiador->execute(
            userId: $request->user()->id,
            zonaId: $acopiador->zona_id,
            periodo: $request->query('periodo'),
            desde: $request->query('desde'),
            hasta: $request->query('hasta'),
        );

        return response()->json(['data' => [
            ...$this->serializar($dashboard),
            'acopiador' => $this->serializarAcopiador($acopiador),
        ]]);
    }

    /** @return array<string, mixed> */
    private function serializar(DashboardAcopiadorData $dashboard): array
    {
        return [
            'kpis' => $dashboard->kpis,
            'ultimas_entregas' => $this->serializarEntregas($dashboard->ultimasEntregas),
            'resultados_por_entrega' => $dashboard->resultadosPorEntrega,
            'proveedores_atendidos' => $dashboard->proveedoresAtendidos,
            'comunicados_recientes' => array_map($this->serializarComunicado(...), $dashboard->comunicadosRecientes),
        ];
    }

    /**
     * Cabecera de la app ("Juan Pérez · Acopiador · Collana 1") y "Mi perfil",
     * para mostrarse también sin conexión desde la copia local.
     *
     * @return array<string, mixed>
     */
    private function serializarAcopiador(AcopiadorModel $acopiador): array
    {
        return [
            'id' => $acopiador->id,
            'nombres' => $acopiador->nombres,
            'apellidos' => $acopiador->apellidos,
            'dni' => $acopiador->dni,
            'telefono' => $acopiador->telefono,
            'estado' => $acopiador->estado,
            'zona_id' => $acopiador->zona_id,
            'zona_nombre' => $acopiador->zona?->nombre,
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
