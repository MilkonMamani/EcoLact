@extends('layouts.admin')

@section('title', 'Control de asistencia')

@php
    use Carbon\CarbonImmutable;

    $fechaCarbon = CarbonImmutable::instance($fecha);
    $fechaEtiqueta = ($esHoy ? 'Hoy, ' : '').$fechaCarbon->locale('es')->translatedFormat('d \d\e F \d\e Y');
@endphp

@section('content')
    <div data-asistencia-page data-url-qr="{{ route('api.admin.asistencia.qr') }}" data-url-corregir-base="{{ url('admin/trabajadores') }}" class="space-y-5">
        <x-layout.breadcrumbs :items="[['Control de asistencia', null]]" />

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="rounded-lg p-2 text-text transition hover:bg-text-muted/10 lg:hidden">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                </button>
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary-soft text-primary-dark">
                    <x-ui.icon name="qr-code" class="h-7 w-7" />
                </span>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-text">Control de asistencia</h1>
                    <p class="mt-1.5 max-w-2xl text-sm text-label">Registra y consulta la asistencia diaria de los trabajadores.</p>
                </div>
            </div>

            <x-ui.button type="button" data-modal-open="modal-escanear-qr" class="shadow-sm">
                <x-ui.icon name="qr-code" class="h-4 w-4" /> Escanear QR
            </x-ui.button>
        </div>

        <x-ui.card>
            <form method="GET" action="{{ route('admin.asistencia.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_auto] lg:items-end">
                <x-forms.input name="q" label="Buscar trabajador" placeholder="Nombre o DNI" :value="request('q')" :sticky="false" />
                <x-forms.select name="rol" label="Rol" :options="collect($roles)->pluck('nombre', 'id')->all()" :value="request('rol')" placeholder="Todos" :sticky="false" />
                <x-forms.input name="fecha" type="date" label="Fecha" :value="$fecha->format('Y-m-d')" max="{{ now()->toDateString() }}" :sticky="false" />
                <x-ui.button type="submit">Filtrar</x-ui.button>
            </form>
        </x-ui.card>

        <div class="flex items-center gap-2 text-sm font-medium text-text">
            <x-ui.icon name="calendar" class="h-4 w-4 text-text-muted" />
            {{ ucfirst($fechaEtiqueta) }}
        </div>

        <x-ui.card :padded="false">
            @if (count($filas) === 0)
                <div class="p-5">
                    <x-ui.empty-state title="No se encontraron trabajadores" description="Prueba con otros filtros o con otra fecha." />
                </div>
            @else
                <div class="ecolact-table-scroll">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-text-muted/10 text-[11px] font-medium uppercase tracking-wider text-label">
                                <th class="py-3 pl-5 pr-3">Trabajador</th>
                                <th class="px-3 py-3">Rol</th>
                                <th class="px-3 py-3">DNI</th>
                                <th class="px-3 py-3">Asistencia</th>
                                <th class="px-3 py-3">Hora</th>
                                <th class="py-3 pl-3 pr-5">Origen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filas as $fila)
                                @php
                                    $estadoVisual = $fila->estado ?? ($fila->esHoy ? 'PENDIENTE_REGISTRO' : 'SIN_REGISTRO');
                                    $estadoTexto = match (true) {
                                        $fila->estado === 'PRESENTE' => 'Asistió',
                                        $fila->estado === 'FALTA' => 'Falta',
                                        $fila->esHoy => 'Pendiente de registro',
                                        default => 'Sin registrar',
                                    };
                                    $badgeStatus = $fila->estado ?? 'SIN_REGISTRO';
                                @endphp
                                <tr
                                    data-fila-asistencia
                                    data-trabajador-id="{{ $fila->trabajadorId }}"
                                    data-user-id="{{ $fila->userId }}"
                                    data-nombre="{{ $fila->nombre }}"
                                    data-fecha="{{ $fecha->format('Y-m-d') }}"
                                    data-estado="{{ $fila->estado ?? '' }}"
                                    data-corregible="{{ $fila->estado !== null ? 'true' : 'false' }}"
                                    title="{{ $fila->estado !== null ? 'Clic para corregir esta asistencia' : '' }}"
                                    class="border-b border-text-muted/5 transition-colors last:border-0 hover:bg-background/50 {{ $fila->estado !== null ? 'cursor-pointer' : '' }}"
                                >
                                    <td class="py-3 pl-5 pr-3">
                                        <div class="flex items-center gap-2.5">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-soft text-sm font-semibold text-primary-dark">{{ $fila->iniciales }}</span>
                                            <p class="truncate font-medium text-text">{{ $fila->nombre }}</p>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-text-muted">{{ $fila->rol }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-text-muted">{{ $fila->dni ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-3">
                                        <x-ui.badge :status="$badgeStatus" :icon="$fila->estado === 'PRESENTE' ? 'check-circle' : ($fila->estado === 'FALTA' ? 'x-circle' : 'clock')">{{ $estadoTexto }}</x-ui.badge>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-text-muted">{{ $fila->horaMarcacion ?? '—' }}</td>
                                    <td class="whitespace-nowrap py-3 pl-3 pr-5 text-text-muted">{{ $fila->origenEtiqueta ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>

        <p class="flex flex-wrap items-center gap-4 text-xs text-text-muted">
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-success"></span> Asistió</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-danger"></span> Falta</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-text-muted/40"></span> Sin registrar / Pendiente de registro</span>
        </p>
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

            <x-forms.select name="estado" label="Nuevo estado" required :options="['PRESENTE' => 'Asistió', 'FALTA' => 'Falta']" placeholder="" :sticky="false" data-corregir-estado />
            <x-forms.textarea name="motivo" label="Motivo" placeholder="Explica el motivo de la corrección" :rows="2" maxlength="255" required :sticky="false" />
            <p data-corregir-error hidden class="text-sm text-danger"></p>

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" data-corregir-submit>Guardar corrección</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
