<?php

namespace App\Http\Controllers\Web\Ventas;

use App\Application\Ventas\ObtenerCompradores;
use App\Application\Ventas\RegistrarComprador;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreCompradorRequest;
use App\Infrastructure\Persistence\Models\CompradorModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

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

        return redirect()->back()->with('success', 'Comprador registrado correctamente.');
    }
}
