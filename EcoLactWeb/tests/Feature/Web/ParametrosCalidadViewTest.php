<?php

use App\Infrastructure\Persistence\Models\ParametroCalidadModel;
use App\Infrastructure\Persistence\Models\ReglaCalidadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function parametroConDescripcion(string $codigo, string $nombre, ?string $descripcion = null, string $estado = 'ACTIVO'): ParametroCalidadModel
{
    return ParametroCalidadModel::create([
        'codigo' => $codigo,
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'unidad' => 'g/ml',
        'estado' => $estado,
    ]);
}

it('la tabla muestra la regla actual como rango, sin código ni conteo de reglas', function () {
    $densidad = parametroConDescripcion('DENSIDAD', 'Densidad', 'Mide la densidad de la leche.');
    fixture_regla_calidad($densidad->id, 1022, 1035, 'RECHAZADA');
    $grasa = ParametroCalidadModel::create(['codigo' => 'GRASA', 'nombre' => 'Grasa', 'unidad' => '%', 'estado' => 'ACTIVO']);
    fixture_regla_calidad($grasa->id, 2, 7, 'OBSERVADA');
    parametroConDescripcion('TEMPERATURA', 'Temperatura');

    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.parametros-calidad.index'));

    $response->assertOk()
        ->assertSee('Regla actual')
        ->assertSeeInOrder(['Densidad', '1022 – 1035', 'Grasa', '2 – 7 %', 'Temperatura', 'Sin configurar'])
        ->assertSee('Selecciona un parámetro');

    preg_match('/<table>.*?<\/table>/s', $response->getContent(), $tabla);
    expect($tabla[0])->not->toContain('DENSIDAD')->not->toContain('Código')->not->toContain('1 regla')->not->toContain('Prioridad');
});

it('la tabla solo selecciona: sin columna Acción, sin lápiz ni menú; editar vive solo en el panel', function () {
    $densidad = parametroConDescripcion('DENSIDAD', 'Densidad');
    fixture_regla_calidad($densidad->id, 1022, 1035, 'RECHAZADA');

    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.parametros-calidad.index'));

    preg_match('/<table>.*?<\/table>/s', $response->getContent(), $tabla);
    expect($tabla[0])
        ->toContain('title="Ver detalle"')
        ->not->toContain('Acción')
        ->not->toContain('Editar')
        ->not->toContain('data-modal-open')
        ->not->toContain('Desactivar');

    $panel = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.parametros-calidad.panel', $densidad))->getContent();
    expect(substr_count($panel, 'data-modal-open="modal-editar-parametro-'.$densidad->id.'"'))->toBe(1)
        ->and(substr_count($panel, 'data-modal-open="modal-regla-'.$densidad->id.'"'))->toBe(1)
        ->and(strpos($panel, 'Editar parámetro'))->toBeLessThan(strpos($panel, 'Regla de evaluación'))
        ->and(strpos($panel, 'Editar regla'))->toBeGreaterThan(strpos($panel, 'Regla de evaluación'));
});

it('la regla actual es la vigente según el dominio, no la última por id; el historial se conserva', function () {
    $densidad = parametroConDescripcion('DENSIDAD', 'Densidad');
    fixture_regla_calidad($densidad->id, 1028, 1034, 'RECHAZADA', new DateTimeImmutable('2026-01-01'));
    fixture_regla_calidad($densidad->id, 1000, 1100, 'OBSERVADA', new DateTimeImmutable('2026-01-01'), prioridad: 2);
    fixture_regla_calidad($densidad->id, 1, 2, 'OBSERVADA', new DateTimeImmutable('2099-01-01'));
    fixture_regla_calidad($densidad->id, 3, 4, 'OBSERVADA')->update(['estado' => 'INACTIVO']);

    $this->actingAs(fixture_usuario('ADMINISTRADOR'))
        ->get(route('admin.parametros-calidad.index'))
        ->assertSee('1028 – 1034')
        ->assertDontSee('1000 – 1100')
        ->assertDontSee('1 – 2')
        ->assertDontSee('3 – 4');

    expect(ReglaCalidadModel::where('parametro_id', $densidad->id)->count())->toBe(4);
});

