<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\EncargadoPlanta\ObtenerEncargadoPlanta;
use App\Application\EncargadoPlanta\ObtenerEncargadosPlanta;
use App\Domain\EncargadoPlanta\EncargadoPlanta;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\EncargadoPlantaModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class EncargadoPlantaController extends Controller
{
    public function __construct(
        private readonly ObtenerEncargadosPlanta $obtenerEncargadosPlanta,
        private readonly ObtenerEncargadoPlanta $obtenerEncargadoPlanta,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', EncargadoPlantaModel::class);

        return response()->json(['data' => array_map($this->serializar(...), $this->obtenerEncargadosPlanta->execute())]);
    }

    public function show(EncargadoPlantaModel $encargado): JsonResponse
    {
        Gate::authorize('view', $encargado);

        return response()->json(['data' => $this->serializar($this->obtenerEncargadoPlanta->execute($encargado->id))]);
    }

    /** @return array<string, mixed>|null */
    private function serializar(?EncargadoPlanta $encargado): ?array
    {
        if ($encargado === null) {
            return null;
        }

        return [
            'id' => $encargado->id(),
            'dni' => $encargado->dni(),
            'nombres' => $encargado->nombres(),
            'apellidos' => $encargado->apellidos(),
            'nombre_completo' => $encargado->nombreCompleto(),
            'telefono' => $encargado->telefono(),
            'estado' => $encargado->estado()->value,
            'fecha_ingreso' => $encargado->fechaIngreso()?->format('Y-m-d'),
        ];
    }
}
