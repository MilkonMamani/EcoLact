<?php

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Infrastructure\Persistence\Models\RutaDetalleModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function asignacionValida(int $zonaId, int $rutaId, array $extra = []): array
{
    return array_merge([
        'zona_id' => $zonaId,
        'ruta_id' => $rutaId,
        'latitud' => -15.8402,
        'longitud' => -70.0219,
        'referencia_ubicacion' => 'Frente a la escuela',
    ], $extra);
}

// ---------------------------------------------------------------- listado

it('el panel lista las zonas desde la base de datos, resalta la seleccionada y muestra su tabla de proveedores', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $collana1 = fixture_zona('Collana 1');
    fixture_zona('Collana 2');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $collana1->id, nombre: 'Ruta 01');
    $sandra = fixture_proveedor($collana1, '72100012');
    $sandra->update(['nombres' => 'Sandra', 'apellidos' => 'Ccama Quispe']);
    $wilson = fixture_proveedor($collana1, '72100013');
    $wilson->update(['nombres' => 'Wilson', 'apellidos' => 'Poma Mamani']);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $sandra->id, latitud: -15.8, longitud: -70.0);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $wilson->id);

    $response = $this->actingAs($admin)->get(route('admin.zonas.index', ['zona' => $collana1->id]));

    $response->assertOk()
        ->assertSee('Collana 1')
        ->assertSee('Collana 2')
        ->assertSee('Buscar zona por nombre o descripción...')
        ->assertSee('Buscar proveedor por nombre o DNI...')
        ->assertSeeInOrder(['Resumen', 'Proveedores', 'Acopiadores'])
        ->assertSeeInOrder(['Sandra Ccama Quispe', '72100012', 'Registrada'])
        ->assertSeeInOrder(['Wilson Poma Mamani', '72100013', 'Sin ubicación'])
        ->assertDontSee('Proveedores sin zona');
    $response->assertSee('data-ficha-id="'.$collana1->id.'" data-url-panel', false)
        ->assertSee('is-selected', false);
    // "Ruta 01" sigue en el JSON del catálogo zona→ruta que usa el modal de
    // asignación (data-catalogo); lo que ya no debe verse es la card de
    // rutas ni su navegación a "Recorridos".
    $response->assertDontSee('Recorridos y detalle de la ruta');
});

it('sin parámetro selecciona la primera zona y una zona inexistente cae a la primera', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $primera = fixture_zona('Aaa Primera');
    fixture_zona('Bbb Segunda');

    $this->actingAs($admin)->get(route('admin.zonas.index'))
        ->assertOk()->assertSee('data-fichas-contenido data-ficha-id="'.$primera->id.'"', false);
    $this->actingAs($admin)->get(route('admin.zonas.index', ['zona' => 9999]))
        ->assertOk()->assertSee('data-fichas-contenido data-ficha-id="'.$primera->id.'"', false);
});

it('el aviso "Proveedores sin zona" va aparte de la tabla y abre la lista con el lápiz para asignarlos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $libre = fixture_proveedor($zona, '72100077');
    $libre->update(['nombres' => 'Diana', 'apellidos' => 'Choque']);

    $this->actingAs($admin)->get(route('admin.zonas.index'))
        ->assertSeeInOrder(['Proveedores sin zona', '1 proveedor pendiente de asignación.', 'Ver proveedores'])
        ->assertSee(e(route('admin.zonas.index', ['zona' => 'sin-asignar'])), false);

    $response = $this->actingAs($admin)->get(route('admin.zonas.index', ['zona' => 'sin-asignar']));

    $response->assertOk()
        ->assertSeeInOrder(['Proveedores sin zona', 'Diana Choque', '72100077', 'Sin ubicación'])
        ->assertSee('Asignar a zona a');
});

it('el fragmento del panel de una zona se sirve sin layout', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');

    $this->actingAs($admin)->get(route('admin.zonas.panel', $zona->id))
        ->assertOk()
        ->assertSee('Información de la zona')
        ->assertDontSee('<html', false);
    $this->actingAs($admin)->get(route('admin.zonas.panel.sin-asignar'))
        ->assertOk()
        ->assertSee('Proveedores sin zona')
        ->assertDontSee('<html', false);
});

