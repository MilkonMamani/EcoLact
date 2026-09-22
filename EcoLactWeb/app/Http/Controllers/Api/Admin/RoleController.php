<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\RoleModel;
use Illuminate\Http\JsonResponse;

/**
 * Solo lectura: nombre del rol + cantidad de usuarios. No hay constructor
 * de roles/permisos (deliberado: los 6 roles del sistema son fijos).
 */
final class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => RoleModel::withCount('users')->orderBy('nombre')->get()->map(fn (RoleModel $role): array => [
                'id' => $role->id,
                'nombre' => $role->nombre,
                'descripcion' => $role->descripcion,
                'usuarios_count' => $role->users_count,
            ]),
        ]);
    }
}
