{{--
    CONFIGURACIÓN DE PAGOS: solo los valores base que usa Pagos, en dos pestañas.
    - Trabajadores: sueldo base mensual POR ROL laboral; todo el personal activo del rol lo hereda
      (indicadores + tabla + panel lateral, patrón admin-fichas; ?rol=ID mantiene la selección).
      El valor por día es informativo (sueldo / 30). Los formularios de cada rol viven aquí y se
      abren desde la fila o desde el panel.
    - Proveedores: configuración GENERAL (precio por litro y descuento por la semana OBSERVADA).
    Nada de asistencia, entregas ni pagos realizados: eso vive en Pagos.
--}}
@extends('layouts.admin')

@section('title', 'Configuración de pagos')
@section('body-class', 'admin-fichas-page')
@section('contenedor', 'max-w-[1600px]')

@php
    $roles = $datos['trabajadores']['roles'];
    $resumen = $datos['trabajadores']['resumen'];
    $seleccionadoId = $rolSeleccionado['rolId'] ?? null;
    $pestanas = ['trabajadores' => 'Trabajadores', 'proveedores' => 'Proveedores'];
    $puedeConfigurar = auth()->user()->can('create', \App\Infrastructure\Persistence\Models\ConfiguracionPagoModel::class);
    $fecha = fn (?string $valor): string => $valor ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y') : '—';
    $soles = fn (?float $valor): string => 'S/ '.number_format((float) $valor, 2);
@endphp