it('una zona sin proveedores muestra un estado vacío amigable', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');

    $this->actingAs($admin)->get(route('admin.zonas.panel', $zona->id))
        ->assertSee('Esta zona aún no tiene proveedores');
});

// -------------------------------------------------------------------- mapa

it('el mapa de la zona indica cuántos proveedores tienen ubicación registrada', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $conUbicacion = fixture_proveedor($zona);
    $sinUbicacion = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $conUbicacion->id, latitud: -15.8, longitud: -70.0);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $sinUbicacion->id);

    $this->actingAs($admin)->get(route('admin.zonas.panel', $zona->id))
        ->assertSeeInOrder(['Con coordenadas', '1', 'Sin coordenadas', '1']);
});

it('una zona sin proveedores con coordenadas muestra el estado vacío del mapa', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: fixture_proveedor($zona)->id);

    $this->actingAs($admin)->get(route('admin.zonas.panel', $zona->id))
        ->assertSee('Sin ubicaciones registradas');
});

// ---------------------------------------------------- alta rápida de ruta

it('crea la ruta principal de una zona sin rutas activas desde el modal de asignación, en JSON', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');

    $respuesta = $this->actingAs($admin)
        ->postJson(route('admin.zonas.rutas.store', $zona->id), ['nombre' => 'Ruta principal', 'estado' => 'ACTIVO']);

    $respuesta->assertOk()->assertJsonPath('ok', true)->assertJsonPath('ruta.nombre', 'Ruta principal');
    $this->assertDatabaseHas('rutas', ['zona_id' => $zona->id, 'nombre' => 'Ruta principal']);
});

it('rechaza en JSON crear una ruta con nombre repetido en la misma zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta principal');

    $this->actingAs($admin)
        ->postJson(route('admin.zonas.rutas.store', $zona->id), ['nombre' => 'Ruta principal', 'estado' => 'ACTIVO'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nombre');
});

// --------------------------------------------------------------- búsqueda

it('la búsqueda devuelve nombre, DNI y la asignación actual para precargar el modal', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $ana = fixture_proveedor($zona, '45678912');
    $ana->update(['nombres' => 'Ana', 'apellidos' => 'Quispe']);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $ana->id, latitud: -15.5, longitud: -70.5, referenciaUbicacion: 'Junto al puente');

    $this->actingAs($admin)->getJson(route('admin.zonas.proveedores.buscar', ['q' => 'ana quis']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.nombre', 'Ana Quispe')
        ->assertJsonPath('0.dni', '45678912')
        ->assertJsonPath('0.zonaId', $zona->id)
        ->assertJsonPath('0.rutaId', $ruta->id())
        ->assertJsonPath('0.latitud', -15.5)
        ->assertJsonPath('0.referencia', 'Junto al puente');

    $this->actingAs($admin)->getJson(route('admin.zonas.proveedores.buscar', ['q' => '45678']))
        ->assertOk()->assertJsonPath('0.dni', '45678912');
    $this->actingAs($admin)->getJson(route('admin.zonas.proveedores.buscar', ['q' => 'nadie']))
        ->assertOk()->assertExactJson([]);
    $this->actingAs($admin)->getJson(route('admin.zonas.proveedores.buscar'))
        ->assertOk()->assertExactJson([]);
});

// -------------------------------------------------------------- CRUD zona

it('crea una zona y deja seleccionada la nueva', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->post(route('admin.zonas.store'), ['nombre' => 'Zona Norte', 'descripcion' => 'Zona de prueba']);

    $zona = ZonaModel::where('nombre', 'Zona Norte')->firstOrFail();
    $response->assertRedirect(route('admin.zonas.index', ['zona' => $zona->id]));
    $this->assertDatabaseHas('zonas', ['nombre' => 'Zona Norte', 'descripcion' => 'Zona de prueba', 'estado' => 'ACTIVO']);
});

