<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\PersonalVentas\ObtenerListaPersonalVentas;
use App\Application\PersonalVentas\ObtenerPersonalVentas;
use App\Domain\PersonalVentas\PersonalVentas;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\PersonalVentasModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class PersonalVentasController extends Controller
{
    public function __construct(
        private readonly ObtenerListaPersonalVentas $obtenerListaPersonalVentas,
        private readonly ObtenerPersonalVentas $obtenerPersonalVentas,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', PersonalVentasModel::class);

        return response()->json(['data' => array_map($this->serializar(...), $this->obtenerListaPersonalVentas->execute())]);
    }

    public function show(PersonalVentasModel $personalVenta): JsonResponse
    {
        Gate::authorize('view', $personalVenta);

        return response()->json(['data' => $this->serializar($this->obtenerPersonalVentas->execute($personalVenta->id))]);
    }

    /** @return array<string, mixed>|null */
    private function serializar(?PersonalVentas $personal): ?array
    {
        if ($personal === null) {
            return null;
        }

        return [
            'id' => $personal->id(),
            'dni' => $personal->dni(),
            'nombres' => $personal->nombres(),
            'apellidos' => $personal->apellidos(),
            'nombre_completo' => $personal->nombreCompleto(),
            'telefono' => $personal->telefono(),
            'estado' => $personal->estado()->value,
            'fecha_ingreso' => $personal->fechaIngreso()?->format('Y-m-d'),
        ];
    }
}
