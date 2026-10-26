<?php

use App\Application\Produccion\CrearReceta;
use App\Application\Produccion\RegistrarInsumo;
use App\Application\Produccion\RegistrarMovimientoInsumo;
use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\LoteProduccion;
use App\Domain\Produccion\LoteProduccionRepository;
use App\Domain\Produccion\Receta;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\CategoriaProductoModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionInsumoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\ParametroCalidadModel;
use App\Infrastructure\Persistence\Models\PrecioLecheModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ReglaCalidadModel;
use App\Infrastructure\Persistence\Models\ReglaPagoCalidadModel;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;

/**
 * Fábricas mínimas y reutilizables para los tests Feature de Application.
 * No es un archivo de test (no llama a it()/test()) y vive fuera de
 * tests/Unit y tests/Feature, por lo que PHPUnit/Pest no lo descubre como
 * suite; cada archivo de test lo incluye explícitamente con require_once.
 */
if (! function_exists('crear_validador')) {
    /**
     * Construye un Validator a partir de las reglas/mensajes de un Form
     * Request, sin necesidad de rutas ni controllers (aún no existen en
     * esta etapa). Instancia el Form Request vía su propio create()
     * (heredado de Symfony\Component\HttpFoundation\Request) para que
     * $this->input()/$this->route() dentro de rules() funcionen igual que
     * en una petición HTTP real.
     *
     * También ejecuta withValidator() cuando el Request lo define (por
     * ejemplo, StoreReglaCalidadRequest valida ahí que mínimo <= máximo),
     * igual que haría Laravel al resolver el Form Request en un Controller.
     *
     * @param  class-string<FormRequest>  $requestClass
     * @param  array<string, mixed>  $data
     */
    function crear_validador(string $requestClass, array $data): Validator
    {
        /** @var FormRequest $request */
        $request = $requestClass::create('/test', 'POST', $data);
        $request->setContainer(app());

        $validator = Illuminate\Support\Facades\Validator::make(
            $data,
            $request->rules(),
            method_exists($request, 'messages') ? $request->messages() : [],
        );

        if (method_exists($request, 'withValidator')) {
            $request->withValidator($validator);
        }

        return $validator;
    }
}

if (! function_exists('registrar_vistas_stub')) {
    /**
     * Etapa 10.1.1: los Controllers de lectura (index/show) ya llaman a
     * view('admin....'), pero las plantillas Blade reales aún no existen
     * (Etapa 10.3 las crea). Para probar la delegación Controller -> Use
     * Case sin invocar el Kernel HTTP ni depender de vistas de producción,
     * los tests registran esta carpeta de stubs (vacíos, nunca se
     * renderizan) como ubicación adicional del ViewFinder.
     */
    function registrar_vistas_stub(): void
    {
        app('view')->addLocation(__DIR__.'/stub-views');
    }
}

if (! function_exists('crear_request_autenticado')) {
    /**
     * Un Request construido con Request::create() no tiene el
     * userResolver que Laravel normalmente inyecta al vincular 'request'
     * en el contenedor durante el ciclo HTTP real; sin él, $request->user()
     * devuelve null aunque actingAs() ya haya autenticado al guard. Se
     * asigna aquí explícitamente para las llamadas directas a Controllers.
     */
    function crear_request_autenticado(string $uri = '/test', array $query = []): Request
    {
        $request = Request::create($uri, 'GET', $query);
        $request->setUserResolver(fn () => auth()->user());

        return $request;
    }
}

if (! function_exists('fixture_next_dni')) {
    function fixture_next_dni(): string
    {
        static $contador = 10000000;

        return (string) $contador++;
    }
}

if (! function_exists('fixture_qr_token')) {
    function fixture_qr_token(): string
    {
        return bin2hex(random_bytes(18));
    }
}

