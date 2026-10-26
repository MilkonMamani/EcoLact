@extends('layouts.admin')

@section('title', 'Pagos a trabajadores')
@section('body-class', 'admin-fichas-page pagos-trabajadores-page')
@section('contenedor', 'max-w-[1600px]')

{{--
    PAGOS A TRABAJADORES: mismo patrón que Pagar a proveedores.
    - La tabla sirve para REVISAR el mes de cada trabajador; el panel derecho para MOSTRAR su
      comprobante (vista previa real de la hoja A4) e imprimirlo o registrar el pago.
    - Clic en la fila = ver comprobante (admin-fichas.js: skeleton, entrada escalonada, panel por
      fetch con ?trabajador=ID). No hay pago masivo de trabajadores, así que no hay checkbox.
    - El sueldo se configura en Configuración de pagos y la asistencia en Control de asistencia:
      aquí no hay acciones de configuración. Todo lo que se pinta sale de TrabajadorPagoFila y
      TrabajadoresPagoResumen (snapshot de obligaciones_pago + jornadas_personal); no se calcula dinero.
--}}
@php
    $soles = fn ($valor): string => 'S/ '.number_format((float) $valor, 2);
    $hayFiltrosDeBusqueda = request()->anyFilled(['q', 'rol', 'estado']);
    $filtrosPanel = array_filter(request()->only(['q', 'rol', 'periodo', 'estado']), fn ($valor) => $valor !== null && $valor !== '');
    $seleccionadaClave = $seleccion?->clave();
    $haySinSueldo = collect($filas->items())->contains(fn ($fila): bool => $fila->sinSueldo());
    $estados = ['EN_CURSO' => 'En curso', 'PENDIENTE' => 'Pendiente', 'PAGADO' => 'Pagado', 'SIN_SUELDO' => 'Sin configurar'];
@endphp

