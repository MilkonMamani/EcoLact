<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use App\Infrastructure\Persistence\Models\EncargadoPlantaModel;
use App\Infrastructure\Persistence\Models\PersonalVentasModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "Trabajadores → Nuevo trabajador": un solo formulario para dar de alta a
 * cualquier tipo de personal. No registra nada por su cuenta: el formulario
 * se envía al alta que ya existe para el tipo elegido (acopiadores.store,
 * analistas-calidad.store, …), así la validación, los Use Cases, la
 * transacción y los permisos siguen siendo los de cada módulo. La fecha de
 * ingreso la fija el sistema (Reloj) al registrar; aquí no se pide.
 *
 * Solo el ACOPIADOR tiene zona (acopiadores.zona_id, obligatoria): los demás
 * roles no la tienen en su tabla, así que no se les muestra.
 */
final class NuevoTrabajadorController extends Controller
{
    /**
     * @var array<string, array{etiqueta: string, plural: string, descripcion: string, detalle: string, icono: string, tono: string, modelo: class-string, ruta: string, listado: string, conZona: bool}>
     */
    public const TIPOS = [
        'acopiador' => [
            'etiqueta' => 'Acopiador',
            'plural' => 'Acopiadores',
            'descripcion' => 'Recoge y registra la leche en una zona.',
            'detalle' => 'Registra la leche en una zona asignada.',
            'icono' => 'milk-jar',
            'tono' => 'verde',
            'modelo' => AcopiadorModel::class,
            'ruta' => 'admin.acopiadores.store',
            'listado' => 'admin.acopiadores.index',
            'conZona' => true,
        ],
        'analista' => [
            'etiqueta' => 'Analista de calidad',
            'plural' => 'Analistas de calidad',
            'descripcion' => 'Analiza la calidad de las entregas.',
            'detalle' => 'Analiza la calidad de las entregas.',
            'icono' => 'beaker',
            'tono' => 'morado',
            'modelo' => AnalistaCalidadModel::class,
            'ruta' => 'admin.analistas-calidad.store',
            'listado' => 'admin.analistas-calidad.index',
            'conZona' => false,
        ],
        'encargado-planta' => [
            'etiqueta' => 'Encargado de planta',
            'plural' => 'Encargados de planta',
            'descripcion' => 'Gestiona producción e insumos en la planta.',
            'detalle' => 'Gestiona producción e insumos.',
            'icono' => 'factory',
            'tono' => 'azul',
            'modelo' => EncargadoPlantaModel::class,
            'ruta' => 'admin.encargados-planta.store',
            'listado' => 'admin.encargados-planta.index',
            'conZona' => false,
        ],
        'ventas' => [
            'etiqueta' => 'Personal de ventas',
            'plural' => 'Personal de ventas',
            'descripcion' => 'Vende los productos de la planta.',
            'detalle' => 'Gestiona la venta de productos.',
            'icono' => 'shopping-cart',
            'tono' => 'ambar',
            'modelo' => PersonalVentasModel::class,
            'ruta' => 'admin.personal-ventas.store',
            'listado' => 'admin.personal-ventas.index',
            'conZona' => false,
        ],
    ];

    public function __invoke(Request $request): View
    {
        $tipo = array_key_exists($request->string('tipo')->toString(), self::TIPOS)
            ? $request->string('tipo')->toString()
            : 'acopiador';

        Gate::authorize('create', self::TIPOS[$tipo]['modelo']);

        $anterior = url()->previous();
        $volver = str_starts_with($anterior, url('/')) && ! str_contains($anterior, '/trabajadores/nuevo')
            ? $anterior
            : route('admin.dashboard');

        return view('admin.trabajadores.nuevo', [
            'tipos' => self::TIPOS,
            'tipo' => $tipo,
            // Las zonas se cargan siempre: al elegir "Acopiador" el campo aparece sin recargar.
            'zonas' => ZonaModel::where('estado', 'ACTIVO')->orderBy('nombre')->pluck('nombre', 'id')->all(),
            'volver' => $volver,
        ]);
    }
}
