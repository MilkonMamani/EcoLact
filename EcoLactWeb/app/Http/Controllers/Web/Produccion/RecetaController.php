<?php

namespace App\Http\Controllers\Web\Produccion;

use App\Application\Produccion\ActivarReceta;
use App\Application\Produccion\CambiarImagenProducto;
use App\Application\Produccion\Contracts\RecetasListadoQuery;
use App\Application\Produccion\CrearNuevaVersionReceta;
use App\Application\Produccion\CrearReceta;
use App\Application\Produccion\CrearRecetaConProducto;
use App\Application\Produccion\DesactivarReceta;
use App\Application\Produccion\GuardarCambiosReceta;
use App\Application\Produccion\ImagenSubida;
use App\Application\Produccion\ObtenerInsumos;
use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\UnidadMedida;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produccion\StoreRecetaRequest;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\RecetaModel;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Módulo Recetas (ADMINISTRADOR). La receta es el ORIGEN del producto: al
 * guardar una receta nueva se crea su producto terminado (stock 0) y su imagen
 * se guarda una sola vez en ese producto. Ninguna acción de este controlador
 * mueve inventario: los insumos solo se consumen al iniciar una producción y
 * el producto solo sube al finalizarla.
 */
final class RecetaController extends Controller
{
    private const TIPOS_SUGERIDOS = ['Queso', 'Yogurt', 'Mantequilla'];

    public function __construct(
        private readonly RecetasListadoQuery $recetasListadoQuery,
        private readonly CrearRecetaConProducto $crearRecetaConProducto,
        private readonly CrearReceta $crearReceta,
        private readonly CambiarImagenProducto $cambiarImagenProducto,
        private readonly GuardarCambiosReceta $guardarCambiosReceta,
        private readonly CrearNuevaVersionReceta $crearNuevaVersionReceta,
        private readonly ActivarReceta $activarReceta,
        private readonly DesactivarReceta $desactivarReceta,
        private readonly ObtenerInsumos $obtenerInsumos,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', RecetaModel::class);

        $datos = $this->recetasListadoQuery->obtener([
            'producto_id' => $request->integer('producto') ?: null,
            'estado' => $request->query('estado'),
            'busqueda' => $request->query('q'),
            'solo_vigente' => true,
        ]);

        return view('admin.recetas.index', [
            'datos' => $datos,
            'insumos' => array_map(fn (Insumo $i): array => [
                'id' => $i->id(),
                'nombre' => $i->nombre(),
                'unidad_base' => $i->unidadBase(),
                'unidades' => UnidadMedida::opciones(),
                'compatibles' => UnidadMedida::opcionesCompatiblesCon($i->unidadBase()),
            ], $this->obtenerInsumos->execute()),
            'tiposProducto' => $this->tiposProducto(),
            'unidadesPresentacion' => UnidadMedida::opciones(),
            'productoPorConfigurar' => $this->productoPorConfigurar($request->integer('configurar') ?: null),
        ]);
    }

    public function store(StoreRecetaRequest $request): RedirectResponse
    {
        Gate::authorize('create', RecetaModel::class);

        $datos = $request->validated();
        $imagen = $this->imagenDe($request);

        try {
            if (isset($datos['producto_existente_id'])) {
                $receta = $this->crearReceta->execute(
                    productoId: (int) $datos['producto_existente_id'],
                    descripcion: $datos['descripcion'] ?? null,
                    ingredientes: $datos['ingredientes'],
                    creadaPorUserId: $request->user()->id,
                );

                if ($imagen !== null) {
                    $this->cambiarImagenProducto->execute($receta->productoId(), $imagen);
                }
            } else {
                $receta = $this->crearRecetaConProducto->execute(
                    tipoProducto: $datos['tipo_producto'],
                    nombreBase: $datos['nombre_producto'],
                    presentacionCantidad: (float) $datos['presentacion_cantidad'],
                    presentacionUnidad: $datos['presentacion_unidad'],
                    descripcion: $datos['descripcion'] ?? null,
                    ingredientes: $datos['ingredientes'],
                    creadaPorUserId: $request->user()->id,
                    imagen: $imagen,
                );
            }
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.recetas.index')->with('success', 'Receta guardada. Su producto queda con stock 0 y no se descontaron insumos: se consumen al iniciar una producción.');
    }

    public function update(StoreRecetaRequest $request, RecetaModel $receta): RedirectResponse
    {
        Gate::authorize('update', $receta);

        $datos = $request->validated();

        try {
            $this->guardarCambiosReceta->execute(
                recetaId: $receta->id,
                descripcion: $datos['descripcion'] ?? null,
                ingredientes: $datos['ingredientes'],
                usuarioId: $request->user()->id,
                nuevaImagen: $this->imagenDe($request),
            );
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.recetas.index')->with('success', 'Receta actualizada. Guardar cambios no descuenta inventario.');
    }

    public function nuevaVersion(StoreRecetaRequest $request, RecetaModel $receta): RedirectResponse
    {
        Gate::authorize('create', RecetaModel::class);

        $datos = $request->validated();

        try {
            $nueva = $this->crearNuevaVersionReceta->execute(
                recetaBaseId: $receta->id,
                descripcion: $datos['descripcion'] ?? null,
                ingredientes: $datos['ingredientes'],
                creadaPorUserId: $request->user()->id,
            );
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.recetas.index')->with('success', "Se creó la versión {$nueva->version()} de la receta.");
    }

    public function activar(RecetaModel $receta): RedirectResponse
    {
        Gate::authorize('update', $receta);

        try {
            $this->activarReceta->execute($receta->id);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Receta activada.');
    }

    public function desactivar(RecetaModel $receta): RedirectResponse
    {
        Gate::authorize('update', $receta);

        try {
            $this->desactivarReceta->execute($receta->id);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Receta desactivada. El producto no se podrá producir hasta activar una receta.');
    }

    private function imagenDe(Request $request): ?ImagenSubida
    {
        $archivo = $request->file('imagen');

        if ($archivo === null || ! $archivo->isValid()) {
            return null;
        }

        return new ImagenSubida($archivo->getRealPath(), $archivo->extension() ?: 'jpg');
    }

    /**
     * @return list<string>
     */
    private function tiposProducto(): array
    {
        $existentes = ProductoModel::query()->whereNotNull('tipo_producto')->distinct()->pluck('tipo_producto')->all();

        return array_values(array_unique([...self::TIPOS_SUGERIDOS, ...$existentes]));
    }

    /**
     * Un producto histórico sin receta que se va a configurar (enlace
     * "Configurar receta" desde Productos): se crea su primera receta sin
     * duplicar el producto.
     *
     * @return array{id: int, nombre: string}|null
     */
    private function productoPorConfigurar(?int $productoId): ?array
    {
        if ($productoId === null) {
            return null;
        }

        $producto = ProductoModel::withCount('recetas')->find($productoId);

        return $producto !== null && $producto->recetas_count === 0
            ? ['id' => $producto->id, 'nombre' => $producto->nombre]
            : null;
    }
}