it('rechaza crear una zona con nombre duplicado o vacío', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_zona('Zona Norte');

    $this->actingAs($admin)->post(route('admin.zonas.store'), ['nombre' => 'Zona Norte', 'estado' => 'ACTIVO'])
        ->assertSessionHasErrors(['nombre' => 'Ya existe una zona con ese nombre.']);
    $this->actingAs($admin)->post(route('admin.zonas.store'), ['nombre' => '', 'estado' => 'ACTIVO'])
        ->assertSessionHasErrors('nombre');
});

it('edita el nombre de una zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');

    $this->actingAs($admin)->patch(route('admin.zonas.update', $zona->id), ['nombre' => 'Collana Alta', 'estado' => 'ACTIVO'])
        ->assertRedirect(route('admin.zonas.index', ['zona' => $zona->id]));

    $this->assertDatabaseHas('zonas', ['id' => $zona->id, 'nombre' => 'Collana Alta']);
});

it('elimina una zona vacía con confirmación en la interfaz', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Zona vacía');

    $this->actingAs($admin)->get(route('admin.zonas.index'))
        ->assertSee('data-confirm="¿Eliminar la zona Zona vacía?', false);

    $this->actingAs($admin)->delete(route('admin.zonas.destroy', $zona->id))
        ->assertRedirect(route('admin.zonas.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('zonas', ['id' => $zona->id]);
});

it('no elimina una zona con rutas o proveedores y muestra el motivo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    fixture_proveedor($zona);

    $this->actingAs($admin)->delete(route('admin.zonas.destroy', $zona->id))
        ->assertRedirect(route('admin.zonas.index', ['zona' => $zona->id]))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('zonas', ['id' => $zona->id]);
});

// ------------------------------------------------------------- asignación

it('asigna un proveedor a zona y ruta guardando coordenadas y referencia, y el destino queda seleccionado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $proveedor = fixture_proveedor($zona);

    $this->actingAs($admin)
        ->putJson(route('admin.zonas.proveedores.asignacion', $proveedor->id), asignacionValida($zona->id, $ruta->id()))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('redirect', route('admin.zonas.index', ['zona' => $zona->id]));

    $this->assertDatabaseHas('ruta_detalles', [
        'ruta_id' => $ruta->id(),
        'proveedor_id' => $proveedor->id,
        'orden_visita' => 1,
        'referencia_ubicacion' => 'Frente a la escuela',
    ]);
    $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id, 'zona_id' => $zona->id]);
});

it('la ubicación es opcional al asignar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $proveedor = fixture_proveedor($zona);

    $this->actingAs($admin)
        ->putJson(route('admin.zonas.proveedores.asignacion', $proveedor->id), ['zona_id' => $zona->id, 'ruta_id' => $ruta->id()])
        ->assertOk();

    expect(RutaDetalleModel::where('proveedor_id', $proveedor->id)->value('latitud'))->toBeNull();
});

it('editar la asignación actualiza la ubicación del proveedor sin duplicarlo ni cambiar su orden', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $p1 = fixture_proveedor($zona);
    $p2 = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p1->id);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p2->id);

    $this->actingAs($admin)
        ->putJson(route('admin.zonas.proveedores.asignacion', $p2->id), asignacionValida($zona->id, $ruta->id(), ['latitud' => -15.7, 'longitud' => -70.3, 'referencia_ubicacion' => 'Sector Collana Bajo']))
        ->assertOk();

    $detalle = RutaDetalleModel::where('proveedor_id', $p2->id)->first();
    expect($detalle->orden_visita)->toBe(2)
        ->and((float) $detalle->latitud)->toBe(-15.7)
        ->and($detalle->referencia_ubicacion)->toBe('Sector Collana Bajo');
    $this->assertDatabaseCount('ruta_detalles', 2);
});

it('dos proveedores asignados a la misma ruta reciben órdenes distintos y consecutivos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $a = fixture_proveedor($zona);
    $b = fixture_proveedor($zona);

    foreach ([$a, $b] as $proveedor) {
        $this->actingAs($admin)->putJson(route('admin.zonas.proveedores.asignacion', $proveedor->id), asignacionValida($zona->id, $ruta->id()))->assertOk();
    }

    expect(RutaDetalleModel::where('ruta_id', $ruta->id())->orderBy('orden_visita')->pluck('proveedor_id')->all())
        ->toBe([$a->id, $b->id]);
});

