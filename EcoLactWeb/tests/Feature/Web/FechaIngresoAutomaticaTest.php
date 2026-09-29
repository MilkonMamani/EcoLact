<?php

use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\AcopiadorZonaHistorialModel;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use App\Infrastructure\Persistence\Models\EncargadoPlantaModel;
use App\Infrastructure\Persistence\Models\PersonalVentasModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/** @return array<string, string> */
function datos_trabajador(string $dni, string $usuario): array
{
    return [
        'dni' => $dni, 'nombres' => 'Rusman', 'apellidos' => 'Torres', 'telefono' => '963258741',
        'username' => $usuario, 'password' => 'Secreto#123',
    ];
}

it('registra al trabajador sin pedir fecha y le asigna hoy en hora de Perú', function (string $rol, string $ruta, string $modelo) {
    // 03:00 UTC del 28/09 = 22:00 del 27/09 en Lima: en UTC ya sería "mañana".
    $this->travelTo('2026-09-28 03:00:00');
    fixture_rol($rol);
    $admin = fixture_usuario('ADMINISTRADOR');
    $datos = datos_trabajador('71112222', 'nuevo.trabajador') + ($rol === 'ACOPIADOR' ? ['zona_id' => fixture_zona('Collana 1')->id] : []);

    $this->actingAs($admin)->post(route($ruta), $datos)->assertSessionHasNoErrors()->assertSessionHas('success');

    expect($modelo::where('dni', '71112222')->firstOrFail()->fecha_ingreso->format('Y-m-d'))->toBe('2026-09-27');
})->with([
    ['ACOPIADOR', 'admin.acopiadores.store', AcopiadorModel::class],
    ['CALIDAD', 'admin.analistas-calidad.store', AnalistaCalidadModel::class],
    ['ENCARGADO_PLANTA', 'admin.encargados-planta.store', EncargadoPlantaModel::class],
    ['VENTAS', 'admin.personal-ventas.store', PersonalVentasModel::class],
]);

it('el historial de zona inicial del acopiador empieza el mismo día de ingreso', function () {
    $this->travelTo('2026-09-28 03:00:00');
    fixture_rol('ACOPIADOR');
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');

    $this->actingAs($admin)->post(route('admin.acopiadores.store'), datos_trabajador('71113333', 'aco.nuevo') + ['zona_id' => $zona->id]);

    $acopiador = AcopiadorModel::where('dni', '71113333')->firstOrFail();
    expect(AcopiadorZonaHistorialModel::where('acopiador_id', $acopiador->id)->value('fecha_inicio')->format('Y-m-d'))->toBe('2026-09-27');
});

it('rechaza una fecha de ingreso enviada a mano al registrar', function () {
    fixture_rol('CALIDAD');
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)
        ->post(route('admin.analistas-calidad.store'), datos_trabajador('71114444', 'ana.mano') + ['fecha_ingreso' => '2020-01-01'])
        ->assertSessionHasErrors('fecha_ingreso');

    expect(AnalistaCalidadModel::where('dni', '71114444')->exists())->toBeFalse();
});

it('la fecha de ingreso y el DNI del acopiador no se pueden modificar al editar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = fixture_acopiador(fixture_zona('Collana 1'), '71115555');
    $acopiador->update(['fecha_ingreso' => '2026-09-01']);

    $this->actingAs($admin)
        ->patch(route('admin.acopiadores.update', $acopiador->id), ['nombres' => 'Rusman', 'fecha_ingreso' => '2020-01-01', 'dni' => '99999999'])
        ->assertSessionHasErrors(['fecha_ingreso', 'dni']);

    expect($acopiador->fresh())
        ->dni->toBe('71115555')
        ->and($acopiador->fresh()->fecha_ingreso->format('Y-m-d'))->toBe('2026-09-01');
});

it('editar información personal actualiza nombres, apellidos y teléfono', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = fixture_acopiador(fixture_zona('Collana 1'));

    $this->actingAs($admin)
        ->patch(route('admin.acopiadores.update', $acopiador->id), ['nombres' => 'Rusman', 'apellidos' => 'Torres', 'telefono' => '963258741'])
        ->assertSessionHasNoErrors();

    expect($acopiador->fresh())->nombres->toBe('Rusman')->apellidos->toBe('Torres')->telefono->toBe('963258741');
});

it('valida nombres obligatorios y el formato del teléfono', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = fixture_acopiador(fixture_zona('Collana 1'));

    $this->actingAs($admin)
        ->patch(route('admin.acopiadores.update', $acopiador->id), ['nombres' => '', 'apellidos' => 'Torres', 'telefono' => 'abc'])
        ->assertSessionHasErrors(['nombres', 'telefono']);
});

it('el modal personal no tiene fecha, DNI ni zona; la zona tiene su propio modal; el panel muestra la fecha como lectura', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = fixture_acopiador(fixture_zona('Collana 1'));
    $acopiador->update(['fecha_ingreso' => '2026-09-27']);

    $html = $this->actingAs($admin)->get(route('admin.acopiadores.panel', $acopiador->id))->assertOk()->getContent();
    $bloque = function (string $id) use ($html): string {
        $inicio = strpos($html, 'id="'.$id.'"');

        return substr($html, $inicio, strpos($html, '</form>', $inicio) - $inicio);
    };

    expect($bloque('modal-editar-acopiador-'.$acopiador->id))
        ->toContain('Editar información personal')
        ->toContain('name="nombres"')->toContain('name="apellidos"')->toContain('name="telefono"')
        ->toContain('El DNI es el identificador del acopiador y no se edita.')
        ->not->toContain('name="dni"')->not->toContain('name="fecha_ingreso"')->not->toContain('name="zona_id"');
    expect($bloque('modal-zona-acopiador-'.$acopiador->id))
        ->toContain('Editar zona')->toContain('name="zona_id"')->not->toContain('name="nombres"');
    expect($html)->toMatch('#<dt>Fecha de ingreso</dt><dd[^>]*>27/09/2026</dd>#')->not->toContain('type="date"');
});

it('ningún formulario de alta pide la fecha de ingreso', function (string $ruta) {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->get($ruta)->assertOk()->assertDontSee('name="fecha_ingreso"', false);
})->with([
    fn () => route('admin.trabajadores.create', ['tipo' => 'acopiador']),
    fn () => route('admin.trabajadores.create', ['tipo' => 'analista']),
    fn () => route('admin.acopiadores.index'),
    fn () => route('admin.analistas-calidad.index'),
    fn () => route('admin.encargados-planta.index'),
    fn () => route('admin.personal-ventas.index'),
]);
