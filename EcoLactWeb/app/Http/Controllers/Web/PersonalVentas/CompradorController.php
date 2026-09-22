<?php

namespace App\Http\Controllers\Web\PersonalVentas;

use App\Application\Ventas\ObtenerCompradores;
use App\Application\Ventas\RegistrarComprador;
use App\Domain\Ventas\Comprador;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreCompradorRequest;
use App\Infrastructure\Persistence\Models\CompradorModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portal del VENTAS: "Compradores" (mayoristas y público que se quieren
 * identificar). Reutiliza los MISMOS Use Cases que admin.compradores.*
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
            );
        }

        if ($request->filled('tipo_cliente')) {
            $compradores = $compradores->filter(fn (Comprador $c): bool => $c->tipoCliente() === $request->string('tipo_cliente')->toString());
        }

        $todos = collect($this->obtenerCompradores->execute());

        $kpis = [
            'total' => $todos->count(),
            'mayoristas' => $todos->filter(fn (Comprador $c): bool => $c->tipoCliente() === 'MAYORISTA')->count(),
            'publico' => $todos->filter(fn (Comprador $c): bool => $c->tipoCliente() === 'PUBLICO')->count(),
        ];

        return view('ventas-panel.compradores.index', [
            'compradores' => $compradores->values()->all(),
            'kpis' => $kpis,
        ]);
    }

    public function store(StoreCompradorRequest $request): RedirectResponse
    {
        Gate::authorize('create', CompradorModel::class);

        $datos = $request->validated();

        $this->registrarComprador->execute(
            nombre: $datos['nombre'],
            tipoCliente: $datos['tipo_cliente'],
            documento: $datos['documento'] ?? null,
            telefono: $datos['telefono'] ?? null,
        );

        return redirect()->route('ventas.compradores.index')->with('success', 'Comprador registrado correctamente.');
    }
}
