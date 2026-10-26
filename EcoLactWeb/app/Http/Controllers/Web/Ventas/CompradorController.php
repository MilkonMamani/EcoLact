<?php

namespace App\Http\Controllers\Web\Ventas;

use App\Application\Ventas\ObtenerCompradores;
use App\Application\Ventas\RegistrarComprador;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreCompradorRequest;
use App\Http\Support\PantallaVentas;
use App\Infrastructure\Persistence\Models\CompradorModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Compradores = agenda de clientes registrados. La misma agenda alimenta
 * "Comprador registrado" en Nueva venta (cuyo modal guarda aquí por fetch).
 */
final class CompradorController extends Controller
{
    public function __construct(
        private readonly RegistrarComprador $registrarComprador,
        private readonly ObtenerCompradores $obtenerCompradores,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', CompradorModel::class);

        return view('ventas.compradores.index', [
            'compradores' => $this->obtenerCompradores->execute(),
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

        return redirect()->back()->with('success', 'Comprador registrado correctamente.');
    }
}
