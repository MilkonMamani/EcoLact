<?php

use App\Application\Calidad\Contracts\TicketAnalizadorOcrService;
use App\Application\Calidad\TicketIlegibleException;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

use function Pest\Laravel\mock;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function ticket_entrega_pendiente()
{
    $zona = fixture_zona();

    return fixture_entrega(fixture_proveedor($zona), fixture_acopiador($zona), 30.0);
}

function ticket_ocr_lee(array $lecturas): void
{
    mock(TicketAnalizadorOcrService::class)->shouldReceive('leerLecturas')->once()->andReturn($lecturas);
}

it('propone los valores leídos del ticket enlazados por código y guarda la foto sin registrar el análisis', function () {
    Storage::fake('public');
    $analista = fixture_usuario('CALIDAD');
    $entrega = ticket_entrega_pendiente();
    $grasa = fixture_parametro('GRASA');
    $proteina = fixture_parametro('PROTEINA');
    $lactosa = fixture_parametro('LACTOSA');
    fixture_parametro('PH');
    ticket_ocr_lee(["Grasa..... 4,5%\nProteina.... 3.4%\nLactosa..... 4.7%\nSNG...... 8.7%"]);

    $respuesta = $this->actingAs($analista)->postJson(route('calidad.analisis-calidad.escanear-ticket'), [
        'entrega_id' => $entrega->id,
        'imagen' => UploadedFile::fake()->image('ticket.jpg'),
    ]);

    $respuesta->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('suficientes', true)
        ->assertJsonPath('mensaje', 'Valores detectados automáticamente. Revisa la información antes de guardar.')
        ->assertJsonPath('valores', [
            ['parametro_id' => $grasa->id, 'codigo' => 'GRASA', 'nombre' => 'GRASA', 'valor' => 4.5, 'unidad' => null],
            ['parametro_id' => $proteina->id, 'codigo' => 'PROTEINA', 'nombre' => 'PROTEINA', 'valor' => 3.4, 'unidad' => null],
            ['parametro_id' => $lactosa->id, 'codigo' => 'LACTOSA', 'nombre' => 'LACTOSA', 'valor' => 4.7, 'unidad' => null],
        ])
        ->assertJsonPath('no_detectados', [['codigo' => 'PH', 'nombre' => 'PH']])
        ->assertJsonPath('detectados_no_configurados', [['codigo' => 'SNG', 'valor' => 8.7]]);

    $ticketPath = $respuesta->json('ticket_path');
    expect($ticketPath)->toStartWith("analisis-calidad/tickets/entrega-{$entrega->id}-");
    Storage::disk('public')->assertExists($ticketPath);
    $this->assertDatabaseMissing('analisis_calidad', ['entrega_id' => $entrega->id]);
    $this->assertDatabaseMissing('parametros_calidad', ['codigo' => 'SNG']);
});

it('avisa que la foto no sirve cuando se reconocen pocos valores', function () {
    Storage::fake('public');
    $entrega = ticket_entrega_pendiente();
    fixture_parametro('GRASA');
    ticket_ocr_lee(['Grasa 4.5 %']);

    $this->actingAs(fixture_usuario('CALIDAD'))->postJson(route('calidad.analisis-calidad.escanear-ticket'), [
        'entrega_id' => $entrega->id,
        'imagen' => UploadedFile::fake()->image('ticket.jpg'),
    ])->assertOk()
        ->assertJsonPath('suficientes', false)
        ->assertJsonPath('mensaje', 'No se pudieron identificar suficientes valores. Toma la foto de frente, con buena iluminación y sin sombras.');
});

it('si el OCR falla responde el aviso y conserva la foto para ingresar los valores a mano', function () {
    Storage::fake('public');
    $entrega = ticket_entrega_pendiente();
    mock(TicketAnalizadorOcrService::class)->shouldReceive('leerLecturas')->andThrow(new TicketIlegibleException('tesseract no encontrado'));

    $respuesta = $this->actingAs(fixture_usuario('CALIDAD'))->postJson(route('calidad.analisis-calidad.escanear-ticket'), [
        'entrega_id' => $entrega->id,
        'imagen' => UploadedFile::fake()->image('ticket.png'),
    ]);

    $respuesta->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('mensaje', 'No se pudo leer el ticket automáticamente.');
    Storage::disk('public')->assertExists($respuesta->json('ticket_path'));
});