@section('content')
    <div class="fichas pagos-trabajadores" data-fichas-page data-fichas-param="trabajador" data-pago-trabajadores data-url-ajuste="{{ route('admin.pagos.trabajadores.ajuste', '__ID__') }}">
        <div class="fichas-layout">
            <section class="fichas-lista" aria-label="Pagos a trabajadores">
                <header class="pt-header">
                    <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="rounded-lg p-2 text-text transition hover:bg-text-muted/10 lg:hidden">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                    </button>
                    <div class="min-w-0">
                        <x-layout.breadcrumbs :items="[['Finanzas', null], ['Pagos', null], ['Trabajadores', null]]" />
                        <h1 class="fichas-title">Pagos a trabajadores</h1>
                        <p class="fichas-subtitle">Revisa los pagos mensuales del personal y registra la entrega correspondiente.</p>
                    </div>
                </header>

                <form method="GET" action="{{ route('admin.pagos.trabajadores.index') }}" class="pt-filtros" data-fichas-filtros>
                    <label class="campo-icono-caja pt-buscador">
                        <span class="sr-only">Buscar trabajador por nombre o DNI</span>
                        <x-ui.icon name="magnifying-glass" class="campo-icono-simbolo h-[18px] w-[18px]" />
                        <input id="filtro-q" type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre o DNI..." autocomplete="off">
                    </label>

                    <div class="pt-filtros-fila">
                        <label>
                            <span class="sr-only">Mes</span>
                            <input id="filtro-periodo" type="month" name="periodo" value="{{ $periodo }}" max="{{ now()->format('Y-m') }}" class="pt-campo" title="Mes">
                        </label>
                        <label>
                            <span class="sr-only">Rol</span>
                            <select id="filtro-rol" name="rol" class="pt-campo">
                                <option value="">Todos los roles</option>
                                @foreach ($roles as $rol)
                                    <option value="{{ $rol['id'] }}" @selected((string) request('rol') === (string) $rol['id'])>{{ $rol['nombre'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span class="sr-only">Estado</span>
                            <select id="filtro-estado" name="estado" class="pt-campo">
                                <option value="">Todos los estados</option>
                                @foreach ($estados as $valor => $etiqueta)
                                    <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="fichas-btn is-primary pt-filtro-boton">Filtrar</button>
                        <a href="{{ route('admin.pagos.trabajadores.index') }}" class="fichas-btn is-outline pt-filtro-boton">Limpiar</a>
                    </div>
                </form>

                <dl class="pt-metricas" aria-label="Resumen de {{ $periodoEtiqueta }}">
                    <div><dt>Periodo</dt><dd>{{ $periodoEtiqueta }}</dd></div>
                    <div><dt>Trabajadores</dt><dd>{{ $resumen->trabajadores }}</dd></div>
                    <div><dt>Pendientes</dt><dd>{{ $resumen->pendientes }}</dd></div>
                    <div><dt>Pagados</dt><dd>{{ $resumen->pagados }}</dd></div>
                    <div><dt>Total pendiente</dt><dd>{{ $soles($resumen->totalPendiente) }}</dd></div>
                </dl>

                {{-- Avisos: una sola línea discreta, nunca un bloque grande ni un enlace para configurar sueldos. --}}
                @if ($diagnostico && $filas->total() > 0)
                    <p class="pt-aviso" data-diagnostico-sueldos><x-ui.icon name="info" class="h-4 w-4" /> {{ $diagnostico->mensaje() }} Los trabajadores sin sueldo configurado se muestran sin monto.</p>
                @elseif ($haySinSueldo)
                    <p class="pt-aviso"><x-ui.icon name="info" class="h-4 w-4" /> Los trabajadores sin sueldo configurado se muestran sin monto.</p>
                @endif

                <div class="fichas-tabla pagos-tabla pt-tabla is-loading" data-fichas-tabla>
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Trabajador</th>
                                <th scope="col">Rol</th>
                                <th scope="col" class="is-num">Asistencias</th>
                                <th scope="col" class="is-num">Faltas</th>
                                <th scope="col" class="is-num">Sueldo mensual</th>
                                <th scope="col" class="is-num">Valor/día</th>
                                <th scope="col" class="is-num">Acumulado</th>
                                <th scope="col" class="is-num">Total</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="fichas-skeleton" aria-hidden="true">
                            @for ($i = 0; $i < min(8, max(1, $filas->count())); $i++)
                                <tr>
                                    <td><span class="flex items-center gap-2.5"><span class="fichas-bone is-avatar-sm"></span><span class="fichas-bone is-lg"></span></span></td>
                                    @foreach (['is-md', 'is-xs', 'is-xs', 'is-sm', 'is-sm', 'is-sm', 'is-sm', 'is-pill'] as $ancho)
                                        <td><span class="fichas-bone {{ $ancho }}"></span></td>
                                    @endforeach
                                </tr>
                            @endfor
                        </tbody>
                        <tbody data-fichas-filas>
                            @forelse ($filas as $fila)
                                @php
                                    $urlPanel = $fila->sinSueldo()
                                        ? route('admin.pagos.trabajadores.sin-sueldo', ['trabajador' => $fila->trabajadorId, 'periodo' => $periodo] + $filtrosPanel)
                                        : route('admin.pagos.trabajadores.panel', ['obligacion' => $fila->id] + $filtrosPanel);
                                    $seleccionada = $fila->clave() === $seleccionadaClave;
                                @endphp
                                <tr
                                    data-fichas-fila
                                    data-ficha-id="{{ $fila->clave() }}"
                                    data-url-panel="{{ $urlPanel }}"
                                    tabindex="0"
                                    title="Ver comprobante"
                                    aria-selected="{{ $seleccionada ? 'true' : 'false' }}"
                                    @class(['is-selected' => $seleccionada, 'is-sin-sueldo' => $fila->sinSueldo()])
                                >
                                    <td>
                                        <span class="pagos-trabajador">
                                            <span class="pagos-avatar" aria-hidden="true">{{ $fila->iniciales }}</span>
                                            <span class="min-w-0">
                                                <a href="{{ request()->fullUrlWithQuery(['trabajador' => $fila->clave()]) }}" class="fichas-nombre" data-fichas-seleccionar tabindex="-1">{{ $fila->nombre }}</a>
                                                <span class="pagos-dni">{{ $fila->dni ? 'DNI '.$fila->dni : 'Sin DNI' }}</span>
                                            </span>
                                        </span>
                                    </td>
                                    <td class="is-muted">{{ $fila->rol }}</td>
                                    <td class="is-num">{{ $fila->diasTrabajados }}</td>
                                    <td @class(['is-num', 'pagos-faltas' => $fila->faltas > 0])>{{ $fila->faltas }}</td>
                                    @if ($fila->sinSueldo())
                                        <td class="is-num pt-sin-configurar">Sin configurar</td>
                                        <td class="is-num is-muted">—</td>
                                        <td class="is-num is-muted">—</td>
                                        <td class="is-num is-muted" data-celda-total>—</td>
                                    @else
                                        <td class="is-num">{{ $soles($fila->sueldoBase) }}</td>
                                        <td class="is-num is-muted">{{ $soles($fila->valorDia) }}</td>
                                        <td class="is-num">{{ $soles($fila->acumulado) }}</td>
                                        <td class="is-num pagos-total" data-celda-total>{{ $soles($fila->total) }}</td>
                                    @endif
                                    <td><x-pagos.estado-pago :estado="$fila->estado" /></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="pt-tabla-vacia">
                                        @if ($filas->total() > 0)
                                            No hay trabajadores en esta página. <a href="{{ $filas->url(1) }}">Ir a la primera página</a>
                                        @elseif ($hayFiltrosDeBusqueda)
                                            <strong>No se encontraron trabajadores</strong> Prueba con otros filtros.
                                        @else
                                            <strong>{{ $diagnostico?->mensaje() ?? 'No hay trabajadores para este periodo.' }}</strong> Periodo: {{ $periodoEtiqueta }}.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <noscript><style>.fichas-tabla.is-loading [data-fichas-filas] { display: table-row-group; } .fichas-tabla.is-loading .fichas-skeleton { display: none; }</style></noscript>

                <x-tables.pagination :paginator="$filas" noun="trabajadores" class="fichas-pager" />
            </section>

            <aside class="fichas-panel pt-panel" data-fichas-panel aria-live="polite" aria-label="Comprobante del trabajador">
                @include('admin.pagos.trabajadores._panel')
            </aside>
        </div>

        <div class="pt-velo" data-panel-velo hidden></div>

        <template data-fichas-panel-vacio>
            @include('admin.pagos.trabajadores._panel', ['seleccion' => null, 'comprobante' => null])
        </template>

        <template data-fichas-panel-skeleton>
            <x-fichas.panel-skeleton />
        </template>
    </div>
@endsection
