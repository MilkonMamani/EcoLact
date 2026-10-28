<?php

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ProveedorRutaHistorialModel;
use App\Infrastructure\Persistence\Models\ProveedorZonaHistorialModel;
use App\Infrastructure\Persistence\Models\RutaDetalleModel;
use App\Infrastructure\Persistence\Models\RutaModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/*
 * zonas:consolidar con el caso real: "Collana 1" (canónica, con delimitación) y "Collana1"
 * (duplicada por una carga masiva), ambas con "Ruta principal". La duplicada además tiene una
 * ruta propia, un proveedor solo con la caché de zona, un acopiador y una entrega.
 */
beforeEach(function () {
    $this->canonica = fixture_delimitar_zona(fixture_zona('Collana 1'), -15.6, -70.0);
    $this->canonica->update(['color_mapa' => '#16a34a']);
    $this->poligono = $this->canonica->fresh()->poligono_geojson;
    $this->duplicada = fixture_zona('Collana1');
    $this->duplicada->update(['descripcion' => 'Zona Collana1']);

    $principal = app(RegistrarRuta::class)->execute(zonaId: $this->canonica->id, nombre: 'Ruta principal');
    $principalDuplicada = app(RegistrarRuta::class)->execute(zonaId: $this->duplicada->id, nombre: 'Ruta principal');
    $norte = app(RegistrarRuta::class)->execute(zonaId: $this->duplicada->id, nombre: 'Ruta Norte');
    $this->rutaPrincipal = $principal->id();
    $this->rutaPrincipalDuplicada = $principalDuplicada->id();
    $this->rutaNorte = $norte->id();

    $asignar = fn (int $rutaId, ZonaModel $zona) => tap(fixture_proveedor($zona), fn ($p) => app(AsignarProveedorARuta::class)->execute(rutaId: $rutaId, proveedorId: $p->id));
    $this->p1 = $asignar($principal->id(), $this->canonica);
    $this->p2 = $asignar($principalDuplicada->id(), $this->duplicada);
    $this->p3 = $asignar($principalDuplicada->id(), $this->duplicada);
    $this->p4 = $asignar($norte->id(), $this->canonica); // cambia de zona: deja historial hacia la duplicada
    $this->soloCache = fixture_proveedor($this->duplicada);

    $this->acopiador = fixture_acopiador($this->duplicada);
    $this->entrega = fixture_entrega($this->p2->fresh(), $this->acopiador, 20);
});

/** Cuántas filas de cada tabla existen: la consolidación no debe borrar ninguna (salvo la zona y la ruta fusionada). */
function conteos_consolidacion(): array
{
    return collect(['proveedores', 'acopiadores', 'entregas', 'ruta_detalles', 'proveedor_ruta_historial', 'proveedor_zona_historial'])
        ->mapWithKeys(fn (string $tabla): array => [$tabla => DB::table($tabla)->count()])
        ->all();
}

it('--dry-run muestra el plan y no modifica nada', function () {
    $antes = conteos_consolidacion();

    $this->artisan('zonas:consolidar', ['--dry-run' => true])
        ->expectsOutputToContain('Zona origen:  Collana1 (id '.$this->duplicada->id.')')
        ->expectsOutputToContain('Zona destino: Collana 1 (id '.$this->canonica->id.')')
        ->expectsOutputToContain('Conflictos detectados: 0')
        ->expectsOutputToContain('no se modificó nada')
        ->assertSuccessful();

    expect(ZonaModel::find($this->duplicada->id))->not->toBeNull()
        ->and(conteos_consolidacion())->toBe($antes)
        ->and(ProveedorModel::where('zona_id', $this->duplicada->id)->count())->toBe(4)
        ->and(ProveedorZonaHistorialModel::where('zona_id', $this->duplicada->id)->exists())->toBeTrue()
        ->and(RutaModel::where('zona_id', $this->duplicada->id)->count())->toBe(2);
});

it('sin --apply tampoco modifica nada', function () {
    $this->artisan('zonas:consolidar')->assertSuccessful();

    expect(ZonaModel::find($this->duplicada->id))->not->toBeNull();
});