it('no escanea el ticket de una entrega con análisis ya confirmado', function () {
    Storage::fake('public');
    $analista = fixture_usuario('CALIDAD');
    $entrega = ticket_entrega_pendiente();
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $analista->id);
    mock(TicketAnalizadorOcrService::class)->shouldNotReceive('leerLecturas');

    $this->actingAs($analista)->postJson(route('calidad.analisis-calidad.escanear-ticket'), [
        'entrega_id' => $entrega->id,
        'imagen' => UploadedFile::fake()->image('ticket.jpg'),
    ])->assertUnprocessable()
        ->assertJsonPath('mensaje', 'El análisis de esta entrega ya fue confirmado y no puede modificarse.');

    expect(Storage::disk('public')->allFiles('analisis-calidad/tickets'))->toBe([]);
});

it('rechaza un archivo que no es imagen', function () {
    $entrega = ticket_entrega_pendiente();

    $this->actingAs(fixture_usuario('CALIDAD'))->postJson(route('calidad.analisis-calidad.escanear-ticket'), [
        'entrega_id' => $entrega->id,
        'imagen' => UploadedFile::fake()->create('ticket.pdf', 100, 'application/pdf'),
    ])->assertUnprocessable()->assertJsonValidationErrors(['imagen' => 'El archivo debe ser una imagen.']);
});

it('rechaza una foto de más de 5 MB', function () {
    $entrega = ticket_entrega_pendiente();

    $this->actingAs(fixture_usuario('CALIDAD'))->postJson(route('calidad.analisis-calidad.escanear-ticket'), [
        'entrega_id' => $entrega->id,
        'imagen' => UploadedFile::fake()->image('ticket.jpg')->size(6000),
    ])->assertUnprocessable()->assertJsonValidationErrors(['imagen' => 'La foto no debe superar los 5 MB.']);
});

it('no permite escanear tickets a un proveedor', function () {
    $entrega = ticket_entrega_pendiente();
    mock(TicketAnalizadorOcrService::class)->shouldNotReceive('leerLecturas');

    $this->actingAs(fixture_usuario('PROVEEDOR'))->postJson(route('calidad.analisis-calidad.escanear-ticket'), [
        'entrega_id' => $entrega->id,
        'imagen' => UploadedFile::fake()->image('ticket.jpg'),
    ])->assertForbidden();
});

it('un visitante sin sesión es enviado al inicio de sesión', function () {
    $entrega = ticket_entrega_pendiente();

    $this->post(route('calidad.analisis-calidad.escanear-ticket'), ['entrega_id' => $entrega->id])
        ->assertRedirect(route('login'));
});