it('el panel muestra información general y la regla actual en bloques, sin tabla de reglas ni accesos redundantes', function () {
    $densidad = parametroConDescripcion('DENSIDAD', 'Densidad');
    fixture_regla_calidad($densidad->id, 1022, 1035, 'RECHAZADA');
    $grasa = parametroConDescripcion('GRASA', 'Grasa');
    fixture_regla_calidad($grasa->id, 3.2, null, 'OBSERVADA');

    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))
        ->get(route('admin.parametros-calidad.panel', $densidad));

    $response->assertOk()
        ->assertSee('DENSIDAD')
        ->assertSee('Regla de evaluación')
        ->assertSee('1022 – 1035')
        ->assertSee('Rechazada')
        ->assertSee('01/01/2026 – Sin fecha fin')
        ->assertSee('Editar parámetro')
        ->assertSee('Editar regla de calidad')
        ->assertDontSee('≥ 3.2')
        ->assertDontSee('Nueva regla')
        ->assertDontSee('Gestionar reglas')
        ->assertDontSee('Configurar regla')
        ->assertDontSee('name="prioridad"', false)
        ->assertDontSee(route('admin.parametros-calidad.reglas-calidad.index', $densidad->id));
});

it('el panel de un parámetro sin regla indica Sin configurar y ofrece Configurar regla', function () {
    $parametro = parametroConDescripcion('ACIDEZ', 'Acidez');

    $this->actingAs(fixture_usuario('ADMINISTRADOR'))
        ->get(route('admin.parametros-calidad.panel', $parametro))
        ->assertOk()
        ->assertSee('Sin configurar')
        ->assertSee('todavía no tiene un rango de evaluación definido')
        ->assertSee('Configurar regla de calidad')
        ->assertSee('Guardar regla')
        ->assertDontSee('Editar regla');
});

it('si la única regla venció, el panel la trata como sin configurar y no la ofrece para editar', function () {
    $parametro = parametroConDescripcion('ACIDEZ', 'Acidez');
    fixture_regla_calidad($parametro->id, 13, 18, 'OBSERVADA')->update(['fecha_fin' => '2026-02-01']);

    $this->actingAs(fixture_usuario('ADMINISTRADOR'))
        ->get(route('admin.parametros-calidad.panel', $parametro))
        ->assertOk()
        ->assertSee('Sin configurar')
        ->assertSee('Su regla anterior ya no está vigente')
        ->assertSee('Configurar regla')
        ->assertDontSee('13 – 18');
});

it('configurar regla crea la regla del parámetro con la prioridad por defecto, sin enviarla', function () {
    $parametro = parametroConDescripcion('DENSIDAD', 'Densidad');
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)
        ->from(route('admin.parametros-calidad.index', ['parametro' => $parametro->id]))
        ->post(route('admin.reglas-calidad.store'), [
            'parametro_id' => $parametro->id,
            'valor_minimo' => 1022,
            'valor_maximo' => 1035,
            'resultado_incumplimiento' => 'RECHAZADA',
            'fecha_inicio' => now()->toDateString(),
            'estado' => 'ACTIVO',
        ])
        ->assertRedirect(route('admin.parametros-calidad.index', ['parametro' => $parametro->id]))
        ->assertSessionHas('success');

    expect(ReglaCalidadModel::where('parametro_id', $parametro->id)->sole()->prioridad)->toBe(1);

    $this->actingAs($admin)
        ->get(route('admin.parametros-calidad.panel', $parametro))
        ->assertSee('1022 – 1035');
});

it('editar regla cambia el rango de la regla actual y conserva su prioridad', function () {
    $parametro = parametroConDescripcion('DENSIDAD', 'Densidad');
    $regla = fixture_regla_calidad($parametro->id, 1022, 1035, 'RECHAZADA', prioridad: 3);

    $this->actingAs(fixture_usuario('ADMINISTRADOR'))
        ->patch(route('admin.reglas-calidad.update', $regla), [
            'valor_minimo' => 1025,
            'valor_maximo' => 1034,
            'resultado_incumplimiento' => 'OBSERVADA',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '',
            'estado' => 'ACTIVO',
        ])
        ->assertSessionHas('success');

    expect($regla->fresh()->only(['valor_minimo', 'valor_maximo', 'resultado_incumplimiento', 'prioridad']))->toBe([
        'valor_minimo' => '1025.0000',
        'valor_maximo' => '1034.0000',
        'resultado_incumplimiento' => 'OBSERVADA',
        'prioridad' => 3,
    ]);
});

it('la ruta show ya no es una página aparte: redirige al listado con el parámetro seleccionado', function () {
    $parametro = parametroConDescripcion('ACIDEZ', 'Acidez');

    $this->actingAs(fixture_usuario('ADMINISTRADOR'))
        ->get(route('admin.parametros-calidad.show', $parametro))
        ->assertRedirect(route('admin.parametros-calidad.index', ['parametro' => $parametro->id]));
});

