<?php

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('cada tipo de acción se pinta con su icono y color fijos, con tooltip y aria-label', function (string $tipo, string $claseColor) {
    $html = Blade::render('<x-ui.action-button type="'.$tipo.'" label="Etiqueta" subject="Ana Choque" />');

    expect($html)
        ->toContain($claseColor)
        ->toContain('title="Etiqueta Ana Choque"')
        ->toContain('aria-label="Etiqueta Ana Choque"')
        ->toContain('<svg')
        ->toContain('h-8 w-8');
    expect(substr_count($html, '<path'))->toBeGreaterThan(0);
})->with([
    'ver = azul' => ['view', 'bg-info text-white'],
    'editar = amarillo con icono oscuro' => ['edit', 'bg-warning text-text'],
    'eliminar = rojo' => ['delete', 'bg-danger text-white'],
    'desactivar = rojo' => ['deactivate', 'bg-danger text-white'],
    'activar = verde' => ['activate', 'bg-success text-white'],
    'quitar = rojo suave' => ['unlink', 'bg-danger/10 text-danger'],
    'ubicación = turquesa' => ['location', 'bg-primary text-white'],
    'descargar = azul' => ['download', 'bg-info text-white'],
    'imprimir = neutro' => ['print', 'bg-text-muted text-white'],
    'pagar = verde' => ['pay', 'bg-success text-white'],
    'crear = turquesa' => ['create', 'bg-primary text-white'],
]);

it('eliminar, desactivar y quitar usan iconos distintos para no confundirse', function () {
    $trazado = fn (string $tipo) => Blade::render('<x-ui.action-button type="'.$tipo.'" />');

    expect(array_unique([$trazado('delete'), $trazado('deactivate'), $trazado('unlink')]))->toHaveCount(3);
});

it('con href renderiza un enlace y sin href un botón que no envía formularios salvo que se pida', function () {
    expect(Blade::render('<x-ui.action-button type="view" href="/algo" />'))->toContain('<a')->toContain('href="/algo"');
    expect(Blade::render('<x-ui.action-button type="edit" />'))->toContain('type="button"');
    expect(Blade::render('<x-ui.action-button type="delete" :submit="true" />'))->toContain('type="submit"');
});

it('el modo texto muestra la etiqueta visible junto al icono', function () {
    expect(Blade::render('<x-ui.action-button type="edit" :text="true" label="Editar" />'))->toContain('<span>Editar</span>');
});

it('los enlaces que abren en otra pestaña llevan rel noopener', function () {
    expect(Blade::render('<x-ui.action-button type="location" href="https://maps.test" target="_blank" />'))
        ->toContain('rel="noopener noreferrer"');
});

it('un tipo desconocido falla en vez de pintar un botón sin significado', function () {
    Blade::render('<x-ui.action-button type="inventado" />');
})->throws(ViewException::class, 'Tipo de acción desconocido: inventado');

it('ninguna columna de acciones de las vistas usa botones de texto: todas usan x-ui.action-button', function () {
    $infractores = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $archivo) {
        if (! str_ends_with($archivo->getFilename(), '.blade.php')) {
            continue;
        }

        preg_match_all('/<x-tables\.actions[^>]*>(.*?)<\/x-tables\.actions>/s', file_get_contents($archivo->getPathname()), $bloques);

        foreach ($bloques[1] as $bloque) {
            if (str_contains($bloque, '<x-ui.button')) {
                $infractores[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $archivo->getPathname());
            }
        }
    }

    expect($infractores)->toBe([]);
});

it('la tabla de proveedores selecciona al proveedor en el panel sin página de detalle ni botones por fila', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $proveedor = fixture_proveedor(fixture_zona('Collana 1'), '72100012');
    $proveedor->update(['nombres' => 'Ana', 'apellidos' => 'Choque Condori']);

    $this->actingAs($admin)->get(route('admin.proveedores.index'))
        ->assertOk()
        ->assertSee('data-url-panel="'.route('admin.proveedores.panel', $proveedor->id).'"', false)
        ->assertSee(e(route('admin.proveedores.index', ['proveedor' => $proveedor->id])), false)
        ->assertSee('aria-label="Editar información personal de Ana Choque Condori"', false)
        ->assertSee('modal-editar-proveedor-'.$proveedor->id, false)
        ->assertSee('id="modal-desactivar-proveedor"', false)
        ->assertDontSee('aria-label="Ver perfil Ana Choque Condori"', false);
});

it('desactivar conserva su modal de confirmación con el aviso de que el registro no se elimina', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $proveedor = fixture_proveedor(fixture_zona('Collana 1'));

    $this->actingAs($admin)->get(route('admin.proveedores.index'))
        ->assertSee('Se conservan sus entregas, compras, controles de calidad, pagos y su historial.', false)
        ->assertSee(route('admin.proveedores.desactivar', $proveedor->id), false);
});

it('eliminar zona conserva DELETE + CSRF + confirmación permanente; quitar de la zona usa icono distinto y avisa que el proveedor sigue existiendo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $proveedor = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id);

    $response = $this->actingAs($admin)->get(route('admin.zonas.index', ['zona' => $zona->id]));

    $response->assertSee('aria-label="Eliminar zona Collana 1"', false)
        ->assertSee('Esta acción eliminará permanentemente la zona', false)
        ->assertSee('name="_method" value="DELETE"', false)
        ->assertSee('aria-label="Editar zona Collana 1"', false)
        ->assertSee('El proveedor seguirá existiendo; únicamente se eliminará su asignación actual', false)
        ->assertSee('aria-label="Quitar de la zona a '.$proveedor->nombres.' '.$proveedor->apellidos.'"', false);
    expect($response->getContent())->toContain('name="_token"');
});
