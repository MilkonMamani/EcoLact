<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';
uses(TestCase::class, RefreshDatabase::class);

dataset('areas web', [
    ['ADMINISTRADOR', 'admin.dashboard'],
    ['ACOPIADOR', 'acopiador.dashboard'],
    ['PROVEEDOR', 'proveedor.dashboard'],
    ['ENCARGADO_PLANTA', 'planta.dashboard'],
    ['VENTAS', 'ventas.dashboard'],
    ['CALIDAD', 'calidad.dashboard'],
]);

function usuario_login(string $rol): User
{
    return match ($rol) {
        'ACOPIADOR' => fixture_acopiador(fixture_zona())->user,
        'PROVEEDOR' => fixture_proveedor(fixture_zona())->user,
        default => fixture_usuario($rol),
    };
}

it('autentica por username y permite entrar al área real con logout', function (string $rol, string $ruta) {
    $user = usuario_login($rol);
    $this->post('/login', ['username' => $user->username, 'password' => 'secret'])->assertRedirectToRoute($ruta);
    $this->assertAuthenticatedAs($user, 'web');
    $this->get(route($ruta))->assertOk()->assertSee('Cerrar sesión')->assertSee(route('logout'));
    $this->assertDatabaseCount('personal_access_tokens', 0);
})->with('areas web');

it('redirige al autenticado desde raíz y ambas rutas guest', function (string $rol, string $ruta) {
    $this->actingAs(usuario_login($rol));
    $this->get('/')->assertRedirectToRoute($ruta);
    $this->get('/login')->assertRedirectToRoute($ruta);
    $this->post('/login', [])->assertRedirectToRoute($ruta);
})->with('areas web');

it('redirige invitados de raíz y área protegida al login', function () {
    $this->get('/')->assertRedirectToRoute('login');
    $this->get(route('admin.dashboard'))->assertRedirectToRoute('login');
    $this->getJson(route('admin.dashboard'))->assertUnauthorized();
});

it('rechaza credenciales con el mismo mensaje sin conservar contraseña', function (bool $existe) {
    $username = $existe ? fixture_usuario('ADMINISTRADOR')->username : 'inexistente';
    $this->from('/login')->post('/login', ['username' => $username, 'password' => 'incorrecta'])
        ->assertRedirect('/login')->assertSessionHasErrors(['username' => 'Usuario o contraseña incorrectos.'])
        ->assertSessionHasInput('username', $username)->assertSessionMissing('_old_input.password');
    $this->assertGuest();
})->with([true, false]);

it('valida campos HTTP y no conserva contraseñas', function (array $datos, array $errores) {
    $this->from('/login')->post('/login', $datos)->assertRedirect('/login')->assertSessionHasErrors($errores)
        ->assertSessionMissing('_old_input.password');
    $this->assertGuest();
})->with([
    [[], ['username' => 'El usuario es obligatorio.', 'password' => 'La contraseña es obligatoria.']],
    [['username' => ['no'], 'password' => ['no'], 'remember' => 'no'], ['username', 'password', 'remember']],
    [['username' => str_repeat('a', 61), 'password' => 'secret'], ['username']],
]);

it('rechaza cuenta inactiva y elimina sesión y cookie persistente', function () {
    $user = fixture_usuario('ADMINISTRADOR');
    $user->update(['estado' => 'INACTIVO']);
    $this->withSession(['dato_anterior' => 'privado']);
    $this->from('/login')->post('/login', ['username' => $user->username, 'password' => 'secret', 'remember' => true])
        ->assertRedirect('/login')->assertSessionHasErrors(['username' => 'Tu cuenta se encuentra inactiva. Comunícate con el administrador.'])
        ->assertSessionMissing('dato_anterior')->assertCookieMissing(Auth::guard('web')->getRecallerName());
    $this->assertGuest();
});

it('rechaza perfiles operativos ausentes o inactivos', function (string $rol, bool $ausente) {
    $user = $ausente ? fixture_usuario($rol) : usuario_login($rol);
    if (! $ausente) {
        ($rol === 'ACOPIADOR' ? $user->acopiador : $user->proveedor)->update(['estado' => 'INACTIVO']);
    }
    $this->from('/login')->post('/login', ['username' => $user->username, 'password' => 'secret'])
        ->assertRedirect('/login')->assertSessionHasErrors('username');
    $this->assertGuest();
})->with([['ACOPIADOR', true], ['ACOPIADOR', false], ['PROVEEDOR', true], ['PROVEEDOR', false]]);

it('rechaza un rol desconocido tras autenticar sin sesión residual', function () {
    $user = fixture_usuario('DESCONOCIDO');
    $this->from('/login')->post('/login', ['username' => $user->username, 'password' => 'secret'])
        ->assertRedirect('/login')->assertSessionHasErrors(['username' => 'Tu cuenta no tiene un rol válido. Comunícate con el administrador.']);
    $this->assertGuest();
});

it('limpia sesiones inválidas al entrar por raíz o guest sin bucles', function (string $url, string $caso) {
    $user = fixture_usuario($caso === 'rol' ? 'DESCONOCIDO' : 'ADMINISTRADOR');
    if ($caso === 'inactivo') {
        $user->update(['estado' => 'INACTIVO']);
    }
    if ($caso === 'sin rol') {
        $user->setRelation('role', null);
    }
    $this->actingAs($user)->get($url)->assertRedirectToRoute('login')->assertSessionHasErrors('username');
    $this->assertGuest();
    $this->get('/login')->assertOk();
})->with(['/', '/login'])->with(['rol', 'inactivo', 'sin rol']);

