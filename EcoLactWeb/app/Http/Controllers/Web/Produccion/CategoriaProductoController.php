<?php

namespace App\Http\Controllers\Web\Produccion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Produccion\StoreCategoriaProductoRequest;
use App\Infrastructure\Persistence\Models\CategoriaProductoModel;
use App\Infrastructure\Persistence\Models\RecetaModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Registra una categoría de producto desde "+ Nueva categoría" del modal de
 * receta (responde JSON: el combobox se actualiza sin recargar). Quien puede
 * crear recetas puede crear categorías; no hay pantalla de administración.
 */
final class CategoriaProductoController extends Controller
{
    public function store(StoreCategoriaProductoRequest $request): JsonResponse
    {
        Gate::authorize('create', RecetaModel::class);

        try {
            $categoria = CategoriaProductoModel::create(['nombre' => $request->validated('nombre'), 'estado' => 'ACTIVA']);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Ya existe esa categoría.',
                'errors' => ['nombre' => ['Ya existe esa categoría.']],
            ], 422);
        }

        return response()->json(['id' => $categoria->id, 'nombre' => $categoria->nombre], 201);
    }
}
