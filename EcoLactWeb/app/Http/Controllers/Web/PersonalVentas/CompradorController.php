<?php

namespace App\Http\Controllers\Web\PersonalVentas;

use App\Application\Ventas\ObtenerCompradores;
use App\Application\Ventas\RegistrarComprador;
use App\Domain\Ventas\Comprador;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreCompradorRequest;
use App\Http\Support\PantallaVentas;
use App\Infrastructure\Persistence\Models\CompradorModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portal del VENTAS: "Compradores" (agenda de clientes registrados).
 * Reutiliza los MISMOS Use Cases que admin.compradores.*
 * (Web\Ventas\CompradorController); solo cambia la vista servida.
 */
final class CompradorController extends Controller
{
    public function __construct(
        private readonly RegistrarComprador $registrarComprador,
        private readonly ObtenerCompradores $obtenerCompradores,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CompradorModel::class);

        $compradores = collect($this->obtenerCompradores->execute());

        if ($request->filled('buscar')) {
            $texto = mb_strtolower($request->string('buscar')->toString());
            $compradores = $compradores->filter(
                fn (Comprador $c): bool => str_contains(mb_strtolower($c->nombre()), $texto)
                    || str_contains(mb_strtolower($c->documento() ?? ''), $texto)
                    || str_contains($c->telefono() ?? '', $texto)
            );
        }

        return view('ventas-panel.compradores.index', [
            'compradores' => $compradores->values()->all(),
        ]);
    }

    public function store(StoreCompradorRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', CompradorModel::class);

        $datos = $request->validated();

        $comprador = $this->registrarComprador->execute(
            nombre: $datos['nombre'],
            documento: $datos['documento'] ?? null,
            telefono: $datos['telefono'] ?? null,
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => PantallaVentas::comprador($comprador)], 201);
        }

        return redirect()->route('ventas.compradores.index')->with('success', 'Comprador registrado correctamente.');
    }
}
