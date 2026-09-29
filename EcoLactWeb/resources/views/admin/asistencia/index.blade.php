@extends('layouts.admin')

@section('title', $esHoy ? 'Asistencia de hoy' : 'Asistencia')
@section('body-class', 'admin-acopio-page')
@section('contenedor', 'max-w-[1400px]')

@php
    $filas = $datos->filas;
    $resumen = $datos->resumen;
    $filtrosActivos = request()->anyFilled(['q', 'rol']);
    $estados = [
        'PRESENTE' => ['Presente', 'check-circle'],
        'FALTA' => ['Falta', 'x-circle'],
    ];
    $origenes = [
        'QR' => ['QR', 'qr-code'],
        'MANUAL' => ['Manual', 'user'],
        'AUTOMATICO' => ['Automático', 'clock'],
        'CORREGIDO' => ['Corregido', 'pen-line'],
    ];
    $plural = fn (int $n, string $uno, string $varios): string => $n === 1 ? $uno : $varios;
@endphp

@section('content')
    <div class="fichas asistencia" data-asistencia-page data-url-qr="{{ route('api.admin.asistencia.qr') }}" data-url-corregir-base="{{ url('admin/trabajadores') }}">
        <header class="fichas-header">
            <div class="flex min-w-0 items-start gap-3">
                <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="rounded-lg p-2 text-text transition hover:bg-text-muted/10 lg:hidden">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                </button>
                <div class="min-w-0">
                    <x-layout.breadcrumbs :items="[['Dashboard', null], ['Asistencia', null]]" />
                    <h1 class="fichas-title">{{ $esHoy ? 'Asistencia de hoy' : 'Consulta de asistencia' }}</h1>
                    <p class="fichas-subtitle">Registra y consulta la asistencia diaria del personal.</p>
                    <p class="asistencia-fecha">
                        <x-ui.icon name="calendar" class="h-4 w-4" />
                        <span>{{ $fechaEtiqueta }}</span>
                    </p>
                </div>
            </div>

            @if ($asistenciaAbierta)
                <button type="button" data-modal-open="modal-escanear-qr" class="fichas-btn is-primary">
                    <x-ui.icon name="qr-code" class="h-4 w-4" /> Escanear QR
                </button>
            @elseif ($esHoy)
                <span class="asistencia-cerrada" data-asistencia-cerrada><x-ui.icon name="clock" class="h-4 w-4" /> Cerrada a las {{ $horaCierre }} a. m.</span>
            @else
                <a href="{{ route('admin.asistencia.index', array_filter(['q' => request('q'), 'rol' => request('rol')])) }}" class="fichas-btn is-outline" data-asistencia-volver-hoy>Volver a hoy</a>
            @endif
        </header>

        <div class="asistencia-resumen" aria-label="Resumen de asistencia">
            <span class="asistencia-dato"><x-ui.icon name="users" class="h-4 w-4 is-total" /><strong>{{ $resumen->trabajadores }}</strong> {{ $plural($resumen->trabajadores, 'trabajador', 'trabajadores') }}</span>
            <span class="asistencia-dato"><x-ui.icon name="check-circle" class="h-4 w-4 is-presente" /><strong>{{ $resumen->presentes }}</strong> {{ $plural($resumen->presentes, 'presente', 'presentes') }}</span>
            <span class="asistencia-dato"><x-ui.icon name="x-circle" class="h-4 w-4 is-falta" /><strong>{{ $resumen->faltas }}</strong> {{ $plural($resumen->faltas, 'falta', 'faltas') }}</span>
            <span class="asistencia-dato"><x-ui.icon name="clock" class="h-4 w-4 is-pendiente" /><strong>{{ $resumen->sinRegistro }}</strong> {{ $plural($resumen->sinRegistro, 'pendiente', 'pendientes') }}</span>
        </div>

        <form method="GET" action="{{ route('admin.asistencia.index') }}" class="asistencia-filtros" data-asistencia-filtros>
            <x-forms.input name="q" label="Buscar trabajador" placeholder="Nombre o DNI..." :value="request('q')" :sticky="false" autocomplete="off" />
            <x-forms.select name="rol" label="Rol" :options="collect($roles)->pluck('nombre', 'id')->all()" :value="request('rol')" placeholder="Todos" :sticky="false" />
            <x-forms.input name="fecha" type="date" label="Fecha" :value="$fecha->format('Y-m-d')" max="{{ now()->toDateString() }}" :sticky="false" />
            <button type="submit" class="fichas-btn is-dark">Filtrar</button>
        </form>

        @if ($filas->total() === 0)
            <div class="fichas-vacio">
                <x-ui.icon name="users" class="h-6 w-6" />
                <div>
                    <p class="font-semibold text-text">{{ $filtrosActivos ? 'Ningún trabajador coincide con los filtros' : 'No hay personal que marque asistencia en esta fecha' }}</p>
                    <p>{{ $filtrosActivos ? 'Prueba con otra búsqueda, otro rol u otra fecha.' : 'Registra trabajadores desde Personal → Trabajadores.' }}</p>
                </div>
            </div>
        @else
            <div class="fichas-tabla asistencia-tabla is-loading" data-asistencia-tabla>
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Trabajador</th>
                            <th scope="col">Rol</th>
                            <th scope="col">DNI</th>
                            <th scope="col">Asistencia</th>
                            <th scope="col">Hora</th>
                            <th scope="col">Origen</th>
                            <th scope="col" class="is-accion"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="fichas-skeleton" aria-hidden="true">
                        @for ($i = 0; $i < min(8, $filas->count()); $i++)
                            <tr>
                                <td><span class="flex items-center gap-2.5"><span class="fichas-bone is-avatar-sm"></span><span class="fichas-bone is-lg"></span></span></td>
                                @foreach (['is-md', 'is-md', 'is-pill', 'is-xs', 'is-md', 'is-dot'] as $ancho)
                                    <td><span class="fichas-bone {{ $ancho }}"></span></td>
                                @endforeach
                            </tr>
                        @endfor
                    </tbody>
                    <tbody data-fichas-filas>
                        @forelse ($filas as $fila)
                            @php
                                [$estadoTexto, $estadoIcono] = $estados[$fila->estado] ?? ($asistenciaAbierta ? ['Pendiente', 'clock'] : ['Sin registro', 'clock']);
                                [$origenTexto, $origenIcono] = $origenes[$fila->origen] ?? [null, null];
                            @endphp
                            <tr
                                data-fila-asistencia
                                data-trabajador-id="{{ $fila->trabajadorId }}"
                                data-user-id="{{ $fila->userId }}"
                                data-nombre="{{ $fila->nombre }}"
                                data-fecha="{{ $fecha->format('Y-m-d') }}"
                                data-fecha-etiqueta="{{ $fechaEtiqueta }}"
                                data-estado="{{ $fila->estado ?? '' }}"
                                data-estado-etiqueta="{{ $estadoTexto }}"
                                data-hora="{{ $fila->horaMarcacion ?? '' }}"
                                data-origen-etiqueta="{{ $origenTexto ?? '' }}"
                            >
                                <td>
                                    <span class="asistencia-trabajador">
                                        <span class="asistencia-avatar" aria-hidden="true">{{ $fila->iniciales }}</span>
                                        <span class="fichas-nombre">{{ $fila->nombre }}</span>
                                    </span>
                                </td>
                                <td class="is-muted">{{ $fila->rol }}</td>
                                <td class="is-muted">{{ $fila->dni ?? '—' }}</td>
                                <td><x-ui.badge compact :status="$fila->estado ?? 'SIN_REGISTRO'" :icon="$estadoIcono">{{ $estadoTexto }}</x-ui.badge></td>
                                <td class="asistencia-hora">{{ $fila->horaMarcacion ?? '—' }}</td>
                                <td class="is-muted">
                                    @if ($origenTexto)
                                        <span class="asistencia-origen"><x-ui.icon :name="$origenIcono" class="h-3.5 w-3.5" /> {{ $origenTexto }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="is-accion">
                                    <details class="asistencia-acciones">
                                        <summary aria-label="Acciones para {{ $fila->nombre }}" title="Acciones">
                                            <x-ui.icon name="ellipsis" class="h-4 w-4 rotate-90" />
                                        </summary>
                                        <div class="asistencia-menu" role="menu">
                                            @if (! $asistenciaAbierta)
                                                <button type="button" role="menuitem" data-asistencia-ver>
                                                    <x-ui.icon name="eye" class="h-4 w-4" /> Ver detalle
                                                </button>
                                            @elseif ($fila->estado !== null)
                                                <button type="button" role="menuitem" data-asistencia-corregir>
                                                    <x-ui.icon name="pen-line" class="h-4 w-4" /> Editar asistencia
                                                </button>
                                            @else
                                                <button type="button" role="menuitem" data-asistencia-registrar>
                                                    <x-ui.icon name="user" class="h-4 w-4" /> Registrar asistencia
                                                </button>
                                            @endif
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="fichas-sin-pagina">No hay trabajadores en esta página. <a href="{{ $filas->url(1) }}">Ir a la primera página</a></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <noscript><style>.fichas-tabla.is-loading [data-fichas-filas] { display: table-row-group; } .fichas-tabla.is-loading .fichas-skeleton { display: none; }</style></noscript>

            <x-tables.pagination :paginator="$filas" noun="trabajadores" class="fichas-pager" />
        @endif
    </div>

    <x-ui.modal id="modal-escanear-qr" title="Registrar asistencia por QR">
        <div data-escaner-qr class="space-y-4">
            <p class="text-sm text-text-muted">Apunta la cámara al código QR del trabajador.</p>

            <div class="relative flex aspect-square w-full items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-primary/30 bg-background/60">
                <video data-escaner-video autoplay playsinline muted class="h-full w-full object-cover" hidden></video>
                <p data-escaner-estado class="px-6 text-center text-sm text-text-muted">Esperando código…</p>
            </div>

            <p data-escaner-aviso hidden class="text-sm text-danger"></p>

            <div data-escaner-resultado hidden class="rounded-lg bg-success/10 px-4 py-3 text-sm text-success">
                <p class="font-medium" data-escaner-resultado-titulo>✓ Asistencia registrada</p>
                <p data-escaner-resultado-detalle class="mt-0.5 text-text-muted"></p>
            </div>

            <div data-escaner-requiere-correccion hidden class="space-y-2 rounded-lg bg-warning/10 px-4 py-3 text-sm text-warning">
                <p data-escaner-correccion-mensaje></p>
                <x-ui.button type="button" variant="secondary" size="sm" data-escaner-abrir-correccion>Ir a la lista para corregir</x-ui.button>
            </div>

            <div class="flex justify-between gap-2">
                <x-ui.button type="button" variant="ghost" data-modal-close>Cerrar</x-ui.button>
                <x-ui.button type="button" variant="secondary" data-escaner-reiniciar autofocus>Volver a escanear</x-ui.button>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal id="modal-corregir-asistencia" title="Corregir asistencia">
        <form method="POST" data-form-corregir class="space-y-4">
            @csrf
            <x-ui.data-field label="Trabajador"><span data-corregir-nombre></span></x-ui.data-field>
            <x-ui.data-field label="Fecha" muted><span data-corregir-fecha></span></x-ui.data-field>
            <x-ui.data-field label="Estado actual" muted><span data-corregir-estado-actual></span></x-ui.data-field>

            <x-forms.select name="estado" label="Nuevo estado" required :options="['PRESENTE' => 'Presente', 'FALTA' => 'Falta']" placeholder="" :sticky="false" data-corregir-estado />
            <x-forms.textarea name="motivo" label="Motivo" placeholder="Explica el motivo de la corrección" :rows="2" maxlength="255" required :sticky="false" />
            <p data-corregir-error hidden class="text-sm text-danger"></p>

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" data-corregir-submit>Guardar corrección</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Detalle de solo lectura (día ya cerrado a las 07:00); lo rellena admin-asistencia.js con los data-* de la fila. --}}
    <x-ui.modal id="modal-detalle-asistencia" title="Detalle de asistencia">
        <div class="space-y-3">
            <x-ui.data-field label="Trabajador"><span data-detalle-nombre></span></x-ui.data-field>
            <x-ui.data-field label="Fecha" muted><span data-detalle-fecha></span></x-ui.data-field>
            <div class="grid grid-cols-3 gap-3">
                <x-ui.data-field label="Estado"><span data-detalle-estado></span></x-ui.data-field>
                <x-ui.data-field label="Hora"><span data-detalle-hora></span></x-ui.data-field>
                <x-ui.data-field label="Origen"><span data-detalle-origen></span></x-ui.data-field>
            </div>
            <p class="text-xs text-text-muted">La asistencia se registra o corrige solo antes de las {{ $horaCierre }} a. m.; este día ya está cerrado.</p>
            <div class="flex justify-end pt-1">
                <x-ui.button type="button" data-modal-close variant="ghost">Cerrar</x-ui.button>
            </div>
        </div>
    </x-ui.modal>

    {{-- Registro manual (admin.trabajadores.jornadas.store → RegistrarJornadaTrabajador); la acción y la fecha las pone admin-asistencia.js. --}}
    <x-ui.modal id="modal-registrar-asistencia" title="Registrar asistencia manual">
        <form method="POST" action="" class="space-y-4">
            @csrf
            <input type="hidden" name="fecha" value="{{ $fecha->format('Y-m-d') }}">
            <x-ui.data-field label="Trabajador"><span data-registrar-nombre></span></x-ui.data-field>
            <x-ui.data-field label="Fecha" muted><span data-registrar-fecha></span></x-ui.data-field>
            <x-forms.textarea name="observacion" id="registrar-observacion" label="Observación (opcional)" :rows="2" maxlength="500" :sticky="false" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Registrar como presente</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