it('valida que zona y ruta sean obligatorias y existan', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $proveedor = fixture_proveedor(fixture_zona());

    $this->actingAs($admin)->putJson(route('admin.zonas.proveedores.asignacion', $proveedor->id), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['zona_id' => 'Selecciona una zona.', 'ruta_id' => 'Selecciona una ruta.']);
    $this->actingAs($admin)->putJson(route('admin.zonas.proveedores.asignacion', $proveedor->id), ['zona_id' => 9999, 'ruta_id' => 9999])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['zona_id', 'ruta_id']);
});

it('rechaza una ruta que no pertenece a la zona seleccionada', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zonaA = fixture_zona('Collana 1');
    $zonaB = fixture_zona('Collana 2');
    $rutaDeB = app(RegistrarRuta::class)->execute(zonaId: $zonaB->id, nombre: 'Ruta 01');
    $proveedor = fixture_proveedor($zonaA);

    $this->actingAs($admin)
        ->putJson(route('admin.zonas.proveedores.asignacion', $proveedor->id), asignacionValida($zonaA->id, $rutaDeB->id()))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ruta_id' => 'La ruta seleccionada no pertenece a la zona indicada.']);

    $this->assertDatabaseCount('ruta_detalles', 0);
});

it('rechaza asignar a una ruta inactiva con el error bajo el campo ruta', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01', estado: 'INACTIVO');
    $proveedor = fixture_proveedor($zona);

    $this->actingAs($admin)
        ->putJson(route('admin.zonas.proveedores.asignacion', $proveedor->id), asignacionValida($zona->id, $ruta->id()))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ruta_id' => 'No se puede asignar proveedores a una ruta inactiva.']);
});

it('valida la latitud y la longitud', function (array $extra, string $campo, string $mensaje) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $proveedor = fixture_proveedor($zona);

    $this->actingAs($admin)
        ->putJson(route('admin.zonas.proveedores.asignacion', $proveedor->id), asignacionValida($zona->id, $ruta->id(), $extra))
        ->assertStatus(422)
        ->assertJsonValidationErrors([$campo => $mensaje]);

    $this->assertDatabaseCount('ruta_detalles', 0);
})->with([
    'latitud mayor a 90' => [['latitud' => 95], 'latitud', 'La latitud debe estar entre -90 y 90.'],
    'latitud menor a -90' => [['latitud' => -90.5], 'latitud', 'La latitud debe estar entre -90 y 90.'],
    'longitud mayor a 180' => [['longitud' => 185], 'longitud', 'La longitud debe estar entre -180 y 180.'],
    'longitud menor a -180' => [['longitud' => -181], 'longitud', 'La longitud debe estar entre -180 y 180.'],
    'latitud no numérica' => [['latitud' => 'abc'], 'latitud', 'La latitud debe ser un número.'],
    'solo latitud' => [['longitud' => null], 'longitud', 'Indica también la longitud: la latitud y la longitud van juntas.'],
    'solo longitud' => [['latitud' => null], 'latitud', 'Indica también la latitud: la latitud y la longitud van juntas.'],
]);

it('valida que la referencia no supere 255 caracteres', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $proveedor = fixture_proveedor($zona);

    $this->actingAs($admin)
        ->putJson(route('admin.zonas.proveedores.asignacion', $proveedor->id), asignacionValida($zona->id, $ruta->id(), ['referencia_ubicacion' => str_repeat('a', 256)]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('referencia_ubicacion');
});

// --------------------------------------------------- quitar y reordenar

it('quitar de la zona conserva al proveedor, compacta el orden y lo deja en "Sin asignar"', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    [$ana, $pedro, $maria] = [fixture_proveedor($zona), fixture_proveedor($zona), fixture_proveedor($zona)];
    foreach ([$ana, $pedro, $maria] as $proveedor) {
        app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id, latitud: -15.5, longitud: -70.5);
    }
    $detallePedro = RutaDetalleModel::where('proveedor_id', $pedro->id)->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('admin.rutas.detalles.destroy', [$ruta->id(), $detallePedro->id]))
        ->assertRedirect();

    $this->assertDatabaseHas('proveedores', ['id' => $pedro->id]);
    $this->assertDatabaseMissing('ruta_detalles', ['proveedor_id' => $pedro->id]);
    expect(RutaDetalleModel::where('ruta_id', $ruta->id())->orderBy('orden_visita')->pluck('orden_visita')->all())->toBe([1, 2]);
    expect(RutaDetalleModel::where('proveedor_id', $maria->id)->value('orden_visita'))->toBe(2);
});

