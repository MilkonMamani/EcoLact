<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Application\Ventas\ObtenerCompradores;
use App\Application\Ventas\RegistrarComprador;
use App\Domain\Ventas\Comprador;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreCompradorRequest;
use App\Infrastructure\Persistence\Models\CompradorModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class CompradorController extends Controller
{
    public function __construct(
        private readonly RegistrarComprador $registrarComprador,
        private readonly ObtenerCompradores $obtenerCompradores,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CompradorModel::class);

        $compradores = collect($this->obtenerCompradores->execute());

        if ($request->filled('buscar')) {
            $texto = mb_strtolower($request->string('buscar')->toString());
            $compradores = $compradores->filter(
                fn (Comprador $c): bool => str_contains(mb_strtolower($c->nombre()), $texto)
                    || str_contains(mb_strtolower($c->documento() ?? ''), $texto)
            );
        }

        if ($request->filled('tipo_cliente')) {
            $compradores = $compradores->filter(fn (Comprador $c): bool => $c->tipoCliente() === $request->string('tipo_cliente')->toString());
        }

        return response()->json(['data' => $compradores->values()->map($this->serializar(...))->all()]);
    }

    public function store(StoreCompradorRequest $request): JsonResponse
    {
        Gate::authorize('create', CompradorModel::class);

        $datos = $request->validated();

        $comprador = $this->registrarComprador->execute(
            nombre: $datos['nombre'],
            documento: $datos['documento'] ?? null,
            telefono: $datos['telefono'] ?? null,
        );

        return response()->json(['data' => $this->serializar($comprador)], 201);
    }

    /** @return array<string, mixed> */
    private function serializar(Comprador $comprador): array
    {
        return [
            'id' => $comprador->id(),
            'nombre' => $comprador->nombre(),
            'documento' => $comprador->documento(),
            'telefono' => $comprador->telefono(),
            'tipo_cliente' => $comprador->tipoCliente(),
            'estado' => $comprador->estado(),
        ];
    }
}