it('al guardar asocia la foto del ticket al análisis y marca el origen de cada valor', function () {
    Storage::fake('public');
    $analista = fixture_usuario('CALIDAD');
    $entrega = ticket_entrega_pendiente();
    $grasa = fixture_parametro('GRASA');
    $proteina = fixture_parametro('PROTEINA');
    $ticketPath = "analisis-calidad/tickets/entrega-{$entrega->id}-abcdefghijklmnopqrstuvwx.jpg";
    Storage::disk('public')->put($ticketPath, 'foto');

    $this->actingAs($analista)->post(route('calidad.analisis-calidad.store'), [
        'entrega_id' => $entrega->id,
        'valores' => ['GRASA' => 4.5, 'PROTEINA' => 3.4],
        'origenes' => ['GRASA' => 'OCR', 'PROTEINA' => 'MANUAL'],
        'ticket_path' => $ticketPath,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $analisis = AnalisisCalidadModel::where('entrega_id', $entrega->id)->sole();
    expect($analisis->imagen_path)->toBe($ticketPath)
        ->and($analisis->estado)->toBe('BORRADOR');
    $this->assertDatabaseHas('analisis_calidad_detalles', ['analisis_id' => $analisis->id, 'parametro_id' => $grasa->id, 'origen' => 'OCR']);
    $this->assertDatabaseHas('analisis_calidad_detalles', ['analisis_id' => $analisis->id, 'parametro_id' => $proteina->id, 'origen' => 'MANUAL']);
});

it('un re-escaneo reemplaza la foto de un análisis en borrador', function () {
    Storage::fake('public');
    $analista = fixture_usuario('CALIDAD');
    $entrega = ticket_entrega_pendiente();
    fixture_parametro('GRASA');
    $primera = "analisis-calidad/tickets/entrega-{$entrega->id}-aaaaaaaaaaaaaaaaaaaaaaaa.jpg";
    $segunda = "analisis-calidad/tickets/entrega-{$entrega->id}-bbbbbbbbbbbbbbbbbbbbbbbb.jpg";
    Storage::disk('public')->put($primera, 'foto');
    Storage::disk('public')->put($segunda, 'foto');

    foreach ([$primera, $segunda] as $ticketPath) {
        $this->actingAs($analista)->post(route('calidad.analisis-calidad.store'), [
            'entrega_id' => $entrega->id,
            'valores' => ['GRASA' => 4.5],
            'ticket_path' => $ticketPath,
        ])->assertSessionHasNoErrors();
    }

    expect(AnalisisCalidadModel::where('entrega_id', $entrega->id)->sole()->imagen_path)->toBe($segunda);
});

it('no acepta como ticket la foto de otra entrega', function () {
    Storage::fake('public');
    $analista = fixture_usuario('CALIDAD');
    $entrega = ticket_entrega_pendiente();
    $otra = ticket_entrega_pendiente();
    fixture_parametro('GRASA');
    $ticketAjeno = "analisis-calidad/tickets/entrega-{$otra->id}-abcdefghijklmnopqrstuvwx.jpg";
    Storage::disk('public')->put($ticketAjeno, 'foto');

    $this->actingAs($analista)->post(route('calidad.analisis-calidad.store'), [
        'entrega_id' => $entrega->id,
        'valores' => ['GRASA' => 4.5],
        'ticket_path' => $ticketAjeno,
    ])->assertSessionHasErrors(['ticket_path' => 'La foto del ticket no corresponde a esta entrega. Vuelve a escanearla.']);

    $this->assertDatabaseMissing('analisis_calidad', ['entrega_id' => $entrega->id]);
});

it('la pantalla de registrar análisis muestra el escáner de ticket y la trazabilidad de la entrega', function () {
    $entrega = ticket_entrega_pendiente();
    fixture_parametro('GRASA');

    $this->actingAs(fixture_usuario('CALIDAD'))->get(route('calidad.entregas.analisis.index', $entrega->id))
        ->assertOk()
        ->assertSee('Ticket del analizador')
        ->assertSee('capture="environment"', false)
        ->assertSee('Acopiador')
        ->assertSee($entrega->proveedor->nombres);
});

it('admin y analista ven los datos de la entrega y el mismo bloque del ticket, cada uno con su endpoint', function (string $rol, string $rutaPantalla, string $rutaEscanear) {
    $entrega = ticket_entrega_pendiente()->load(['proveedor', 'zona', 'acopiador']);
    fixture_parametro('GRASA');

    $this->actingAs(fixture_usuario($rol))->get(route($rutaPantalla, $entrega->id))
        ->assertOk()
        ->assertSeeInOrder(['Proveedor', $entrega->proveedor->nombres, 'DNI', $entrega->proveedor->dni, 'Entrega', '#'.$entrega->id, 'Zona', $entrega->zona->nombre, 'Litros', '30.00 L', 'Acopiador', $entrega->acopiador->nombres])
        ->assertSee('Ticket del analizador')
        ->assertSee('capture="environment"', false)
        ->assertSee('data-url="'.route($rutaEscanear).'"', false);
})->with([
    'administrador' => ['ADMINISTRADOR', 'admin.entregas.analisis-calidad.index', 'admin.analisis-calidad.escanear-ticket'],
    'analista' => ['CALIDAD', 'calidad.entregas.analisis.index', 'calidad.analisis-calidad.escanear-ticket'],
]);
