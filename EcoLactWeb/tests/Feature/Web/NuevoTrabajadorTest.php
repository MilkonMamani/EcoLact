<?php

use App\Application\Pago\GenerarObligacionesPersonal;
use App\Application\Pago\RegistrarAsistenciaQr;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('el menú agrupa a los trabajadores y "Nuevo trabajador" abre el formulario único', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertSee(route('admin.trabajadores.create'), false);

    $this->get(route('admin.trabajadores.create'))
        ->assertOk()
        ->assertSeeInOrder(['Acopiador', 'Analista de calidad', 'Encargado de planta', 'Personal de ventas'])
        ->assertSee('action="'.route('admin.acopiadores.store').'"', false)
        ->assertSee('name="zona_id"', false);
});

it('cada tipo envía al alta que ya existe para su rol y solo el acopiador pide zona', function (string $tipo, string $rutaStore) {
    $admin = fixture_usuario('ADMINISTRADOR');

    $html = $this->actingAs($admin)->get(route('admin.trabajadores.create', ['tipo' => $tipo]))
        ->assertOk()
        ->assertSee('<form method="POST" action="'.route($rutaStore).'"', false)
        ->getContent();

    // Zona existe para cambiar de rol sin recargar, pero oculta y deshabilitada: no se muestra ni se envía.
    $campoZona = substr($html, strpos($html, 'data-campo="zona"') - 40, 4000);
    expect($campoZona)->toMatch('/data-campo="zona"\s+hidden\s*>/')->toMatch('/<select[^>]*name="zona_id"[^>]*disabled/');
})->with([
    ['analista', 'admin.analistas-calidad.store'],
    ['encargado-planta', 'admin.encargados-planta.store'],
    ['ventas', 'admin.personal-ventas.store'],
]);

it('el rediseño muestra encabezado, tarjetas de rol, secciones con íconos, placeholders y panel lateral', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_zona('Collana 1');

    $this->actingAs($admin)->get(route('admin.trabajadores.create'))
        ->assertOk()
        ->assertSeeInOrder(['Personal', 'Trabajadores', 'Nuevo trabajador', 'Volver', 'Ver lista de trabajadores', 'Nuevo trabajador', 'Registra un nuevo integrante del equipo de EcoLact.'])
        ->assertSeeInOrder(['Acopiador', 'Recoge y registra la leche en una zona.', 'Analista de calidad', 'Encargado de planta', 'Personal de ventas'])
        ->assertSeeInOrder(['Información personal', 'Completa los datos del trabajador.', 'Cuenta de acceso', 'Este usuario podrá acceder al sistema con estas credenciales.'])
        ->assertSee('placeholder="Ej. Juan Carlos"', false)
        ->assertSee('placeholder="Ej. 12345678"', false)
        ->assertSee('placeholder="Ej. juan.quispe"', false)
        ->assertSee('data-ver-clave', false)
        ->assertSee('Collana 1')
        ->assertSee('Registrar trabajador')
        ->assertDontSee('Registrar acopiador')
        ->assertDontSee('name="fecha_ingreso"', false)
        ->assertSeeInOrder(['Información importante', 'Roles del sistema', 'Registra la leche en una zona asignada.', 'Gestiona la venta de productos.'])
        // "Ver lista de trabajadores" enlaza a las listas reales de cada rol.
        ->assertSee(route('admin.acopiadores.index'), false)
        ->assertSee(route('admin.personal-ventas.index'), false);
});

it('el acopiador exige zona y los demás roles la ignoran aunque se envíe', function () {
    fixture_rol('ACOPIADOR');
    fixture_rol('ENCARGADO_PLANTA');
    $admin = fixture_usuario('ADMINISTRADOR');
    $datos = ['nombres' => 'Juan', 'apellidos' => 'Quispe', 'telefono' => '987654321', 'password' => 'Secreto#123'];

    $this->actingAs($admin)->post(route('admin.acopiadores.store'), $datos + ['dni' => '70000001', 'username' => 'juan.aco'])
        ->assertSessionHasErrors('zona_id');

    $this->post(route('admin.encargados-planta.store'), $datos + ['dni' => '70000002', 'username' => 'juan.planta', 'zona_id' => 999])
        ->assertSessionHasNoErrors();
});

it('registrar desde el formulario único usa el alta real del rol', function () {
    fixture_rol('CALIDAD');
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)
        ->from(route('admin.trabajadores.create', ['tipo' => 'analista']))
        ->post(route('admin.analistas-calidad.store'), [
            'dni' => '45678912', 'nombres' => 'Carla', 'apellidos' => 'Mamani',
            'username' => 'carla.mamani', 'password' => 'Secreto#123',
        ])
        ->assertRedirect(route('admin.trabajadores.create', ['tipo' => 'analista']))
        ->assertSessionHas('success');

    expect(AnalistaCalidadModel::where('dni', '45678912')->exists())->toBeTrue();
});

it('solo el administrador ve el formulario de nuevo trabajador', function () {
    $acopiador = fixture_acopiador(fixture_zona());

    $this->actingAs($acopiador->user)->get(route('admin.trabajadores.create'))->assertForbidden();
});

it('el QR de asistencia del administrador no registra asistencia', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('ADMINISTRADOR', 1500, '2026-09-01', $admin);
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable);
    $identidad = TrabajadorModel::where('user_id', $admin->id)->firstOrFail();

    expect(fn () => app(RegistrarAsistenciaQr::class)->execute($identidad->qr_token, $admin->id))
        ->toThrow(DomainException::class, 'El administrador no marca asistencia: cobra sueldo fijo mensual.');
});