it('crea un parámetro con descripción y deja seleccionado el nuevo registro', function () {
    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->post(route('admin.parametros-calidad.store'), [
        'nombre' => 'Grasa láctea',
        'codigo' => 'GRASA_LACTEA',
        'unidad' => '%',
        'estado' => 'ACTIVO',
        'descripcion' => 'Contenido de grasa.',
    ]);

    $parametro = ParametroCalidadModel::where('codigo', 'GRASA_LACTEA')->firstOrFail();
    $response->assertRedirect(route('admin.parametros-calidad.index', ['parametro' => $parametro->id]));
    expect($parametro->descripcion)->toBe('Contenido de grasa.');
});

it('rechaza códigos fuera del formato A-Z, 0-9 y _', function (string $codigo) {
    $this->actingAs(fixture_usuario('ADMINISTRADOR'))
        ->post(route('admin.parametros-calidad.store'), [
            'nombre' => 'Grasa láctea',
            'codigo' => $codigo,
            'unidad' => '%',
            'estado' => 'ACTIVO',
        ])
        ->assertSessionHasErrors('codigo');

    expect(ParametroCalidadModel::count())->toBe(0);
})->with(['minúsculas' => 'grasa', 'con espacio' => 'GRASA LACTEA', 'con tilde' => 'PROTEÍNA']);

it('editar cambia nombre, unidad y descripción pero nunca el código ni las reglas', function () {
    $parametro = parametroConDescripcion('DENSIDAD', 'Densidad', 'Antes');
    $regla = fixture_regla_calidad($parametro->id, 1.028, 1.034, 'RECHAZADA');

    $this->actingAs(fixture_usuario('ADMINISTRADOR'))
        ->patch(route('admin.parametros-calidad.update', $parametro), [
            'nombre' => 'Densidad relativa',
            'codigo' => 'OTRO',
            'unidad' => 'g/mL',
            'descripcion' => 'Mide la densidad de la leche.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($parametro->fresh()->only(['codigo', 'nombre', 'unidad', 'descripcion']))->toBe([
        'codigo' => 'DENSIDAD',
        'nombre' => 'Densidad relativa',
        'unidad' => 'g/mL',
        'descripcion' => 'Mide la densidad de la leche.',
    ]);
    expect(ReglaCalidadModel::where('parametro_id', $parametro->id)->pluck('id')->all())->toBe([$regla->id]);
});

it('cambiar el estado del parámetro solo cambia el estado', function () {
    $parametro = parametroConDescripcion('ACIDEZ', 'Acidez', 'Se conserva', 'INACTIVO');
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->patch(route('admin.parametros-calidad.update', $parametro), ['estado' => 'ACTIVO'])->assertSessionHas('success');
    expect($parametro->fresh()->only(['estado', 'descripcion', 'unidad']))->toBe(['estado' => 'ACTIVO', 'descripcion' => 'Se conserva', 'unidad' => 'g/ml']);

    $this->actingAs($admin)->patch(route('admin.parametros-calidad.update', $parametro), ['estado' => 'INACTIVO'])->assertSessionHas('success');
    expect($parametro->fresh()->estado)->toBe('INACTIVO');
});

it('desactivar un parámetro con reglas activas vuelve con el mensaje del dominio en vez de fallar', function () {
    $parametro = parametroConDescripcion('DENSIDAD', 'Densidad');
    fixture_regla_calidad($parametro->id, 1.028, 1.034, 'RECHAZADA');

    $this->actingAs(fixture_usuario('ADMINISTRADOR'))
        ->from(route('admin.parametros-calidad.index', ['parametro' => $parametro->id]))
        ->patch(route('admin.parametros-calidad.update', $parametro), ['estado' => 'INACTIVO'])
        ->assertRedirect(route('admin.parametros-calidad.index', ['parametro' => $parametro->id]))
        ->assertSessionHas('error', 'No se puede desactivar el parámetro: tiene reglas de calidad activas asociadas. Desactívelas primero.');

    expect($parametro->fresh()->estado)->toBe('ACTIVO');
});

it('un acopiador no puede ver el panel de un parámetro', function () {
    $parametro = parametroConDescripcion('DENSIDAD', 'Densidad');
    $acopiador = fixture_acopiador(fixture_zona());

    $this->actingAs($acopiador->user)
        ->get(route('admin.parametros-calidad.panel', $parametro))
        ->assertForbidden();
});
