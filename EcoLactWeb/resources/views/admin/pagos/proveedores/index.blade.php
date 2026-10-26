{{--
    PAGAR A PROVEEDORES: la tabla sirve para REVISAR las liquidaciones semanales; el panel derecho
    para ENTENDER el cálculo (comprobante) y ejecutar el pago.
    - Clic en la fila = ver su comprobante. Checkbox = marcarla para el pago masivo
      ("Revisar seleccionados"). Son dos cosas distintas y no se mezclan.
    - Todas las cifras (litros, resultado semanal, precio aplicado, compras, total) llegan del
      backend (PagoSemanalProveedoresQuery); aquí solo se muestran.
    Ver resources/js/pages/admin-pago-semanal.js.
--}}
@extends('layouts.admin')

@section('title', 'Pagar a proveedores')
@section('contenedor', 'max-w-[1560px]')
@section('body-class', 'pagos-proveedores-page')

@php
    $soles = fn ($valor): string => 'S/ '.number_format((float) $valor, 2);
    $kpis = $datos['kpis'];
    $filas = $datos['filas'];
    $semana = $datos['semana'];
    $estados = ['todos' => 'Todos los estados', 'pendientes' => 'Pendientes', 'pagados' => 'Pagados'];
    $puedePagar = auth()->user()?->can('pay', \App\Infrastructure\Persistence\Models\ObligacionPagoModel::class);
@endphp

