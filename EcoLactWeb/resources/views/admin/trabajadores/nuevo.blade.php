@extends('layouts.admin')

@section('title', 'Nuevo trabajador')
@section('body-class', 'admin-fichas-page')
@section('contenedor', 'max-w-[1240px]')

@section('content')
    @php
        $actual = $tipos[$tipo];
    @endphp

    <div class="fichas nuevo-trabajador" data-nuevo-trabajador>
        <x-layout.breadcrumbs :items="[['Personal', null], ['Trabajadores', null], ['Nuevo trabajador', null]]" />

        <div class="nt-barra">
            <a href="{{ $volver }}" class="nt-volver">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Volver
            </a>

            <details class="nt-listas">
                <summary class="fichas-btn is-outline">
                    <x-ui.icon name="users" class="h-4 w-4" /> Ver lista de trabajadores
                    <x-ui.icon name="chevron-down" class="h-4 w-4" />
                </summary>
                <div class="nt-listas-menu" role="menu">
                    @foreach ($tipos as $opcion)
                        <a href="{{ route($opcion['listado']) }}" role="menuitem">
                            <span class="nt-icono-rol is-{{ $opcion['tono'] }}"><x-ui.icon :name="$opcion['icono']" class="h-4 w-4" /></span>
                            {{ $opcion['plural'] }}
                        </a>
                    @endforeach
                </div>
            </details>
        </div>

        <header class="nt-encabezado">
            <h1 class="fichas-title">Nuevo trabajador</h1>
            <p class="fichas-subtitle">Registra un nuevo integrante del equipo de EcoLact.</p>
        </header>

        {{-- Selector de rol: radios con aspecto de tarjeta. admin-nuevo-trabajador.js cambia el destino del formulario y los campos sin recargar. --}}
        <fieldset class="nt-roles">
            <legend class="sr-only">Tipo de trabajador</legend>
            @foreach ($tipos as $clave => $opcion)
                <label class="nt-rol is-{{ $opcion['tono'] }}">
                    <input type="radio" name="tipo_trabajador" value="{{ $clave }}" @checked($clave === $tipo)
                        data-accion="{{ route($opcion['ruta']) }}" data-con-zona="{{ $opcion['conZona'] ? '1' : '0' }}"
                        data-cancelar="{{ route($opcion['listado']) }}">
                    <span class="nt-icono-rol is-{{ $opcion['tono'] }}"><x-ui.icon :name="$opcion['icono']" class="h-5 w-5" /></span>
                    <span class="nt-rol-texto">
                        <span class="nt-rol-nombre">{{ $opcion['etiqueta'] }}</span>
                        <span class="nt-rol-descripcion">{{ $opcion['descripcion'] }}</span>
                    </span>
                    <span class="nt-rol-radio" aria-hidden="true"></span>
                </label>
            @endforeach
        </fieldset>
        <noscript>
            <p class="nt-noscript">Sin JavaScript, elige el tipo aquí:
                @foreach ($tipos as $clave => $opcion)
                    <a href="{{ route('admin.trabajadores.create', ['tipo' => $clave]) }}">{{ $opcion['etiqueta'] }}</a>@if (! $loop->last) · @endif
                @endforeach
            </p>
        </noscript>

        <div class="nt-cuerpo">
            <form method="POST" action="{{ route($actual['ruta']) }}" class="nt-formulario" data-nuevo-trabajador-form>
                @csrf

                <section class="nt-seccion">
                    <div class="nt-seccion-head">
                        <span class="nt-seccion-icono"><x-ui.icon name="user" class="h-5 w-5" /></span>
                        <div>
                            <h2>Información personal</h2>
                            <p>Completa los datos del trabajador.</p>
                        </div>
                    </div>

                    <div class="nt-grid">
                        <x-forms.campo-icono name="nombres" label="Nombres" icono="user" placeholder="Ej. Juan Carlos" required autocomplete="given-name" />
                        <x-forms.campo-icono name="apellidos" label="Apellidos" icono="user" placeholder="Ej. Quispe Mamani" required autocomplete="family-name" />
                        <x-forms.campo-icono name="dni" label="DNI" icono="id-card" placeholder="Ej. 12345678" required inputmode="numeric" maxlength="8" pattern="\d{8}" title="8 dígitos" />
                        <x-forms.campo-icono name="telefono" label="Teléfono" icono="phone" type="tel" placeholder="Ej. 987 654 321" autocomplete="tel" />
                        {{-- Solo el acopiador tiene zona: para los demás roles el campo queda oculto y deshabilitado (no se envía). --}}
                        <x-forms.campo-icono name="zona_id" label="Zona" icono="map-pin" :options="$zonas" placeholder="Selecciona una zona" required data-campo="zona"
                            :hidden="! $actual['conZona']" :disabled="! $actual['conZona']" />
                    </div>
                </section>

                <section class="nt-seccion is-cuenta">
                    <div class="nt-seccion-head">
                        <span class="nt-seccion-icono"><x-ui.icon name="lock" class="h-5 w-5" /></span>
                        <div>
                            <h2>Cuenta de acceso</h2>
                            <p>Este usuario podrá acceder al sistema con estas credenciales.</p>
                        </div>
                    </div>

                    <div class="nt-grid">
                        <x-forms.campo-icono name="username" label="Usuario" icono="user" placeholder="Ej. juan.quispe" required autocomplete="off" />
                        <x-forms.campo-icono name="password" label="Contraseña" icono="lock" type="password" placeholder="Mínimo 8 caracteres" required minlength="8" autocomplete="new-password" />
                    </div>
                </section>

                <footer class="nt-footer">
                    <a href="{{ route($actual['listado']) }}" class="fichas-btn is-outline" data-nuevo-trabajador-cancelar>Cancelar</a>
                    <button type="submit" class="fichas-btn is-primary">
                        <x-ui.icon name="check" class="h-4 w-4" /> Registrar trabajador
                    </button>
                </footer>
            </form>

            <aside class="nt-lateral" aria-label="Ayuda">
                <div class="nt-aviso">
                    <span class="nt-aviso-icono"><x-ui.icon name="info" class="h-5 w-5" /></span>
                    <div>
                        <h3>Información importante</h3>
                        <p>Asegúrate de completar correctamente todos los datos. El trabajador podrá acceder al sistema con el usuario y contraseña que registres.</p>
                    </div>
                </div>

                <div class="nt-roles-info">
                    <h3>Roles del sistema</h3>
                    <ul>
                        @foreach ($tipos as $opcion)
                            <li>
                                <span class="nt-icono-rol is-{{ $opcion['tono'] }}"><x-ui.icon :name="$opcion['icono']" class="h-4 w-4" /></span>
                                <span>
                                    <strong>{{ $opcion['etiqueta'] }}</strong>
                                    <span>{{ $opcion['detalle'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </aside>
        </div>
    </div>
@endsection
