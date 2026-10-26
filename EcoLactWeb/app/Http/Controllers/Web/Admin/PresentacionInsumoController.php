<?php

namespace App\Http\Controllers\Web\Admin;

use App\Application\Produccion\GuardarPresentacionInsumo;
use App\Domain\Produccion\PresentacionInsumo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produccion\StorePresentacionInsumoRequest;
use App\Infrastructure\Persistence\Models\InsumoModel;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Presentaciones de compra de un insumo, gestionadas desde su panel en la
 * pantalla Insumos. Vuelve a la misma pantalla con el insumo seleccionado.
 */
final class PresentacionInsumoController extends Controller
{
    public function __construct(
        private readonly GuardarPresentacionInsumo $guardarPresentacion,
    ) {}

    public function store(StorePresentacionInsumoRequest $request, InsumoModel $insumo): RedirectResponse
    {
        return $this->guardar($request, $insumo, null, 'Presentación agregada.');
    }

    public function update(StorePresentacionInsumoRequest $request, InsumoModel $insumo, int $presentacion): RedirectResponse
    {
        return $this->guardar($request, $insumo, $presentacion, 'Presentación actualizada.');
    }

    private function guardar(StorePresentacionInsumoRequest $request, InsumoModel $insumo, ?int $presentacionId, string $mensaje): RedirectResponse
    {
        Gate::authorize('update', $insumo);

        $datos = $request->validated();
        $volver = redirect()->route('admin.insumos.index', ['insumo' => $insumo->id]);

        try {
            $guardada = $this->guardarPresentacion->execute(
                insumoId: $insumo->id,
                nombre: $datos['nombre'],
                cantidad: (float) $datos['cantidad'],
                unidad: $datos['unidad'],
                presentacionId: $presentacionId,
                estado: $datos['estado'] ?? PresentacionInsumo::ACTIVA,
            );
        } catch (DomainException $e) {
            return $volver->withInput()->with('error', $e->getMessage());
        }

        return $volver->with('success', "{$mensaje} {$guardada->etiqueta()} = ".rtrim(rtrim(number_format($guardada->cantidadBase(), 4), '0'), '.')." {$insumo->unidad_base}.");
    }
}
