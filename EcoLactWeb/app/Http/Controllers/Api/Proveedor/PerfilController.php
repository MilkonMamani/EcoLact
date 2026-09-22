<?php

namespace App\Http\Controllers\Api\Proveedor;

use App\Application\Proveedor\ObtenerProveedor;
use App\Domain\Proveedor\Proveedor;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Solo lectura: el backend todavía no expone edición de datos propios (ni
 * siquiera en el panel web), así que este endpoint no tiene un PATCH
 * equivalente por ahora.
 */
final class PerfilController extends Controller
{
    public function __construct(
        private readonly ObtenerProveedor $obtenerProveedor,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $proveedorId = $request->user()->proveedor?->id
            ?? throw new DomainException('No existe un proveedor asociado al usuario autenticado.');

        return response()->json(['data' => $this->serializar($this->obtenerProveedor->execute($proveedorId))]);
    }

    /** @return array<string, mixed>|null */
    private function serializar(?Proveedor $proveedor): ?array
    {
        if ($proveedor === null) {
            return null;
        }

        return [
            'id' => $proveedor->id(),
            'dni' => $proveedor->dni(),
            'nombres' => $proveedor->nombres(),
            'apellidos' => $proveedor->apellidos(),
            'nombre_completo' => $proveedor->nombreCompleto(),
            'telefono' => $proveedor->telefono(),
            'zona_id' => $proveedor->zonaId(),
            'estado' => $proveedor->estado()->value,
            'fecha_registro' => $proveedor->fechaRegistro()->format('Y-m-d'),
        ];
    }
}