if (! function_exists('fixture_rol')) {
    function fixture_rol(string $nombre): RoleModel
    {
        return RoleModel::firstOrCreate(['nombre' => $nombre], ['descripcion' => null, 'es_remunerable' => $nombre !== 'PROVEEDOR', 'controla_asistencia' => $nombre !== 'ADMINISTRADOR']);
    }
}

if (! function_exists('fixture_zona')) {
    function fixture_zona(?string $nombre = null): ZonaModel
    {
        return ZonaModel::create([
            'nombre' => $nombre ?? ('Zona '.uniqid()),
            'descripcion' => null,
            'estado' => 'ACTIVO',
        ]);
    }
}

if (! function_exists('fixture_usuario')) {
    function fixture_usuario(string $rolNombre, ?string $username = null): User
    {
        $role = fixture_rol($rolNombre);

        return User::create([
            'role_id' => $role->id,
            'username' => $username ?? (strtolower($rolNombre).'.'.uniqid()),
            'password' => 'secret',
            'estado' => 'ACTIVO',
        ]);
    }
}

if (! function_exists('fixture_proveedor')) {
    function fixture_proveedor(ZonaModel $zona, ?string $dni = null): ProveedorModel
    {
        $user = fixture_usuario('PROVEEDOR');

        return ProveedorModel::create([
            'user_id' => $user->id,
            'zona_id' => $zona->id,
            'dni' => $dni ?? fixture_next_dni(),
            'nombres' => 'Proveedor',
            'apellidos' => 'Test',
            'telefono' => null,
            'qr_token' => fixture_qr_token(),
            'estado' => 'ACTIVO',
            'fecha_registro' => now(),
        ]);
    }
}

if (! function_exists('fixture_acopiador')) {
    function fixture_acopiador(ZonaModel $zona, ?string $dni = null): AcopiadorModel
    {
        $user = fixture_usuario('ACOPIADOR');

        return AcopiadorModel::create([
            'user_id' => $user->id,
            'zona_id' => $zona->id,
            'dni' => $dni ?? fixture_next_dni(),
            'nombres' => 'Acopiador',
            'apellidos' => 'Test',
            'telefono' => null,
            'estado' => 'ACTIVO',
            'fecha_ingreso' => now(),
        ]);
    }
}

if (! function_exists('fixture_entrega')) {
    function fixture_entrega(
        ProveedorModel $proveedor,
        AcopiadorModel $acopiador,
        float $litros,
        ?DateTimeImmutable $fechaHora = null,
    ): EntregaModel {
        return EntregaModel::create([
            'proveedor_id' => $proveedor->id,
            'acopiador_id' => $acopiador->id,
            'zona_id' => $proveedor->zona_id,
            'registrado_por_user_id' => $acopiador->user_id,
            'fecha_hora' => ($fechaHora ?? new DateTimeImmutable)->format('Y-m-d H:i:s'),
            'litros' => $litros,
            'metodo_identificacion' => 'DNI',
            'estado' => 'PENDIENTE_ANALISIS',
        ]);
    }
}

if (! function_exists('fixture_parametro')) {
    function fixture_parametro(string $codigo): ParametroCalidadModel
    {
        return ParametroCalidadModel::firstOrCreate(
            ['codigo' => $codigo],
            ['nombre' => $codigo, 'unidad' => null, 'estado' => 'ACTIVO'],
        );
    }
}

if (! function_exists('fixture_regla_calidad')) {
    function fixture_regla_calidad(
        int $parametroId,
        ?float $valorMinimo,
        ?float $valorMaximo,
        string $resultadoIncumplimiento,
        ?DateTimeImmutable $fechaInicio = null,
        int $prioridad = 1,
    ): ReglaCalidadModel {
        return ReglaCalidadModel::create([
            'parametro_id' => $parametroId,
            'valor_minimo' => $valorMinimo,
            'valor_maximo' => $valorMaximo,
            'resultado_incumplimiento' => $resultadoIncumplimiento,
            'prioridad' => $prioridad,
            'fecha_inicio' => ($fechaInicio ?? new DateTimeImmutable('2026-01-01'))->format('Y-m-d'),
            'fecha_fin' => null,
            'estado' => 'ACTIVO',
        ]);
    }
}