it('regenera el ID y token de sesión y descarta destinos ajenos al rol', function () {
    $user = fixture_usuario('ADMINISTRADOR');
    $this->withSession(['url.intended' => 'https://example.org', '_token' => 'token-previo']);
    $id = session()->getId();
    $this->post('/login', ['username' => $user->username, 'password' => 'secret'])->assertRedirectToRoute('admin.dashboard')
        ->assertSessionMissing('url.intended');
    expect(session()->getId())->not->toBe($id);
    expect(session()->token())->not->toBe('token-previo');
});

it('cierra sesión incluso si la cuenta fue desactivada y elimina datos previos', function () {
    $user = fixture_usuario('ADMINISTRADOR');
    $login = $this->post('/login', ['username' => $user->username, 'password' => 'secret', 'remember' => 1]);
    $nombre = Auth::guard('web')->getRecallerName();
    $this->withUnencryptedCookie($nombre, $login->getCookie($nombre, false)->getValue());
    $user->update(['estado' => 'INACTIVO']);
    $this->withSession(['dato_privado' => 'borrar']);
    $id = session()->getId();
    $token = session()->token();
    $this->post('/logout')->assertRedirectToRoute('login')->assertSessionMissing('dato_privado')
        ->assertSessionHas('success', 'Sesión cerrada correctamente.')
        ->assertCookieExpired(Auth::guard('web')->getRecallerName());
    $this->assertGuest();
    expect(session()->getId())->not->toBe($id);
    expect(session()->token())->not->toBe($token);
    $this->get(route('admin.dashboard'))->assertRedirectToRoute('login');
});

it('no permite logout mediante GET ni a invitados', function () {
    $this->get('/logout')->assertMethodNotAllowed();
    $this->post('/logout')->assertRedirectToRoute('login');
});

it('recuerda realmente al usuario al perder la sesión', function () {
    $user = fixture_usuario('ADMINISTRADOR');
    $nombre = Auth::guard('web')->getRecallerName();
    $response = $this->post('/login', ['username' => $user->username, 'password' => 'secret', 'remember' => 1]);
    $response->assertCookie($nombre);
    expect($user->fresh()->remember_token)->not->toBeEmpty();
    $cookie = $response->getCookie($nombre, false);
    session()->flush();
    Auth::forgetGuards();
    $this->withUnencryptedCookie($nombre, $cookie->getValue())->get('/')->assertRedirectToRoute('admin.dashboard');
    $this->assertAuthenticatedAs($user);
    expect(Auth::guard('web')->viaRemember())->toBeTrue();
});

it('no genera cookie persistente sin recordarme', function () {
    $user = fixture_usuario('ADMINISTRADOR');
    $this->post('/login', ['username' => $user->username, 'password' => 'secret'])
        ->assertCookieMissing(Auth::guard('web')->getRecallerName());
    expect($user->fresh()->remember_token)->toBeNull();
});

it('limita cinco fallos y permite reintentar después de un minuto', function () {
    $this->freezeTime();
    $user = fixture_usuario('ADMINISTRADOR');
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['username' => $user->username, 'password' => 'mal'])->assertSessionHasErrors('username');
    }
    $this->post('/login', ['username' => $user->username, 'password' => 'secret'])
        ->assertSessionHasErrors(['username' => 'Demasiados intentos. Inténtalo nuevamente en 60 segundos.']);
    $this->assertGuest();
    $this->travel(61)->seconds();
    $this->post('/login', ['username' => $user->username, 'password' => 'secret'])->assertRedirectToRoute('admin.dashboard');
});

it('separa el límite por usuario e IP', function () {
    $user = fixture_usuario('ADMINISTRADOR');
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['username' => $user->username, 'password' => 'mal']);
    }
    $otro = fixture_usuario('ADMINISTRADOR');
    $this->post('/login', ['username' => $otro->username, 'password' => 'secret'])->assertRedirectToRoute('admin.dashboard');
    $this->post('/logout');
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.25'])->post('/login', ['username' => $user->username, 'password' => 'secret'])
        ->assertRedirectToRoute('admin.dashboard');
});

it('reinicia el contador tras un acceso correcto', function () {
    $user = fixture_usuario('ADMINISTRADOR');
    for ($i = 0; $i < 4; $i++) {
        $this->post('/login', ['username' => $user->username, 'password' => 'mal']);
    }
    $this->post('/login', ['username' => $user->username, 'password' => 'secret'])->assertRedirectToRoute('admin.dashboard');
    $this->post('/logout');
    $this->post('/login', ['username' => $user->username, 'password' => 'mal']);
    $this->post('/login', ['username' => $user->username, 'password' => 'secret'])->assertRedirectToRoute('admin.dashboard');
});

it('mantiene la autorización entre roles después del login', function () {
    $user = usuario_login('ACOPIADOR');
    $this->post('/login', ['username' => $user->username, 'password' => 'secret']);
    $this->get(route('admin.dashboard'))->assertForbidden();
    $user->acopiador->update(['estado' => 'INACTIVO']);
    Auth::forgetGuards();
    $this->get(route('acopiador.entregas.index'))->assertForbidden();
});