@section('content')
    <div data-pago-semanal data-preview-url="{{ route('admin.pagos.proveedores.preview') }}" @if ($verId) data-ver-id="{{ $verId }}" @endif class="space-y-4">
        <x-layout.breadcrumbs :items="[['Pagos', null], ['Pago a proveedores', null]]" />

        <header class="flex items-center gap-4">
            <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="rounded-lg p-2 text-text transition hover:bg-text-muted/10 lg:hidden">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
            </button>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-text">Pago semanal a proveedores</h1>
                <p class="mt-1 text-sm text-label">Revisa las liquidaciones semanales y confirma los pagos correspondientes.</p>
            </div>
        </header>

        <dl class="pp-metricas">
            <div><dt>Pendientes</dt><dd>{{ $kpis['pendientes'] }} {{ $kpis['pendientes'] === 1 ? 'proveedor' : 'proveedores' }}</dd></div>
            <div><dt>Litros pagables</dt><dd>{{ number_format($kpis['litros'], 0) }} L</dd></div>
            <div><dt>Total pendiente</dt><dd>{{ $soles($kpis['totalPendiente']) }}</dd></div>
        </dl>

        <div class="pp-layout">
            <div class="min-w-0 space-y-3">
                {{-- Filtros --}}
                <form method="GET" action="{{ route('admin.pagos.proveedores.index') }}" data-filtros class="pp-filtros">
                    <input type="hidden" name="buscar" value="{{ $buscar }}" data-buscar-espejo>
                    {{-- Mismo patrón que x-forms.campo-icono (campo-icono.css): el ícono vive dentro de la caja, no encima del texto. --}}
                    <label class="campo-icono-caja pp-buscador">
                        <span class="sr-only">Buscar proveedor por nombre, DNI o zona</span>
                        <x-ui.icon name="magnifying-glass" class="campo-icono-simbolo h-[18px] w-[18px]" />
                        <input type="search" data-buscar value="{{ $buscar }}" placeholder="Buscar proveedor..." autocomplete="off">
                    </label>
                    <div class="pp-filtros-fila">
                        <label>
                            <span class="sr-only">Semana</span>
                            {{-- Cerrado muestra solo "Semana 40"; al abrir, cada opción muestra su rango (admin-pago-semanal.js). --}}
                            <select id="filtro-semana" name="semana" class="pp-campo" data-semana-select>
                                <option value="{{ \App\Application\Pago\PagoSemanalFiltro::SEMANA_TODAS }}" @selected($datos['todas']) data-corta="Todas las semanas" data-larga="Todas las semanas">Todas las semanas</option>
                                @foreach ($datos['semanas'] as $opcion)
                                    @php $elegida = ! $datos['todas'] && $opcion['inicio'] === $semana['inicio']; @endphp
                                    <option value="{{ $opcion['inicio'] }}" @selected($elegida) data-corta="{{ $opcion['etiqueta'] }}" data-larga="{{ $opcion['etiqueta'] }} · {{ $opcion['rango'] }}">{{ $elegida ? $opcion['etiqueta'] : $opcion['etiqueta'].' · '.$opcion['rango'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span class="sr-only">Zona</span>
                            <select id="filtro-zona" name="zona" class="pp-campo">
                                <option value="">Todas las zonas</option>
                                @foreach ($datos['zonas'] as $zona)
                                    <option value="{{ $zona['id'] }}" @selected($filtro->zonaId === $zona['id'])>{{ $zona['nombre'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span class="sr-only">Estado</span>
                            <select id="filtro-estado" name="estado" class="pp-campo">
                                @foreach ($estados as $valor => $etiqueta)
                                    <option value="{{ $valor }}" @selected($filtro->estado === $valor)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="pp-boton is-primario">Filtrar</button>
                        <a href="{{ route('admin.pagos.proveedores.index') }}" class="pp-boton">Limpiar</a>
                    </div>
                    @if (! $datos['todas'] && $datos['pendientesOtrasSemanas'] > 0)
                        <p class="text-xs text-warning">{{ $datos['pendientesOtrasSemanas'] }} pendiente(s) en otras semanas</p>
                    @endif
                </form>

                {{-- Liquidaciones --}}
                <section class="pp-tabla-tarjeta" aria-label="Liquidaciones semanales">
                    <div class="pp-tabla-barra">
                        <p class="text-sm font-semibold text-text">Liquidaciones semanales</p>
                        @if ($puedePagar)
                            <div class="flex items-center gap-2">
                                <button type="button" data-abrir-vista-previa disabled class="pp-boton is-primario is-sm">
                                    <x-ui.icon name="printer" class="h-4 w-4" /> Revisar seleccionados (<span data-contador-seleccion>0</span>)
                                </button>
                                <div class="relative">
                                    <button type="button" data-dropdown-toggle="menu-acciones-masivas" aria-label="Acciones masivas" class="pp-boton is-sm is-icono">
                                        <x-ui.icon name="ellipsis" class="h-5 w-5" />
                                    </button>
                                    <div id="menu-acciones-masivas" data-dropdown-menu class="absolute right-0 z-20 mt-1 w-60 rounded-lg border border-text-muted/15 bg-surface p-1 shadow-sm">
                                        <button type="button" data-seleccionar-pendientes class="block w-full rounded-md px-3 py-2 text-left text-sm text-text hover:bg-text-muted/10">Seleccionar pendientes visibles</button>
                                        <button type="button" data-limpiar class="block w-full rounded-md px-3 py-2 text-left text-sm text-text hover:bg-text-muted/10">Limpiar selección</button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="ecolact-table-scroll max-w-full overflow-x-auto">
                        <table class="pp-tabla-liquidaciones">
                            <thead>
                                <tr>
                                    <th scope="col" class="is-check">
                                        <input type="checkbox" data-marcar-todos aria-label="Marcar todos los pendientes para pago masivo" class="h-4 w-4 cursor-pointer rounded border-text-muted/40 accent-primary">
                                    </th>
                                    <th scope="col">Proveedor</th>
                                    <th scope="col" class="hidden md:table-cell">Zona</th>
                                    <th scope="col" class="is-num hidden sm:table-cell">Litros</th>
                                    <th scope="col" class="is-num hidden lg:table-cell">Precio/L</th>
                                    <th scope="col" class="hidden sm:table-cell">Calidad semanal</th>
                                    <th scope="col" class="is-num hidden lg:table-cell">Compras</th>
                                    <th scope="col" class="is-num">Total</th>
                                    <th scope="col">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filas as $fila)
                                    @php
                                        $noPagable = $fila['estado'] === 'NO_PAGABLE';
                                        $porDisponer = $fila['pendiente'] && ! $fila['pagable'];
                                    @endphp
                                    <tr data-fila data-id="{{ $fila['id'] }}" data-pendiente="{{ $fila['pagable'] ? 1 : 0 }}" data-estado="{{ $fila['estado'] }}"
                                        data-url-comprobante="{{ route('admin.pagos.proveedores.receipt', $fila['id']) }}"
                                        @if ($porDisponer) data-disponible="{{ $fila['fechaPagoProgramada'] }}" @endif
                                        data-total="{{ number_format($fila['total'], 2, '.', '') }}"
                                        data-buscar-texto="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($fila['proveedor'].' '.($fila['dni'] ?? '').' '.$fila['zona'].' '.($fila['zonaDetalle'] ?? '').' '.$fila['semanaEtiqueta'].' '.$fila['estadoEtiqueta'].' '.$fila['resultadoEtiqueta'])) }}"
                                        tabindex="0" title="Ver comprobante" aria-selected="false">
                                        <td class="is-check">
                                            <input type="checkbox" data-marcar value="{{ $fila['id'] }}" @disabled(! $fila['pagable']) aria-label="Marcar a {{ $fila['proveedor'] }} para pago masivo" class="h-4 w-4 rounded border-text-muted/40 accent-primary {{ $fila['pagable'] ? 'cursor-pointer' : 'cursor-not-allowed opacity-30' }}">
                                        </td>
                                        <td>
                                            <span data-proveedor-nombre class="block font-medium text-text">{{ $fila['proveedor'] }}</span>
                                            <span class="block text-xs text-text-muted">{{ $fila['semanaEtiqueta'] }}@if ($porDisponer) · disponible el {{ $fila['fechaPagoProgramada'] }}@endif</span>
                                            <span class="block text-xs text-text-muted md:hidden">{{ $fila['zona'] }}</span>
                                        </td>
                                        <td class="hidden text-text md:table-cell">{{ $fila['zona'] }}</td>
                                        <td class="is-num hidden text-text sm:table-cell">{{ number_format($fila['litrosSemana'], 0) }} L</td>
                                        <td class="is-num hidden lg:table-cell">
                                            <span class="block font-medium text-text">{{ $soles($fila['precioAplicado']) }}</span>
                                            @if (abs($fila['ajusteLitro']) >= 0.005 || $noPagable)
                                                <span class="block text-xs text-text-muted">Base {{ $soles($fila['precioLitro']) }}@if (! $noPagable) · ajuste {{ $fila['ajusteLitro'] < 0 ? '−' : '+' }}{{ $soles(abs($fila['ajusteLitro'])) }}@endif</span>
                                            @endif
                                        </td>
                                        <td class="hidden sm:table-cell">
                                            <span class="pp-calidad is-{{ strtolower($fila['resultado'] ?? 'sin') }}">{{ $fila['resultadoEtiqueta'] }}</span>
                                        </td>
                                        <td class="is-num hidden text-text lg:table-cell">{{ $fila['compras'] > 0 ? '− '.$soles($fila['compras']) : '—' }}</td>
                                        <td class="is-num pp-total">{{ $soles($fila['total']) }}</td>
                                        <td>
                                            <span @class(['pp-estado', 'is-pagado' => $fila['estado'] === 'PAGADO', 'is-no-pagable' => $noPagable, 'is-pendiente' => $fila['pendiente']])>
                                                <x-ui.icon :name="match (true) { $fila['estado'] === 'PAGADO' => 'check-circle', $noPagable => 'minus-circle', default => 'clock' }" class="h-4 w-4" />
                                                {{ $fila['estadoEtiqueta'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div data-sin-resultados @if (count($filas) > 0) hidden @endif class="px-5 py-10 text-center">
                            <p class="text-sm font-medium text-text">{{ count($filas) === 0 ? 'No hay liquidaciones para estos filtros' : 'No se encontraron proveedores con esa búsqueda' }}</p>
                            <p class="mt-1 text-sm text-text-muted">{{ count($filas) === 0 ? 'Prueba con otra semana, zona o estado.' : 'Prueba con otro nombre, DNI o zona.' }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <p data-resumen-tabla class="text-sm text-text-muted"></p>
                        <div data-paginacion class="ml-auto flex max-w-full flex-wrap items-center justify-end gap-1.5"></div>
                    </div>
                </section>
            </div>

            {{-- Comprobante del proveedor seleccionado --}}
            <aside class="pp-panel" data-panel-comprobante aria-label="Comprobante de pago">
                <div class="pp-panel-cabecera">
                    <div>
                        <h2 class="text-base font-semibold text-text">Comprobante de pago</h2>
                        <p class="text-xs text-text-muted">Vista previa del comprobante del proveedor seleccionado.</p>
                    </div>
                    <button type="button" data-cerrar-panel aria-label="Cerrar comprobante" class="pp-panel-cerrar">
                        <x-ui.icon name="x-mark" class="h-5 w-5" />
                    </button>
                </div>

                <div data-mini>
                    <div data-mini-vacio class="pp-panel-vacio">
                        <x-ui.icon name="file-text" class="h-9 w-9 text-text-muted/50" />
                        <p class="text-sm font-medium text-text">Selecciona un proveedor</p>
                        <p class="max-w-[16rem] text-xs text-text-muted">Haz clic en una fila para revisar su liquidación y comprobante.</p>
                    </div>
                    <div data-mini-cargando hidden class="pp-panel-vacio text-sm text-text-muted">Cargando comprobante…</div>
                    <div data-mini-hoja hidden class="pp-miniatura mx-auto"></div>
                </div>

                <div data-acciones hidden class="pp-panel-acciones">
                    <p data-acciones-nota hidden class="pp-panel-nota"></p>
                    <div class="flex flex-wrap gap-2">
                        <a data-imprimir-comprobante href="#" target="_blank" rel="noopener" class="pp-boton">
                            <x-ui.icon name="printer" class="h-4 w-4" /> Imprimir comprobante
                        </a>
                        @if ($puedePagar)
                            <button type="button" data-registrar-pago hidden class="pp-boton is-primario">
                                <x-ui.icon name="check" class="h-4 w-4" /> Registrar pago
                            </button>
                        @endif
                    </div>
                </div>
            </aside>
        </div>
    </div>

    @include('admin.pagos.proveedores._modal-vista-previa', ['filtro' => $filtro, 'semana' => $semana])
@endsection