if (! function_exists('fixture_analisis_confirmado')) {
    function fixture_analisis_confirmado(
        EntregaModel $entrega,
        string $resultado,
        int $confirmadoPorUserId,
    ): AnalisisCalidadModel {
        return AnalisisCalidadModel::create([
            'entrega_id' => $entrega->id,
            'imagen_path' => null,
            'estado' => 'CONFIRMADO',
            'resultado' => $resultado,
            'fecha_analisis' => $entrega->fecha_hora,
            'confirmado_por_user_id' => $confirmadoPorUserId,
        ]);
    }
}

if (! function_exists('fixture_precio_leche')) {
    function fixture_precio_leche(float $precio, ?DateTimeImmutable $fechaInicio, int $creadoPorUserId): PrecioLecheModel
    {
        return PrecioLecheModel::create([
            'precio_litro' => $precio,
            'fecha_inicio' => ($fechaInicio ?? new DateTimeImmutable('2026-01-01'))->format('Y-m-d'),
            'fecha_fin' => null,
            'estado' => 'ACTIVO',
            'creado_por_user_id' => $creadoPorUserId,
        ]);
    }
}

if (! function_exists('fixture_regla_pago')) {
    function fixture_regla_pago(
        string $resultadoCalidad,
        string $tipoAjuste,
        float $valorAjuste,
        ?DateTimeImmutable $fechaInicio = null,
    ): ReglaPagoCalidadModel {
        return ReglaPagoCalidadModel::create([
            'resultado_calidad' => $resultadoCalidad,
            'tipo_ajuste' => $tipoAjuste,
            'valor_ajuste' => $valorAjuste,
            'fecha_inicio' => ($fechaInicio ?? new DateTimeImmutable('2026-01-01'))->format('Y-m-d'),
            'fecha_fin' => null,
            'estado' => 'ACTIVO',
        ]);
    }
}

if (! function_exists('fixture_ingreso_producto')) {
    /**
     * Deja stock de un producto terminado registrando un movimiento
     * INGRESO_PRODUCCION (el stock de un producto es la suma de sus
     * movimientos; no existe una tabla de saldo).
     */
    function fixture_ingreso_producto(int $productoId, float $cantidad, int $userId, ?int $loteId = null, ?string $observacion = null): ProductoMovimientoModel
    {
        return ProductoMovimientoModel::create([
            'producto_id' => $productoId,
            'tipo' => 'INGRESO_PRODUCCION',
            'cantidad' => $cantidad,
            'lote_produccion_id' => $loteId,
            'fecha' => now()->format('Y-m-d H:i:s'),
            'observacion' => $observacion,
            'registrado_por_user_id' => $userId,
        ]);
    }
}

if (! function_exists('fixture_leche')) {
    /**
     * El insumo del sistema LECHE (lo crea la migración).
     */
    function fixture_leche(): InsumoModel
    {
        return InsumoModel::where('codigo', 'LECHE')->firstOrFail();
    }
}

if (! function_exists('fixture_stock_leche')) {
    /**
     * Sube el stock de leche registrando un movimiento real de acopio.
     */
    function fixture_stock_leche(float $litros, int $userId): void
    {
        app(RegistrarMovimientoInsumo::class)->execute(
            insumoId: fixture_leche()->id,
            motivo: MotivoMovimientoInsumo::AJUSTE_ENTRADA,
            direccion: 'ENTRADA',
            cantidad: $litros,
            registradoPorUserId: $userId,
            observacion: 'Stock de prueba',
        );
    }
}

