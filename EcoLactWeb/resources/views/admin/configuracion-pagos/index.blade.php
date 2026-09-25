@extends('layouts.admin')

@section('title', 'Configuración de pagos')

@php
    $soles = fn ($valor, int $decimales = 2): string => 'S/ '.number_format((float) $valor, $decimales);
    $kpis = $datos['kpis'];
    $proveedor = $datos['proveedor'];
    $precioBase = $datos['precioBaseVigente'];
    $reglasActivas = $datos['reglasActivas'];
    $puedeConfigurar = auth()->user()?->can('create', \App\Infrastructure\Persistence\Models\ConfiguracionPagoModel::class);
    $etiquetasEstado = ['ACTIVA' => 'Activa', 'PROGRAMADA' => 'Programada', 'VENCIDA' => 'Vencida', 'SIN_CONFIGURAR' => 'Sin configurar'];
    $encabezado = 'py-3 pr-4 text-[11px] font-medium uppercase tracking-wider text-label';
    $pasos = ['Configuras cómo se calcula el pago.', 'El sistema genera el pago pendiente según el periodo.', 'En Pagos confirmas la entrega del dinero.'];
@endphp

@section('content')
    <div data-pagos-page data-config-pagos class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="rounded-lg p-2 text-text transition hover:bg-text-muted/10 lg:hidden">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                </button>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-text">Configuración de pagos</h1>
                    <p class="mt-1.5 max-w-2xl text-sm text-label">Define cómo se calculan los pagos de proveedores y personal. Esta configuración genera pagos pendientes en el módulo Pagos.</p>
                </div>
            </div>
            @if ($puedeConfigurar)
                <x-ui.button data-modal-open="modal-nueva-configuracion" data-config-nueva as="button" type="button" class="shadow-sm">
                    <x-ui.icon name="plus" class="h-4 w-4" /> Nueva configuración
                </x-ui.button>
            @endif
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.stat-card label="Configuraciones activas" :value="$kpis['activas']" accent="primary">
                <x-slot:icon><x-ui.icon name="files" class="h-6 w-6" /></x-slot:icon>
            </x-ui.stat-card>
            <x-ui.stat-card label="Pagos a proveedores" :value="$kpis['proveedores']" accent="info">
                <x-slot:icon><x-ui.icon name="droplet" class="h-6 w-6" /></x-slot:icon>
            </x-ui.stat-card>
            <x-ui.stat-card label="Pagos al personal" :value="$kpis['personal']" accent="accent" class="sm:col-span-2 lg:col-span-1">
                <x-slot:icon><x-ui.icon name="users" class="h-6 w-6" /></x-slot:icon>
            </x-ui.stat-card>
        </div>

        <section aria-labelledby="como-funciona" class="rounded-xl border border-info/20 bg-info-soft px-5 py-4">
            <div class="flex items-center gap-2">
                <x-ui.icon name="info" class="h-5 w-5 text-info" />
                <h2 id="como-funciona" class="text-sm font-semibold text-text">¿Cómo funciona?</h2>
            </div>
            <ol class="mt-3 grid gap-3 md:grid-cols-3 md:gap-0 md:divide-x md:divide-info/20">
                @foreach ($pasos as $indice => $paso)
                    <li class="flex items-center gap-3 md:px-5 md:first:pl-0 md:last:pr-0">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-info text-sm font-semibold text-white" aria-hidden="true">{{ $indice + 1 }}</span>
                        <span class="text-sm text-text">{{ $paso }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- PAGOS A PROVEEDORES --}}
        <x-ui.card :padded="false">
            <div class="flex items-start gap-3 border-b border-text-muted/10 px-5 py-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-info-soft text-info"><x-ui.icon name="truck" class="h-5 w-5" /></span>
                <div>
                    <h2 class="text-base font-semibold text-text">Pagos a proveedores</h2>
                    <p class="mt-0.5 text-sm text-label">El precio por litro se configura aquí. Los ajustes de calidad se aplican solo si están activados.</p>
                </div>
            </div>
            <div class="px-5 pb-4">
                <x-tables.table class="ecolact-table-cards">
                    <thead>
                        <tr class="border-b border-text-muted/10 text-left">
                            <th class="{{ $encabezado }}">Tipo</th>
                            <th class="{{ $encabezado }}">Cálculo</th>
                            <th class="{{ $encabezado }}">Precio base</th>
                            <th class="{{ $encabezado }}">Ajuste por calidad</th>
                            <th class="{{ $encabezado }}">Periodicidad</th>
                            <th class="{{ $encabezado }}">Estado</th>
                            <th class="{{ $encabezado }} pr-0 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($configuracion = $proveedor['configuracion'])
                        <tr class="border-b border-text-muted/5">
                            <td data-label="Tipo" class="py-4 pr-4 font-medium text-text">Proveedor</td>
                            <td data-label="Cálculo" class="py-4 pr-4 text-text">Por litros entregados</td>
                            <td data-label="Precio base" class="py-4 pr-4 font-medium text-text">
                                @if ($precioBase !== null)
                                    {{ $soles($precioBase) }} <span class="font-normal text-label">por litro</span>
                                @else
                                    <span class="font-normal text-warning">Sin precio</span>
                                @endif
                            </td>
                            <td data-label="Ajuste por calidad" class="py-4 pr-4 text-text">
                                @if ($configuracion && ! $configuracion->aplicaCalidad())
                                    <span class="text-label">No aplica</span>
                                @else
                                    {{ $reglasActivas }} {{ $reglasActivas === 1 ? 'regla activa' : 'reglas activas' }}
                                @endif
                            </td>
                            <td data-label="Periodicidad" class="py-4 pr-4 text-text">Semanal</td>
                            <td data-label="Estado" class="py-4 pr-4">
                                <x-ui.badge :status="$proveedor['estado']">{{ $etiquetasEstado[$proveedor['estado']] }}</x-ui.badge>
                                @if ($configuracion)
                                    <span class="mt-1 block text-xs text-label">Desde {{ $configuracion->fechaInicio()->format('d/m/Y') }}</span>
                                @endif
                            </td>
                            <td data-label="Acción" class="py-4 pl-4">
                                <x-tables.actions>
                                    @if ($puedeConfigurar)
                                        @if ($configuracion)
                                            <x-ui.action-button type="edit" label="Editar configuración" subject="de pago a proveedores" :data-modal-open="'modal-editar-config-'.$configuracion->id()" />
                                        @else
                                            <x-ui.action-button type="create" :text="true" label="Configurar" subject="pagos a proveedores" data-modal-open="modal-nueva-configuracion" data-config-nueva data-tipo="PROVEEDOR" />
                                        @endif
                                    @endif
                                </x-tables.actions>
                            </td>
                        </tr>
                        @if (count($proveedor['historial']) > 0 || count($datos['preciosHistorial']) > 1)
                            <tr class="bg-background/60">
                                <td colspan="7" data-full class="px-4 py-2">
                                    @include('admin.configuracion-pagos._historial', ['fila' => $proveedor, 'esProveedor' => true, 'precios' => $datos['preciosHistorial']])
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </x-tables.table>
            </div>
        </x-ui.card>

        {{-- PAGOS AL PERSONAL --}}
        <x-ui.card :padded="false">
            <div class="flex items-start gap-3 border-b border-text-muted/10 px-5 py-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-accent-soft text-accent"><x-ui.icon name="users" class="h-5 w-5" /></span>
                <div>
                    <h2 class="text-base font-semibold text-text">Pagos al personal</h2>
                    <p class="mt-0.5 text-sm text-label">Configura el sueldo de cada rol del personal. Los roles se cargan desde la tabla roles del sistema.</p>
                </div>
            </div>
            <p class="border-b border-text-muted/10 px-5 py-3 text-xs text-label">El valor diario se calcula automáticamente dividiendo el sueldo mensual entre 30. Los trabajadores que cumplen todo el mes reciben su sueldo mensual completo.</p>
            <div class="px-5 pb-4">
                <x-tables.table class="ecolact-table-cards">
                    <thead>
                        <tr class="border-b border-text-muted/10 text-left">
                            <th class="{{ $encabezado }}">Rol</th>
                            <th class="{{ $encabezado }}">Tipo de cálculo</th>
                            <th class="{{ $encabezado }}">Valor / Sueldo base</th>
                            <th class="{{ $encabezado }}">Periodicidad</th>
                            <th class="{{ $encabezado }}">Estado</th>
                            <th class="{{ $encabezado }} pr-0 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datos['personal'] as $fila)
                            @php($configuracion = $fila['configuracion'])
                            <tr class="border-b border-text-muted/5 {{ count($fila['historial']) === 0 ? 'last:border-0' : '' }}">
                                <td data-label="Rol" class="py-4 pr-4 font-medium text-text">{{ $fila['rolNombre'] }}</td>
                                <td data-label="Tipo de cálculo" class="py-4 pr-4 text-text">Sueldo fijo mensual</td>
                                <td data-label="Valor / Sueldo base" class="py-4 pr-4 font-medium text-text whitespace-nowrap">
                                    @if ($configuracion)
                                        {{ $soles($configuracion->montoBase()) }}
                                        <span class="mt-0.5 block text-xs font-normal text-label">{{ $soles($fila['valorDia']) }} / día · Base salarial: 30 días</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="Periodicidad" class="py-4 pr-4 text-text">Día 1 de cada mes</td>
                                <td data-label="Estado" class="py-4 pr-4">
                                    <x-ui.badge :status="$fila['estado']">{{ $etiquetasEstado[$fila['estado']] }}</x-ui.badge>
                                    @if ($configuracion)
                                        <span class="mt-1 block text-xs text-label">Desde {{ $configuracion->fechaInicio()->format('d/m/Y') }}</span>
                                    @endif
                                </td>
                                <td data-label="Acción" class="py-4 pl-4">
                                    <x-tables.actions>
                                        @if ($puedeConfigurar)
                                            @if ($configuracion)
                                                <x-ui.action-button type="edit" label="Editar configuración" :subject="'de '.$fila['rolNombre']" :data-modal-open="'modal-editar-config-'.$configuracion->id()" />
                                            @else
                                                <x-ui.action-button type="create" :text="true" label="Configurar" :subject="'pagos de '.$fila['rolNombre']" data-modal-open="modal-nueva-configuracion" data-config-nueva data-tipo="PERSONAL" data-rol="{{ $fila['rolId'] }}" />
                                            @endif
                                        @endif
                                    </x-tables.actions>
                                </td>
                            </tr>
                            @if (count($fila['historial']) > 0)
                                <tr class="border-b border-text-muted/5 bg-background/60 last:border-0">
                                    <td colspan="6" data-full class="px-4 py-2">
                                        @include('admin.configuracion-pagos._historial', ['fila' => $fila, 'esProveedor' => false, 'precios' => []])
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" data-full class="py-6 text-center text-sm text-label">No hay roles remunerables. Marca un rol de la tabla roles como remunerable para configurar su sueldo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-tables.table>
                <p class="mt-4 text-xs text-label">Todas las personas activas de un mismo rol generan el mismo sueldo mensual. Cambiar un sueldo abre una nueva vigencia: los pagos de meses anteriores no se modifican.</p>
            </div>
        </x-ui.card>
    </div>

    @if ($puedeConfigurar)
        @include('admin.configuracion-pagos._modal-nueva', ['datos' => $datos])

        @foreach (array_merge([$proveedor], $datos['personal']) as $fila)
            @if ($fila['configuracion'])
                @include('admin.configuracion-pagos._modal-editar', ['fila' => $fila, 'datos' => $datos])
            @endif
        @endforeach
    @endif
@endsection
