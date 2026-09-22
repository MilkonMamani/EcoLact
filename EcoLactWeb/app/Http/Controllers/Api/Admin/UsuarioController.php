<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Acopiador\ObtenerAcopiadores;
use App\Application\AnalistaCalidad\ObtenerAnalistasCalidad;
use App\Application\EncargadoPlanta\ObtenerEncargadosPlanta;
use App\Application\PersonalVentas\ObtenerListaPersonalVentas;
use App\Application\Proveedor\ObtenerProveedores;
use App\Domain\Acopiador\Acopiador;
use App\Domain\AnalistaCalidad\AnalistaCalidad;
use App\Domain\EncargadoPlanta\EncargadoPlanta;
use App\Domain\PersonalVentas\PersonalVentas;
use App\Domain\Proveedor\Proveedor;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use App\Infrastructure\Persistence\Models\EncargadoPlantaModel;
use App\Infrastructure\Persistence\Models\PersonalVentasModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Vista compuesta "Usuarios": el backend no tiene un listado unificado de
 * personas (cada tipo vive en su propia tabla/Policy con su propio CRUD ya
 * probado — ver Api\Admin\AcopiadorController, ProveedorController,
 * EncargadoPlantaController, PersonalVentasController,
 * AnalistaCalidadController). Este endpoint combina los 5 listados de solo
 * lectura en una sola respuesta para la pantalla "Gestión > Usuarios"; el
 * detalle y las acciones de cada persona siguen resolviéndose contra el
 * endpoint propio de su tipo.
 */
final class UsuarioController extends Controller
{
    public function __construct(
        private readonly ObtenerAcopiadores $obtenerAcopiadores,
        private readonly ObtenerProveedores $obtenerProveedores,
        private readonly ObtenerEncargadosPlanta $obtenerEncargadosPlanta,
        private readonly ObtenerListaPersonalVentas $obtenerListaPersonalVentas,
        private readonly ObtenerAnalistasCalidad $obtenerAnalistasCalidad,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AcopiadorModel::class);
        Gate::authorize('viewAny', ProveedorModel::class);
        Gate::authorize('viewAny', EncargadoPlantaModel::class);
        Gate::authorize('viewAny', PersonalVentasModel::class);
        Gate::authorize('viewAny', AnalistaCalidadModel::class);

        $filas = [
            ...array_map(fn (Acopiador $a): array => $this->fila('ACOPIADOR', $a->id(), $a->nombreCompleto(), $a->dni(), $a->estado()->value, $a->zonaId()), $this->obtenerAcopiadores->execute()),
            ...array_map(fn (Proveedor $p): array => $this->fila('PROVEEDOR', $p->id(), $p->nombreCompleto(), $p->dni(), $p->estado()->value, $p->zonaId()), $this->obtenerProveedores->execute()),
            ...array_map(fn (EncargadoPlanta $e): array => $this->fila('ENCARGADO_PLANTA', $e->id(), $e->nombreCompleto(), $e->dni(), $e->estado()->value, null), $this->obtenerEncargadosPlanta->execute()),
            ...array_map(fn (PersonalVentas $v): array => $this->fila('VENTAS', $v->id(), $v->nombreCompleto(), $v->dni(), $v->estado()->value, null), $this->obtenerListaPersonalVentas->execute()),
            ...array_map(fn (AnalistaCalidad $c): array => $this->fila('CALIDAD', $c->id(), $c->nombreCompleto(), $c->dni(), $c->estado()->value, null), $this->obtenerAnalistasCalidad->execute()),
        ];

        if ($request->filled('tipo')) {
            $tipo = $request->string('tipo')->toString();
            $filas = array_values(array_filter($filas, static fn (array $fila): bool => $fila['tipo'] === $tipo));
        }

        if ($request->filled('estado')) {
            $estado = $request->string('estado')->toString();
            $filas = array_values(array_filter($filas, static fn (array $fila): bool => $fila['estado'] === $estado));
        }

        if ($request->filled('buscar')) {
            $texto = mb_strtolower($request->string('buscar')->toString());
            $filas = array_values(array_filter(
                $filas,
                static fn (array $fila): bool => str_contains(mb_strtolower($fila['nombre_completo']), $texto) || str_contains($fila['dni'], $texto),
            ));
        }

        return response()->json([
            'data' => [
                'kpis' => [
                    'total' => count($filas),
                    'activos' => count(array_filter($filas, static fn (array $f): bool => $f['estado'] === 'ACTIVO')),
                ],
                'filas' => $filas,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function fila(string $tipo, ?int $id, string $nombreCompleto, string $dni, string $estado, ?int $zonaId): array
    {
        return [
            'tipo' => $tipo,
            'id' => $id,
            'nombre_completo' => $nombreCompleto,
            'dni' => $dni,
            'estado' => $estado,
            'zona_id' => $zonaId,
        ];
    }
}
