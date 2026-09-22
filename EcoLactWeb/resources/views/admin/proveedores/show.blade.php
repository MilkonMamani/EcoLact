@php
    $tieneUbicacion = $asignacion?->latitud !== null && $asignacion?->longitud !== null;
    $latitud = $tieneUbicacion ? number_format((float) $asignacion->latitud, 6, '.', '') : null;
    $longitud = $tieneUbicacion ? number_format((float) $asignacion->longitud, 6, '.', '') : null;
    // "Registrar/Editar ubicación" reutiliza el modal de asignación de Zonas (se abre solo con ?asignar=<DNI>).
    $urlAsignar = route('admin.zonas.index', ['zona' => $zona?->id ?? 'sin-asignar', 'asignar' => $proveedor->dni()]);
    $productosPorId = \App\Infrastructure\Persistence\Models\ProductoModel::whereIn('id', collect($compras)->map(fn ($c) => $c->productoId())->unique())
        ->pluck('nombre', 'id');
@endphp

@extends('layouts.admin')

@section('title', $proveedor->nombreCompleto())

@section('content')
    <x-ui.button :href="route('admin.proveedores.index')" variant="ghost" size="sm" as="a">&larr; Proveedores</x-ui.button>

    <x-ui.card :title="$proveedor->nombreCompleto()" :subtitle="'DNI: '.$proveedor->dni()">
        <x-slot:actions>
            <x-ui.badge :status="$proveedor->estado()->value" />
            <x-ui.action-button type="edit" :text="true" label="Editar" data-modal-open="modal-editar-proveedor" />
            @if ($proveedor->estado()->value === 'ACTIVO')
                <x-ui.action-button type="deactivate" :text="true" label="Desactivar" data-modal-open="modal-desactivar-proveedor" />
            @else
                <x-ui.action-button type="activate" :text="true" label="Reactivar" data-modal-open="modal-reactivar-proveedor" />
            @endif
        </x-slot:actions>

        <div class="grid gap-8 lg:grid-cols-3">
            <div class="space-y-8 lg:col-span-2">
                <x-ui.profile-section title="Datos personales" icon="user">
                    <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                        <x-ui.data-field label="Nombres">{{ $proveedor->nombres() }}</x-ui.data-field>
                        <x-ui.data-field label="Apellidos">{{ $proveedor->apellidos() }}</x-ui.data-field>
                        <x-ui.data-field label="DNI" icon="id-card">{{ $proveedor->dni() }}</x-ui.data-field>
                        <x-ui.data-field label="Teléfono" icon="phone" :muted="! $proveedor->telefono()">{{ $proveedor->telefono() ?? 'No registrado' }}</x-ui.data-field>
                        <x-ui.data-field label="Fecha de registro" icon="calendar">{{ $proveedor->fechaRegistro()->format('d/m/Y') }}</x-ui.data-field>
                        <x-ui.data-field label="Advertencias" icon="triangle-alert">
                            @if ($proveedor->advertenciasCount() > 0)
                                <span class="inline-flex items-center rounded-full bg-danger/10 px-2.5 py-0.5 text-sm font-semibold text-danger">{{ $proveedor->advertenciasCount() }}</span>
                            @else
                                <span class="text-sm font-normal text-text-muted">Sin advertencias</span>
                            @endif
                        </x-ui.data-field>
                    </dl>
                </x-ui.profile-section>

                <x-ui.profile-section title="Ubicación y ruta de acopio" icon="map-pin">
                    <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                        <x-ui.data-field label="Zona" icon="map">
                            @if ($zona)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-soft px-3 py-1 text-sm font-semibold text-primary-dark">{{ $zona->nombre }}</span>
                            @else
                                <span class="text-sm font-normal text-text-muted">Sin asignar</span>
                            @endif
                        </x-ui.data-field>
                        <x-ui.data-field label="Ruta de acopio" icon="route">
                            @if ($rutaActual)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-info/10 px-3 py-1 text-sm font-semibold text-info">{{ $rutaActual->nombre }}</span>
                            @else
                                <span class="text-sm font-normal text-text-muted">Sin asignar</span>
                            @endif
                        </x-ui.data-field>
                    </dl>

                    @if (! $rutaActual)
                        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-warning/10 px-4 py-3">
                            <p class="text-sm text-text">Este proveedor todavía no está asignado a una zona y ruta de acopio.</p>
                            <x-ui.action-button type="edit" :text="true" label="Asignar a zona y ruta" :href="$urlAsignar" />
                        </div>
                    @else
                        <div class="mt-5 rounded-lg border border-text-muted/10 p-4">
                            <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-text-muted">Ubicación del punto de acopio</p>

                            @if ($tieneUbicacion)
                                <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                                    <x-ui.data-field label="Latitud" icon="crosshair">{{ $latitud }}</x-ui.data-field>
                                    <x-ui.data-field label="Longitud" icon="crosshair">{{ $longitud }}</x-ui.data-field>
                                    <x-ui.data-field label="Referencia" class="sm:col-span-2" :muted="! $asignacion->referencia_ubicacion">{{ $asignacion->referencia_ubicacion ?: 'No registrada' }}</x-ui.data-field>
                                </dl>
                                <p class="mt-3 text-xs text-text-muted">Coordenadas: {{ $latitud }}, {{ $longitud }}</p>

                                <div class="mt-4 flex flex-wrap justify-end gap-2">
                                    <x-ui.action-button type="edit" :text="true" label="Editar ubicación" :href="$urlAsignar" />
                                    <x-ui.action-button type="location" :text="true" label="Ver en mapa" :href="'https://www.google.com/maps/search/?api=1&query='.$latitud.','.$longitud" target="_blank" />
                                </div>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/10 px-3 py-1 text-sm font-medium text-warning">
                                    <x-ui.icon name="map-pin" class="h-4 w-4" /> Sin ubicación registrada
                                </span>
                                <p class="mt-2 text-sm text-text-muted">Este proveedor todavía no tiene un punto de acopio georreferenciado.</p>
                                <dl class="mt-4">
                                    <x-ui.data-field label="Referencia" :muted="! $asignacion->referencia_ubicacion">{{ $asignacion->referencia_ubicacion ?: 'No registrada' }}</x-ui.data-field>
                                </dl>

                                <div class="mt-4 flex justify-end">
                                    <x-ui.action-button type="edit" :text="true" label="Registrar ubicación" :href="$urlAsignar" />
                                </div>
                            @endif
                        </div>
                    @endif
                </x-ui.profile-section>
            </div>

            <x-ui.profile-section title="Código QR del proveedor" icon="qr-code">
                <div class="rounded-xl border border-primary/30 bg-primary-soft p-4 text-center">
                    <canvas data-qr-code="{{ base64_encode($proveedor->qrToken()) }}" class="mx-auto rounded-lg bg-white p-2" aria-label="Código QR de {{ $proveedor->nombreCompleto() }}"></canvas>
                    <p class="mt-3 text-sm text-text-muted">Escanea este código para identificar a <span class="font-medium text-text">{{ $proveedor->nombreCompleto() }}</span>.</p>
                </div>
            </x-ui.profile-section>
        </div>
    </x-ui.card>

    {{-- "Ver pagos" abre un modal sobre esta misma pantalla: los datos llegan por fetch y la URL no cambia. --}}
    <div data-proveedor-pagos data-url="{{ route('admin.proveedores.pagos.index', $proveedor->id()) }}" data-url-detalle="{{ route('admin.proveedores.pagos.show', [$proveedor->id(), '__ID__']) }}">
        <x-ui.card title="Pago del proveedor" subtitle="El pago semanal se genera solo desde Configuración de pagos y se confirma en Pagos.">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    @if ($resumenPagos['total'] === 0)
                        <p class="text-sm text-text-muted">Aún no hay pagos generados para {{ $proveedor->nombreCompleto() }}.</p>
                        <p class="mt-1 text-xs text-text-muted">Aparecen en Pagos cuando termina cada semana de entregas y existe una configuración de pago a proveedores.</p>
                    @else
                        <p class="text-sm text-text-muted">
                            Este proveedor tiene {{ $resumenPagos['total'] }} {{ $resumenPagos['total'] === 1 ? 'pago registrado' : 'pagos registrados' }}@if ($resumenPagos['pendientes'] > 0) ({{ $resumenPagos['pendientes'] }} {{ $resumenPagos['pendientes'] === 1 ? 'pendiente' : 'pendientes' }} de pago)@endif.
                        </p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.action-button type="view" :text="true" label="Ver pagos" data-pagos-abrir />
                </div>
            </div>
        </x-ui.card>
    </div>

    <x-ui.card :padded="false" class="mt-4">
        <div class="flex flex-wrap gap-1 border-b border-text-muted/10 px-3 pt-3" role="tablist">
            @foreach (['resumen' => 'Resumen', 'entregas' => 'Entregas', 'calidad' => 'Calidad', 'compras' => 'Compras', 'zona' => 'Zona'] as $clave => $etiqueta)
                <button type="button" data-perfil-tab="{{ $clave }}" class="rounded-t-lg px-4 py-2 text-sm font-medium text-text-muted transition-colors hover:bg-text-muted/5 border-b-2 border-transparent data-[active=true]:border-primary data-[active=true]:bg-primary-soft/60 data-[active=true]:font-semibold data-[active=true]:text-primary-dark" data-active="{{ $clave === 'resumen' ? 'true' : 'false' }}">{{ $etiqueta }}</button>
            @endforeach
        </div>

        <div class="p-5">
            <div data-perfil-panel="resumen">
                <p class="text-sm text-text-muted">Último análisis: {{ $analisis === [] ? 'Sin análisis registrados.' : $analisis[0]->fechaAnalisis()->format('d/m/Y H:i').' — '.($analisis[0]->resultado()?->value ?? 'Sin resultado') }}</p>
                <p class="mt-1 text-sm text-text-muted">Seguimiento de calidad: {{ $proveedor->advertenciasCount() > 0 ? $proveedor->advertenciasCount().' advertencia(s) registrada(s).' : 'Sin advertencias.' }}</p>
            </div>

            <div data-perfil-panel="entregas" hidden>
                @if (count($entregas) === 0)
                    <x-ui.empty-state title="Sin entregas registradas" :icon="false" />
                @else
                    <x-tables.table>
                        <thead>
                            <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="py-2 pr-4">Zona</th>
                                <th class="py-2 pr-4">Litros</th>
                                <th class="py-2 pr-4">Identificación</th>
                                <th class="py-2 pr-4">Estado</th>
                                <th class="py-2 pl-4 text-right">Ver</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entregas as $entrega)
                                <tr class="border-b border-text-muted/5 last:border-0">
                                    <td class="py-2 pr-4 text-text">{{ $entrega->fechaHora()->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $zona?->nombre ?? 'Zona #'.$entrega->zonaId() }}</td>
                                    <td class="py-2 pr-4 text-text">{{ number_format($entrega->litros(), 2) }} L</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $entrega->metodoIdentificacion() }}</td>
                                    <td class="py-2 pr-4"><x-ui.badge :status="$entrega->estado()->value" /></td>
                                    <td class="py-2 pl-4 text-right"><a class="text-primary hover:underline" href="{{ route('admin.entregas.show', $entrega->id()) }}">Ver</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-tables.table>
                @endif
            </div>

            <div data-perfil-panel="calidad" hidden>
                @if (count($analisis) === 0)
                    <x-ui.empty-state title="Sin análisis de calidad registrados" :icon="false" />
                @else
                    <x-tables.table>
                        <thead>
                            <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="py-2 pr-4">Entrega</th>
                                <th class="py-2 pr-4">Resultado</th>
                                <th class="py-2 pr-4">Estado</th>
                                <th class="py-2 pl-4 text-right">Ver</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($analisis as $item)
                                <tr class="border-b border-text-muted/5 last:border-0">
                                    <td class="py-2 pr-4 text-text">{{ $item->fechaAnalisis()->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 pr-4 text-text-muted">#{{ $item->entregaId() }}</td>
                                    <td class="py-2 pr-4">@if ($item->resultado())<x-ui.badge :status="$item->resultado()->value" />@else <span class="text-xs text-text-muted">Sin resultado</span>@endif</td>
                                    <td class="py-2 pr-4"><x-ui.badge :status="$item->estado()->value" /></td>
                                    <td class="py-2 pl-4 text-right"><a class="text-primary hover:underline" href="{{ route('admin.analisis-calidad.show', $item->id()) }}">Ver</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-tables.table>
                @endif
            </div>

            <div data-perfil-panel="liquidaciones" hidden>
                @if (count($liquidaciones) === 0)
                    <x-ui.empty-state title="Sin liquidaciones registradas" :icon="false" />
                @else
                    <x-tables.table>
                        <thead>
                            <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                                <th class="py-2 pr-4">Litros entregados</th>
                                <th class="py-2 pr-4">Litros pagables</th>
                                <th class="py-2 pr-4">Incidencia</th>
                                <th class="py-2 pr-4">Precio aplicado</th>
                                <th class="py-2 pr-4">Total a pagar</th>
                                <th class="py-2 pr-4">Estado</th>
                                <th class="py-2 pl-4 text-right">Hoja</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($liquidaciones as $liquidacion)
                                <tr class="border-b border-text-muted/5 last:border-0">
                                    <td class="py-2 pr-4 text-text">{{ number_format($liquidacion->totalLitrosEntregados(), 2) }} L</td>
                                    <td class="py-2 pr-4 text-text">{{ number_format($liquidacion->totalLitrosPagables(), 2) }} L</td>
                                    <td class="py-2 pr-4">{{ $liquidacion->tuvoIncidencia() ? 'Sí' : 'No' }}</td>
                                    <td class="py-2 pr-4 text-text-muted">S/ {{ number_format($liquidacion->precioLitroAplicado(), 4) }}</td>
                                    <td class="py-2 pr-4 font-medium text-text">S/ {{ number_format($liquidacion->totalPagar(), 2) }}</td>
                                    <td class="py-2 pr-4"><x-ui.badge :status="$liquidacion->estado()->value" /></td>
                                    <td class="py-2 pl-4 text-right"><a class="text-primary hover:underline" target="_blank" rel="noopener" href="{{ route('admin.liquidaciones.imprimir', $liquidacion->id()) }}">Imprimir</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-tables.table>
                @endif
            </div>

            <div data-perfil-panel="compras" hidden>
                @if (count($compras) === 0)
                    <x-ui.empty-state title="Sin compras registradas" :icon="false" />
                @else
                    <x-tables.table>
                        <thead>
                            <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="py-2 pr-4">Producto</th>
                                <th class="py-2 pr-4">Cantidad</th>
                                <th class="py-2 pr-4">Precio unitario</th>
                                <th class="py-2 pr-4">Importe</th>
                                <th class="py-2 pr-4">Forma de pago</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($compras as $compra)
                                <tr class="border-b border-text-muted/5 last:border-0">
                                    <td class="py-2 pr-4 text-text">{{ $compra->fecha()->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 pr-4 text-text">{{ $productosPorId[$compra->productoId()] ?? 'Producto #'.$compra->productoId() }}</td>
                                    <td class="py-2 pr-4 text-text">{{ number_format($compra->cantidad(), 2) }}</td>
                                    <td class="py-2 pr-4 text-text-muted">S/ {{ number_format($compra->precioUnitario(), 2) }}</td>
                                    <td class="py-2 pr-4 font-medium text-text">S/ {{ number_format($compra->importeTotal(), 2) }}</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $compra->formaPago() === 'DESCUENTO_PLANILLA' ? 'Descontado del pago' : 'Contado' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-tables.table>
                @endif
            </div>

            <div data-perfil-panel="zona" hidden>
                <dl class="mb-5 grid gap-4 sm:grid-cols-2">
                    <x-ui.data-field label="Zona" icon="map" :muted="! $zona">{{ $zona?->nombre ?? 'Sin asignar' }}</x-ui.data-field>
                    <x-ui.data-field label="Ruta de acopio" icon="route" :muted="! $rutaActual">{{ $rutaActual?->nombre ?? 'Sin asignar' }}</x-ui.data-field>
                </dl>

                @if (count($zonaHistorial) === 0)
                    <x-ui.empty-state title="Sin historial de zonas" :icon="false" />
                @else
                    <x-tables.table>
                        <thead>
                            <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                                <th class="py-2 pr-4">Zona</th>
                                <th class="py-2 pr-4">Desde</th>
                                <th class="py-2 pr-4">Hasta</th>
                                <th class="py-2 pr-4">Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($zonaHistorial as $historial)
                                <tr class="border-b border-text-muted/5 last:border-0">
                                    <td class="py-2 pr-4 text-text">{{ $historial->zonaId() === $proveedor->zonaId() ? ($zona?->nombre ?? 'Zona #'.$historial->zonaId()) : 'Zona #'.$historial->zonaId() }}</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $historial->fechaInicio()->format('d/m/Y') }}</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $historial->fechaFin()?->format('d/m/Y') ?? 'Actual' }}</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $historial->motivo() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-tables.table>
                @endif
            </div>
        </div>
    </x-ui.card>

    @php
        $campoPago = 'flex h-11 items-stretch overflow-hidden rounded-lg border border-text-muted/25 bg-surface shadow-sm focus-within:border-primary focus-within:ring-1 focus-within:ring-primary';
        $iconoPago = 'flex w-11 shrink-0 items-center justify-center border-r border-text-muted/15 bg-background/70 text-primary';
        $selectPago = 'w-full min-w-0 cursor-pointer appearance-none bg-transparent pl-3 pr-8 text-sm text-text focus:outline-none';
    @endphp

    <x-ui.modal id="modal-pagos-proveedor" :title="'Pagos de '.$proveedor->nombreCompleto()" size="2xl">
        <p class="-mt-3 text-sm text-text-muted">Historial de pagos realizados al proveedor. Consulta los comprobantes por semana.</p>

        <form data-pagos-filtros class="mt-5 grid items-end gap-4 sm:grid-cols-[1fr_1fr_auto]">
            <div>
                <label for="pagos-filtro-semana" class="mb-1.5 block text-[13px] font-semibold text-[#12324a]">Semana</label>
                <div class="{{ $campoPago }} relative">
                    <span class="{{ $iconoPago }}"><x-ui.icon name="calendar" class="h-[18px] w-[18px]" /></span>
                    <select id="pagos-filtro-semana" name="semana" class="{{ $selectPago }}">
                        <option value="todos">Todos</option>
                    </select>
                    <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                </div>
            </div>
            <div>
                <label for="pagos-filtro-estado" class="mb-1.5 block text-[13px] font-semibold text-[#12324a]">Estado</label>
                <div class="{{ $campoPago }} relative">
                    <span class="{{ $iconoPago }}"><x-ui.icon name="clock" class="h-[18px] w-[18px]" /></span>
                    <select id="pagos-filtro-estado" name="estado" class="{{ $selectPago }}">
                        <option value="todos">Todos</option>
                        <option value="pendientes">Pendiente</option>
                        <option value="pagados">Pagado</option>
                    </select>
                    <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                </div>
            </div>
            <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-6 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-dark focus-visible:outline-primary">
                <x-ui.icon name="magnifying-glass" class="h-[18px] w-[18px]" /> Filtrar
            </button>
        </form>

        <div data-pagos-resultado class="mt-5 min-h-[10rem]" aria-live="polite"></div>

        <div class="mt-5 flex justify-end border-t border-text-muted/10 pt-4">
            <x-ui.button type="button" data-modal-close variant="ghost">Cerrar</x-ui.button>
        </div>
    </x-ui.modal>

    {{-- Segundo modal, encima del historial: al cerrarlo se vuelve al historial tal como estaba. --}}
    <x-ui.modal id="modal-pago-detalle" title="Detalle del pago" size="2xl" :nested="true">
        <p data-pago-detalle-sub class="-mt-3 text-sm text-text-muted">Comprobante del pago del proveedor.</p>
        <div data-pago-detalle-cuerpo class="mt-4 min-h-[8rem] rounded-lg bg-background/60 p-3"></div>
        <div class="mt-5 flex justify-end border-t border-text-muted/10 pt-4">
            <x-ui.button type="button" data-modal-close variant="ghost">Cerrar</x-ui.button>
        </div>
    </x-ui.modal>

    <x-ui.modal id="modal-editar-proveedor" title="Editar proveedor">
        <form method="POST" action="{{ route('admin.proveedores.update', $proveedor->id()) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <x-forms.input name="dni" label="DNI" :value="$proveedor->dni()" required />
            <x-forms.input name="nombres" label="Nombres" :value="$proveedor->nombres()" required />
            <x-forms.input name="apellidos" label="Apellidos" :value="$proveedor->apellidos()" required />
            <x-forms.input name="telefono" label="Teléfono" :value="$proveedor->telefono()" />

            <p class="text-xs text-text-muted">La zona y la ruta se asignan desde el módulo <a href="{{ route('admin.zonas.index') }}" class="underline">Zonas</a>, no desde este formulario.</p>

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar cambios</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-desactivar-proveedor" title="Desactivar proveedor">
        <p class="text-sm text-text">¿Desactivar a <strong>{{ $proveedor->nombreCompleto() }}</strong>?</p>
        <p class="mt-2 text-xs text-text-muted">No se elimina: el registro y su historial se conservan, pero dejará de estar disponible para operar. Podrás reactivarlo cuando quieras.</p>
        <p class="mt-1 text-sm text-text-muted">No podrá registrarse entregas a su nombre. Su información histórica se conservará.</p>
        <form method="POST" action="{{ route('admin.proveedores.desactivar', $proveedor->id()) }}" class="mt-4 flex justify-end gap-2">
            @csrf
            @method('PATCH')
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit" variant="danger">Sí, desactivar</x-ui.button>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-reactivar-proveedor" title="Reactivar proveedor">
        <p class="text-sm text-text">¿Reactivar a <strong>{{ $proveedor->nombreCompleto() }}</strong>?</p>
<p class="mt-2 text-xs text-text-muted">Volverá a estar disponible para operar con normalidad.</p>
        <form method="POST" action="{{ route('admin.proveedores.reactivar', $proveedor->id()) }}" class="mt-4 flex justify-end gap-2">
            @csrf
            @method('PATCH')
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit" variant="secondary">Sí, reactivar</x-ui.button>
        </form>
    </x-ui.modal>

    <script>
        (function () {
            const botones = document.querySelectorAll('[data-perfil-tab]');
            const paneles = document.querySelectorAll('[data-perfil-panel]');

            botones.forEach((boton) => {
                boton.addEventListener('click', () => {
                    const clave = boton.getAttribute('data-perfil-tab');
                    botones.forEach((b) => b.setAttribute('data-active', b === boton ? 'true' : 'false'));
                    paneles.forEach((panel) => {
                        panel.hidden = panel.getAttribute('data-perfil-panel') !== clave;
                    });
                });
            });
        })();
    </script>
@endsection
