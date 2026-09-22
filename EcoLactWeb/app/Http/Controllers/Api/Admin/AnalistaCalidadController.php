<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\AnalistaCalidad\ObtenerAnalistaCalidad;
use App\Application\AnalistaCalidad\ObtenerAnalistasCalidad;
use App\Domain\AnalistaCalidad\AnalistaCalidad;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class AnalistaCalidadController extends Controller
{
    public function __construct(
        private readonly ObtenerAnalistasCalidad $obtenerAnalistasCalidad,
        private readonly ObtenerAnalistaCalidad $obtenerAnalistaCalidad,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', AnalistaCalidadModel::class);

        return response()->json(['data' => array_map($this->serializar(...), $this->obtenerAnalistasCalidad->execute())]);
    }

    public function show(AnalistaCalidadModel $analista): JsonResponse
    {
        Gate::authorize('view', $analista);

        return response()->json(['data' => $this->serializar($this->obtenerAnalistaCalidad->execute($analista->id))]);
    }

    /** @return array<string, mixed>|null */
    private function serializar(?AnalistaCalidad $analista): ?array
    {
        if ($analista === null) {
            return null;
        }

        return [
            'id' => $analista->id(),
            'dni' => $analista->dni(),
            'nombres' => $analista->nombres(),
            'apellidos' => $analista->apellidos(),
            'nombre_completo' => $analista->nombreCompleto(),
            'telefono' => $analista->telefono(),
            'estado' => $analista->estado()->value,
            'fecha_ingreso' => $analista->fechaIngreso()?->format('Y-m-d'),
        ];
    }
}