it('--apply deja solo la zona canónica y conserva proveedores, rutas, detalles e historiales', function () {
    $antes = conteos_consolidacion();

    $this->artisan('zonas:consolidar', ['--apply' => true, '--force' => true])
        ->expectsOutputToContain('Realiza un respaldo de la base de datos antes de continuar.')
        ->assertSuccessful();

    expect(ZonaModel::pluck('nombre')->all())->toBe(['Collana 1'])
        ->and(conteos_consolidacion())->toBe($antes);

    // Proveedores: todos en la canónica (también el que solo tenía la caché de zona).
    expect(ProveedorModel::where('zona_id', $this->canonica->id)->count())->toBe(5);

    // Rutas: "Ruta principal" se fusionó (la duplicada desaparece) y "Ruta Norte" pasó a la canónica.
    expect(RutaModel::where('zona_id', $this->canonica->id)->orderBy('nombre')->pluck('nombre')->all())->toBe(['Ruta Norte', 'Ruta principal'])
        ->and(RutaModel::find($this->rutaPrincipalDuplicada))->toBeNull()
        ->and(RutaModel::find($this->rutaNorte)->zona_id)->toBe($this->canonica->id);

    // Detalles: los de la ruta fusionada van después de los propios, en su mismo orden.
    expect(RutaDetalleModel::where('ruta_id', $this->rutaPrincipal)->orderBy('orden_visita')->pluck('proveedor_id', 'orden_visita')->all())
        ->toBe([1 => $this->p1->id, 2 => $this->p2->id, 3 => $this->p3->id])
        ->and(RutaDetalleModel::where('proveedor_id', $this->p4->id)->value('ruta_id'))->toBe($this->rutaNorte);

    // Historiales: se conservan y apuntan a la zona/ruta canónicas.
    expect(ProveedorRutaHistorialModel::where('proveedor_id', $this->p2->id)->value('ruta_id'))->toBe($this->rutaPrincipal)
        ->and(ProveedorZonaHistorialModel::where('zona_id', $this->duplicada->id)->exists())->toBeFalse()
        ->and(ProveedorZonaHistorialModel::where('proveedor_id', $this->p4->id)->whereNull('fecha_fin')->value('zona_id'))->toBe($this->canonica->id);

    // Acopiador y entrega histórica: misma zona lógica, ahora con el id canónico.
    expect(AcopiadorModel::find($this->acopiador->id)->zona_id)->toBe($this->canonica->id)
        ->and(EntregaModel::find($this->entrega->id)->zona_id)->toBe($this->canonica->id);
});

it('no deja ninguna llave foránea apuntando a la zona eliminada', function () {
    $this->artisan('zonas:consolidar', ['--apply' => true, '--force' => true])->assertSuccessful();

    foreach (['proveedores', 'acopiadores', 'rutas', 'entregas', 'comunicados', 'proveedor_zona_historial', 'acopiador_zona_historial'] as $tabla) {
        expect(DB::table($tabla)->where('zona_id', $this->duplicada->id)->exists())->toBeFalse("{$tabla} todavía apunta a la zona duplicada");
    }
});

it('la canónica conserva su delimitación, su color y no recibe la descripción genérica de la carga', function () {
    $this->duplicada->update(['color_mapa' => '#000000']);
    fixture_delimitar_zona($this->duplicada, -10.0, -60.0);

    $this->artisan('zonas:consolidar', ['--apply' => true, '--force' => true])->assertSuccessful();

    $canonica = $this->canonica->fresh();
    expect($canonica->poligono_geojson)->toBe($this->poligono)
        ->and($canonica->color_mapa)->toBe('#16a34a')
        ->and($canonica->descripcion)->toBeNull();
});

it('si la canónica no tiene delimitación toma la de la duplicada', function () {
    $this->canonica->update(['poligono_geojson' => null]);
    fixture_delimitar_zona($this->duplicada, -15.6, -70.0);
    $poligonoDuplicada = $this->duplicada->fresh()->poligono_geojson;

    $this->artisan('zonas:consolidar', ['--apply' => true, '--force' => true])->assertSuccessful();

    expect($this->canonica->fresh()->poligono_geojson)->toBe($poligonoDuplicada);
});

it('detiene --apply sin modificar nada si dos rutas distintas de "Ruta principal" chocan por nombre', function () {
    app(RegistrarRuta::class)->execute(zonaId: $this->canonica->id, nombre: 'Ruta Norte');
    $antes = conteos_consolidacion();

    $this->artisan('zonas:consolidar', ['--apply' => true, '--force' => true])
        ->expectsOutputToContain('no es seguro asumir que son la misma ruta')
        ->assertFailed();

    expect(ZonaModel::find($this->duplicada->id))->not->toBeNull()
        ->and(conteos_consolidacion())->toBe($antes)
        ->and(RutaModel::find($this->rutaNorte)->zona_id)->toBe($this->duplicada->id);
});

it('solo toca las cuatro zonas oficiales: sin canónica exacta es un conflicto y no se crea la zona', function () {
    fixture_zona('FAON');
    fixture_zona('Otra1');
    fixture_zona('Otra 1');

    $this->artisan('zonas:consolidar', ['--apply' => true, '--force' => true])
        ->expectsOutputToContain('no existe una zona llamada exactamente "Faon"')
        ->assertFailed();

    expect(ZonaModel::where('nombre', 'Faon')->exists())->toBeFalse()
        ->and(ZonaModel::whereIn('nombre', ['Otra1', 'Otra 1', 'FAON', 'Collana1'])->count())->toBe(4);
});
