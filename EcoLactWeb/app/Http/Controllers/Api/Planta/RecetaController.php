<?php

namespace App\Http\Controllers\Api\Planta;

use App\Application\Produccion\CalcularRequerimientosProduccion;
use App\Application\Produccion\Contracts\RecetasListadoQuery;
use App\Application\Produccion\RequerimientoInsumo;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\RecetaModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Recetas en SOLO LECTURA para el encargado de planta (las configura el
 * ADMINISTRADOR, RecetaPolicy). Una receta actual por producto.
 *
 * `por_unidad` es lo que necesita UNA unidad del producto, ya en la unidad
 * base de cada insumo, calculado con el mismo Use Case que inicia la
 * producción (CalcularRequerimientosProduccion). La app lo usa para mostrar
 * "Por cada unidad" y para previsualizar sin conexión; al sincronizar, el
 * servidor vuelve a calcular y validar el stock.
 */
final class RecetaController extends Controller
{
    public function __construct(
        private readonly RecetasListadoQuery $recetasListadoQuery,
        private readonly CalcularRequerimientosProduccion $calcularRequerimientos,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', RecetaModel::class);

        $datos = $this->recetasListadoQuery->obtener(['solo_vigente' => true]);

        return response()->json([
            'data' => [
                'kpis' => $datos->kpis,
                'filas' => array_map(fn (array $fila): array => $fila + ['por_unidad' => $this->porUnidad($fila)], $datos->filas),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return list<array<string, mixed>>
     */
    private function porUnidad(array $fila): array
    {
        if ($fila['estado'] !== 'ACTIVA') {
            return [];
        }

        try {
            $requerimientos = $this->calcularRequerimientos->execute([['producto_id' => $fila['productoId'], 'cantidad' => 1]]);
        } catch (DomainException) {
            return [];
        }

        return array_map(static fn (RequerimientoInsumo $r): array => [
            'insumo_id' => $r->insumoId,
            'nombre' => $r->nombre,
            'unidad' => $r->unidadBase,
            'cantidad' => $r->necesario,
            'es_leche' => $r->esLeche,
        ], $requerimientos->insumos);
    }
}