if (! function_exists('fixture_insumo')) {
    /**
     * Crea la ficha del insumo por el caso de uso real (nace en 0) y, si se pide stock,
     * lo deja respaldado por un movimiento de ajuste, como haría un conteo físico.
     */
    function fixture_insumo(string $nombre, string $unidadBase = 'kg', float $stock = 0.0, float $minimo = 0.0, ?int $userId = null): Insumo
    {
        $insumo = app(RegistrarInsumo::class)->execute(
            nombre: $nombre,
            unidadBase: $unidadBase,
            stockMinimo: $minimo,
        );

        if ($stock <= 0.0) {
            return $insumo;
        }

        app(RegistrarMovimientoInsumo::class)->execute(
            insumoId: (int) $insumo->id(),
            motivo: MotivoMovimientoInsumo::AJUSTE_ENTRADA,
            direccion: 'ENTRADA',
            cantidad: $stock,
            registradoPorUserId: $userId ?? fixture_usuario('ADMINISTRADOR')->id,
            observacion: 'Stock de prueba',
        );

        return app(InsumoRepository::class)->buscarPorId((int) $insumo->id()) ?? $insumo;
    }
}

if (! function_exists('fixture_producto')) {
    function fixture_producto(string $nombre, string $unidad = 'unidad'): ProductoModel
    {
        return ProductoModel::create(['nombre' => $nombre, 'unidad_medida' => $unidad, 'estado' => 'ACTIVO']);
    }
}

if (! function_exists('fixture_receta')) {
    /**
     * Crea y activa una receta por el caso de uso real.
     *
     * @param  list<array{insumo_id: int, cantidad: float, unidad: string}>  $ingredientes
     */
    function fixture_receta(int $productoId, array $ingredientes, int $userId, float $rendimiento = 1.0, string $unidadRendimiento = 'unidad'): Receta
    {
        return app(CrearReceta::class)->execute(
            productoId: $productoId,
            descripcion: null,
            ingredientes: $ingredientes,
            creadaPorUserId: $userId,
            rendimientoCantidad: $rendimiento,
            rendimientoUnidad: $unidadRendimiento,
        );
    }
}

if (! function_exists('fixture_lote')) {
    /**
     * Un lote de producción ya existente con su consumo de leche registrado
     * (sirve para probar pantallas e historiales sin recorrer todo el flujo).
     */
    function fixture_lote(float $litrosLeche, int $userId, string $estado = 'EN_PROCESO', ?DateTimeImmutable $inicio = null): LoteProduccion
    {
        $lote = LoteProduccionModel::create([
            'codigo' => 'PENDIENTE-'.uniqid(),
            'fecha_hora_inicio' => ($inicio ?? new DateTimeImmutable)->format('Y-m-d H:i:s'),
            'fecha_hora_fin' => $estado === 'FINALIZADO' ? now()->format('Y-m-d H:i:s') : null,
            'estado' => $estado,
            'registrado_por_user_id' => $userId,
        ]);
        $lote->update(['codigo' => sprintf('LOT-%05d', $lote->id)]);

        if ($litrosLeche > 0) {
            LoteProduccionInsumoModel::create([
                'lote_produccion_id' => $lote->id,
                'insumo_id' => fixture_leche()->id,
                'cantidad_requerida' => $litrosLeche,
                'cantidad_usada' => $litrosLeche,
                'unidad_base' => 'L',
            ]);
        }

        return app(LoteProduccionRepository::class)->buscarPorId($lote->id);
    }
}

if (! function_exists('fixture_categoria_id')) {
    /**
     * Id de una categoría de producto. Quesos, Yogures y Mantequillas las crea
     * la migración de categorias_productos; cualquier otra se crea aquí.
     */
    function fixture_categoria_id(string $nombre = 'Quesos'): int
    {
        return CategoriaProductoModel::firstOrCreate(
            ['nombre_normalizado' => CategoriaProductoModel::normalizar($nombre)],
            ['nombre' => $nombre, 'estado' => 'ACTIVA'],
        )->id;
    }
}