@section('content')
    <div class="fichas config-pagos" data-config-pagos-page>
        <header class="fichas-header">
            <div class="min-w-0">
                <x-layout.breadcrumbs :items="[['Configuración', null], ['Configuración de pagos', null]]" />
                <h1 class="fichas-title">Configuración de pagos</h1>
                <p class="fichas-subtitle">Administra los sueldos base por rol y las condiciones económicas aplicables en EcoLact.</p>
            </div>
        </header>

        @error('configuracion')
            <p class="config-pagos-error" role="alert">{{ $message }}</p>
        @enderror

        <nav class="config-pagos-tabs" role="tablist" aria-label="Tipo de configuración">
            @foreach ($pestanas as $clave => $etiqueta)
                <button type="button" role="tab" id="tab-{{ $clave }}" aria-controls="seccion-{{ $clave }}" aria-selected="{{ $pestana === $clave ? 'true' : 'false' }}" data-config-tab="{{ $clave }}">
                    {{ $etiqueta }}
                </button>
            @endforeach
        </nav>

        {{-- TRABAJADORES: sueldo base de cada rol laboral --}}
        <section id="seccion-trabajadores" role="tabpanel" aria-labelledby="tab-trabajadores" data-config-seccion="trabajadores" @if ($pestana !== 'trabajadores') hidden @endif>
            <dl class="config-roles-kpis" aria-label="Resumen de sueldos por rol">
                <div>
                    <span class="config-roles-kpi-icono"><x-ui.icon name="users" class="h-4 w-4" /></span>
                    <dt>Roles laborales</dt>
                    <dd>{{ $resumen['roles'] }}</dd>
                </div>
                <div class="is-ok">
                    <span class="config-roles-kpi-icono"><x-ui.icon name="check-circle" class="h-4 w-4" /></span>
                    <dt>Con sueldo configurado</dt>
                    <dd>{{ $resumen['configurados'] }}</dd>
                </div>
                <div @class(['is-alerta' => $resumen['pendientes'] > 0])>
                    <span class="config-roles-kpi-icono"><x-ui.icon name="clock" class="h-4 w-4" /></span>
                    <dt>Pendientes de configuración</dt>
                    <dd>{{ $resumen['pendientes'] }}</dd>
                </div>
            </dl>

            @if (count($roles) === 0)
                <x-ui.empty-state class="mt-6 bg-surface" title="No hay roles laborales remunerables." description="Los sueldos se configuran por rol. Revisa en la base de datos qué roles cobran sueldo (los proveedores cobran por litros)." />
            @else
                <div class="fichas-layout" data-fichas-page data-fichas-param="rol">
                    <div class="min-w-0">
                        <p class="config-pagos-ayuda">Cada rol tiene un sueldo base mensual que hereda automáticamente todo su personal activo. El valor diario es referencial (sueldo ÷ 30).</p>
                        <div class="fichas-tabla is-loading" data-fichas-tabla>
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">Rol</th>
                                        <th scope="col" class="is-num hidden md:table-cell">Personal asignado</th>
                                        <th scope="col" class="is-num">Sueldo mensual</th>
                                        <th scope="col" class="is-num hidden sm:table-cell">Valor diario</th>
                                        <th scope="col" class="hidden lg:table-cell">Vigente desde</th>
                                        <th scope="col" class="hidden sm:table-cell">Estado</th>
                                        <th scope="col" class="is-accion"><span class="sr-only">Acción</span></th>
                                    </tr>
                                </thead>
                                <tbody class="fichas-skeleton" aria-hidden="true">
                                    @foreach ($roles as $fila)
                                        <tr>
                                            <td><span class="fichas-bone is-lg"></span></td>
                                            <td class="hidden md:table-cell"><span class="fichas-bone is-sm ml-auto"></span></td>
                                            <td><span class="fichas-bone is-sm ml-auto"></span></td>
                                            <td class="hidden sm:table-cell"><span class="fichas-bone is-sm ml-auto"></span></td>
                                            <td class="hidden lg:table-cell"><span class="fichas-bone is-md"></span></td>
                                            <td class="hidden sm:table-cell"><span class="fichas-bone is-sm"></span></td>
                                            <td></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tbody data-fichas-filas>
                                    @foreach ($roles as $fila)
                                        @php $configurado = $fila['sueldo'] !== null; @endphp
                                        <tr data-fichas-fila data-ficha-id="{{ $fila['rolId'] }}" data-url-panel="{{ route('admin.configuracion-pagos.roles.panel', $fila['rolId']) }}" tabindex="0" title="Ver detalle del rol" aria-selected="{{ $fila['rolId'] === $seleccionadoId ? 'true' : 'false' }}" @class(['is-selected' => $fila['rolId'] === $seleccionadoId])>
                                            <td class="is-trabajador">
                                                <a href="{{ route('admin.configuracion-pagos.index', ['tab' => 'trabajadores', 'rol' => $fila['rolId']]) }}" class="fichas-nombre" data-fichas-seleccionar tabindex="-1">{{ $fila['rol'] }}</a>
                                                <p class="config-pagos-meta md:hidden">{{ $fila['personal'] }} {{ $fila['personal'] === 1 ? 'persona' : 'personas' }}</p>
                                            </td>
                                            <td class="is-num hidden md:table-cell"><span class="config-roles-personal">{{ $fila['personal'] }}</span></td>
                                            <td @class(['is-num', 'font-semibold' => $configurado, 'is-muted' => ! $configurado])>{{ $configurado ? $soles($fila['sueldo']) : 'Sin configurar' }}</td>
                                            <td class="is-num is-muted hidden sm:table-cell">{{ $configurado ? $soles($fila['valorDia']) : '—' }}</td>
                                            <td class="is-muted hidden lg:table-cell">{{ $fecha($fila['vigenteDesde']) }}</td>
                                            <td class="hidden sm:table-cell">@include('admin.configuracion-pagos._estado', ['estado' => $fila['estado']])</td>
                                            <td class="is-accion">
                                                @if ($puedeConfigurar)
                                                    <button type="button" class="fichas-btn is-outline is-sm" data-modal-open="modal-sueldo-rol-{{ $fila['rolId'] }}">Configurar</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <noscript><style>.fichas-tabla.is-loading [data-fichas-filas] { display: table-row-group; } .fichas-tabla.is-loading .fichas-skeleton { display: none; }</style></noscript>
                    </div>

                    <aside class="fichas-panel" data-fichas-panel aria-live="polite" aria-label="Sueldo del rol">
                        @include('admin.configuracion-pagos._panel-rol', ['rol' => $rolSeleccionado])
                    </aside>

                    <template data-fichas-panel-vacio>
                        @include('admin.configuracion-pagos._panel-rol', ['rol' => null])
                    </template>

                    <template data-fichas-panel-skeleton>
                        <div class="fichas-panel-cargando" aria-busy="true" aria-label="Cargando">
                            <div class="fichas-panel-head">
                                <div class="grid flex-1 gap-2.5">
                                    <span class="fichas-bone is-xl"></span>
                                    <span class="fichas-bone is-md"></span>
                                </div>
                            </div>
                            <div class="fichas-panel-cuerpo grid gap-4">
                                @foreach (['is-md', 'is-xl', 'is-lg', 'is-md', 'is-xl'] as $ancho)
                                    <span class="fichas-bone {{ $ancho }}"></span>
                                @endforeach
                            </div>
                        </div>
                    </template>
                </div>

                @if ($puedeConfigurar)
                    @foreach ($roles as $fila)
                        @include('admin.configuracion-pagos._form-sueldo-rol', ['fila' => $fila])
                    @endforeach
                @endif
            @endif
        </section>

        {{-- PROVEEDORES: precio por litro y calidad semanal --}}
        <section id="seccion-proveedores" role="tabpanel" aria-labelledby="tab-proveedores" data-config-seccion="proveedores" @if ($pestana !== 'proveedores') hidden @endif>
            @include('admin.configuracion-pagos._proveedores', ['proveedor' => $datos['proveedor']])
        </section>
    </div>
@endsection
