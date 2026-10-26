{{--
    CONFIGURACIÓN DE PAGOS: solo los valores base que usa Pagos, en dos pestañas.
    - Trabajadores: sueldo mensual de cada trabajador registrado (tabla + panel lateral, patrón
      admin-fichas; ?trabajador=ID mantiene la selección). El valor por día se calcula (sueldo / 30).
    - Proveedores: configuración GENERAL (precio por litro y descuento por la semana OBSERVADA).
    Nada de asistencia, entregas ni pagos realizados: eso vive en Pagos.
--}}
@extends('layouts.admin')

@section('title', 'Configuración de pagos')
@section('body-class', 'admin-fichas-page')
@section('contenedor', 'max-w-[1600px]')

@php
    $trabajadores = $datos['trabajadores'];
    $seleccionadoId = $trabajador['userId'] ?? null;
    $pestanas = ['trabajadores' => 'Trabajadores', 'proveedores' => 'Proveedores'];
@endphp

@section('content')
    <div class="fichas config-pagos" data-config-pagos-page>
        <header class="fichas-header">
            <div class="min-w-0">
                <x-layout.breadcrumbs :items="[['Configuración', null], ['Configuración de pagos', null]]" />
                <h1 class="fichas-title">Configuración de pagos</h1>
                <p class="fichas-subtitle">Define los valores base que EcoLact usa para calcular pagos.</p>
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

        {{-- TRABAJADORES: cuánto gana cada trabajador --}}
        <section id="seccion-trabajadores" role="tabpanel" aria-labelledby="tab-trabajadores" data-config-seccion="trabajadores" @if ($pestana !== 'trabajadores') hidden @endif>
            @if (count($trabajadores) === 0)
                <x-ui.empty-state class="mt-6 bg-surface" title="Todavía no hay trabajadores registrados." description="Registra trabajadores en Trabajadores > Nuevo trabajador y aparecerán aquí para configurar su sueldo." />
            @else
                <div class="fichas-layout" data-fichas-page data-fichas-param="trabajador">
                    <div>
                        <p class="config-pagos-ayuda">Sueldo mensual de cada trabajador. El valor por día se calcula sobre 30 días.</p>
                        <div class="fichas-tabla is-loading" data-fichas-tabla>
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">Trabajador</th>
                                        <th scope="col" class="hidden md:table-cell">Rol</th>
                                        <th scope="col" class="is-num">Sueldo mensual</th>
                                        <th scope="col" class="is-num hidden sm:table-cell">Valor/día</th>
                                        <th scope="col" class="hidden lg:table-cell">Vigente desde</th>
                                        <th scope="col" class="hidden sm:table-cell">Estado</th>
                                        <th scope="col" class="is-accion sm:hidden"><span class="sr-only">Ver detalle</span></th>
                                    </tr>
                                </thead>
                                <tbody class="fichas-skeleton" aria-hidden="true">
                                    @for ($i = 0; $i < min(8, count($trabajadores)); $i++)
                                        <tr>
                                            <td><span class="fichas-bone is-lg"></span></td>
                                            <td class="hidden md:table-cell"><span class="fichas-bone is-md"></span></td>
                                            <td><span class="fichas-bone is-sm ml-auto"></span></td>
                                            <td class="hidden sm:table-cell"><span class="fichas-bone is-sm ml-auto"></span></td>
                                            <td class="hidden lg:table-cell"><span class="fichas-bone is-md"></span></td>
                                            <td class="hidden sm:table-cell"><span class="fichas-bone is-sm"></span></td>
                                            <td class="sm:hidden"></td>
                                        </tr>
                                    @endfor
                                </tbody>
                                <tbody data-fichas-filas>
                                    @foreach ($trabajadores as $fila)
                                        @php $configurado = $fila['sueldo'] !== null; @endphp
                                        <tr data-fichas-fila data-ficha-id="{{ $fila['userId'] }}" data-url-panel="{{ route('admin.configuracion-pagos.trabajadores.panel', $fila['userId']) }}" tabindex="0" title="Ver detalle" aria-selected="{{ $fila['userId'] === $seleccionadoId ? 'true' : 'false' }}" @class(['is-selected' => $fila['userId'] === $seleccionadoId])>
                                            <td class="is-trabajador">
                                                <a href="{{ route('admin.configuracion-pagos.index', ['tab' => 'trabajadores', 'trabajador' => $fila['userId']]) }}" class="fichas-nombre" data-fichas-seleccionar tabindex="-1">{{ $fila['nombre'] }}</a>
                                                <p class="config-pagos-meta md:hidden">{{ $fila['rol'] }}</p>
                                            </td>
                                            <td class="is-muted hidden md:table-cell">{{ $fila['rol'] }}</td>
                                            <td @class(['is-num', 'is-muted' => ! $configurado])>{{ $configurado ? 'S/ '.number_format($fila['sueldo'], 2) : 'Sin configurar' }}</td>
                                            <td class="is-num is-muted hidden sm:table-cell">{{ $configurado ? 'S/ '.number_format($fila['valorDia'], 2) : '—' }}</td>
                                            <td class="is-muted hidden lg:table-cell">{{ $fila['vigenteDesde'] ? \Illuminate\Support\Carbon::parse($fila['vigenteDesde'])->format('d/m/Y') : '—' }}</td>
                                            <td class="hidden sm:table-cell">@include('admin.configuracion-pagos._estado', ['estado' => $fila['estado']])</td>
                                            <td class="is-accion sm:hidden" aria-hidden="true"><x-ui.icon name="chevron-right" class="config-pagos-chevron h-4 w-4" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <noscript><style>.fichas-tabla.is-loading [data-fichas-filas] { display: table-row-group; } .fichas-tabla.is-loading .fichas-skeleton { display: none; }</style></noscript>
                    </div>

                    <aside class="fichas-panel" data-fichas-panel aria-live="polite" aria-label="Sueldo del trabajador">
                        @if ($trabajador)
                            @include('admin.configuracion-pagos._panel-trabajador', ['trabajador' => $trabajador])
                        @else
                            @include('admin.configuracion-pagos._panel-trabajador-vacio')
                        @endif
                    </aside>

                    <template data-fichas-panel-vacio>
                        @include('admin.configuracion-pagos._panel-trabajador-vacio')
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
            @endif
        </section>

        {{-- PROVEEDORES: precio por litro y calidad semanal --}}
        <section id="seccion-proveedores" role="tabpanel" aria-labelledby="tab-proveedores" data-config-seccion="proveedores" @if ($pestana !== 'proveedores') hidden @endif>
            @include('admin.configuracion-pagos._proveedores', ['proveedor' => $datos['proveedor']])
        </section>
    </div>
@endsection