it('reordena por JSON persistiendo el nuevo orden en la base de datos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    [$a, $b, $c] = [fixture_proveedor($zona), fixture_proveedor($zona), fixture_proveedor($zona)];
    foreach ([$a, $b, $c] as $proveedor) {
        app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id);
    }
    $ids = RutaDetalleModel::where('ruta_id', $ruta->id())->orderBy('orden_visita')->pluck('id')->all();

    $this->actingAs($admin)
        ->patchJson(route('admin.rutas.detalles.reordenar', $ruta->id()), ['orden' => [$ids[1], $ids[0], $ids[2]]])
        ->assertOk()->assertJsonPath('ok', true);

    expect(RutaDetalleModel::where('ruta_id', $ruta->id())->orderBy('orden_visita')->pluck('proveedor_id')->all())
        ->toBe([$b->id, $a->id, $c->id]);
});

it('rechaza un reordenamiento que omite a algún proveedor de la ruta', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    foreach ([fixture_proveedor($zona), fixture_proveedor($zona)] as $proveedor) {
        app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id);
    }
    $primero = RutaDetalleModel::where('ruta_id', $ruta->id())->orderBy('orden_visita')->value('id');

    $this->actingAs($admin)
        ->patchJson(route('admin.rutas.detalles.reordenar', $ruta->id()), ['orden' => [$primero]])
        ->assertStatus(422);
});

// ---------------------------------------------------------- autorización

it('solo el administrador puede usar las acciones de zonas y asignaciones', function (string $rol) {
    $usuario = fixture_usuario($rol);
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $proveedor = fixture_proveedor($zona);

    $this->actingAs($usuario)->get(route('admin.zonas.index'))->assertForbidden();
    $this->actingAs($usuario)->get(route('admin.zonas.panel', $zona->id))->assertForbidden();
    $this->actingAs($usuario)->getJson(route('admin.zonas.proveedores.buscar', ['q' => 'a']))->assertForbidden();
    $this->actingAs($usuario)->putJson(route('admin.zonas.proveedores.asignacion', $proveedor->id), asignacionValida($zona->id, $ruta->id()))->assertForbidden();
    $this->actingAs($usuario)->delete(route('admin.zonas.destroy', $zona->id))->assertForbidden();
    $this->actingAs($usuario)->patchJson(route('admin.rutas.detalles.reordenar', $ruta->id()), ['orden' => [1]])->assertForbidden();

    $this->assertDatabaseHas('zonas', ['id' => $zona->id]);
    $this->assertDatabaseCount('ruta_detalles', 0);
})->with(['ACOPIADOR', 'PROVEEDOR', 'CALIDAD']);

it('un usuario sin sesión es enviado al login en la búsqueda y en la asignación', function () {
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $proveedor = fixture_proveedor($zona);

    $this->getJson(route('admin.zonas.proveedores.buscar', ['q' => 'a']))->assertUnauthorized();
    $this->putJson(route('admin.zonas.proveedores.asignacion', $proveedor->id), asignacionValida($zona->id, $ruta->id()))->assertUnauthorized();
});

// ------------------------------------------------- formulario proveedor

it('el formulario de proveedor ya no pide la ubicación: se registra desde Zonas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_proveedor(fixture_zona());

    $this->actingAs($admin)->get(route('admin.proveedores.index'))
        ->assertOk()
        ->assertDontSee('Ubicación habitual de entrega')
        ->assertDontSee('Usar mi ubicación')
        ->assertDontSee('name="latitud"', false);
});
