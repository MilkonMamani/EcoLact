<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('muestra el login independiente con controles accesibles y CSRF', function () {
    $this->get('/login')->assertOk()->assertViewIs('auth.login')
        ->assertSee('¡Bienvenido a')->assertSee('EcoLact!')->assertSee('Recordarme')->assertSee('Iniciar sesión')
        ->assertSee('name="username"', false)->assertSee('name="password"', false)
        ->assertSee('type="checkbox"', false)->assertSee('name="_token"', false)
        ->assertSee('autocomplete="username"', false)->assertSee('autocomplete="current-password"', false)
        ->assertSee('aria-label="Mostrar contraseña"', false)->assertSee('ecolact-rural.svg')
        ->assertDontSee('name="email"', false)->assertDontSee('¿Olvidaste tu contraseña?');
});

it('conserva username escapado y nunca repuebla password tras un fallo', function () {
    $username = '<script>alert(1)</script>';
    $this->from('/login')->post('/login', ['username' => $username, 'password' => 'secreto-no-rellenar']);
    $response = $this->get('/login')->assertOk()->assertSee('Usuario o contraseña incorrectos.')
        ->assertSee('value="'.e($username).'"', false)->assertDontSee($username, false)
        ->assertDontSee('secreto-no-rellenar')->assertSee('role="alert"', false);
    preg_match('/<input[^>]*name="password"[^>]*>/', $response->getContent(), $campo);
    expect($campo[0])->not->toContain('value=');
});
