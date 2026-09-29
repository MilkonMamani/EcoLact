{{--
    Panel derecho de Proveedores: se pinta en la carga inicial (index) y como fragmento
    (admin.proveedores.panel) cada vez que se elige otra fila. Solo lo necesario por pestaña:
    el QR vive únicamente en Resumen y solo se edita desde "Información personal" y
    "Ubicación y zona".

    @var \App\Application\Proveedor\ProveedorPanelData $panel
--}}
@php
    $limite = \App\Infrastructure\Persistence\Queries\EloquentProveedorPanelQuery::LIMITE;
    $urlAsignar = route('admin.zonas.index', ['zona' => $panel->zonaId ?? 'sin-asignar', 'asignar' => $panel->dni]);
    $etiquetasEntrega = ['PENDIENTE_ANALISIS' => 'Pendiente análisis', 'ANALIZADA' => 'Analizada', 'ANULADA' => 'Anulada'];
    $etiquetasAnalisis = ['CONFIRMADO' => ['CONFIRMADO', 'Confirmado'], 'BORRADOR' => ['PENDIENTE', 'Pendiente']];
    $pestanas = ['resumen' => 'Resumen', 'entregas' => 'Entregas', 'calidad' => 'Calidad', 'compras' => 'Compras'];
@endphp

<div class="fichas-panel-contenido" data-fichas-contenido data-ficha-id="{{ $panel->id }}"
    data-proveedor-pagos
    data-url="{{ route('admin.proveedores.pagos.index', $panel->id) }}"
    data-url-detalle="{{ route('admin.proveedores.pagos.show', [$panel->id, '__ID__']) }}"
    data-titulo="Pagos de {{ $panel->nombreCompleto() }}">

    <header class="fichas-panel-head">
        <span class="fichas-avatar" aria-hidden="true">{{ $panel->iniciales() }}</span>
        <div class="min-w-0 flex-1">
            <x-ui.badge :status="$panel->estado">{{ $panel->estaActivo() ? 'Activo' : 'Inactivo' }}</x-ui.badge>
            <h2 class="fichas-panel-nombre">{{ $panel->nombreCompleto() }}</h2>
            <p class="fichas-panel-dni">DNI {{ $panel->dni }}</p>
        </div>
        <button type="button" class="fichas-cerrar" data-fichas-cerrar aria-label="Cerrar panel" title="Cerrar panel">
            <x-ui.icon name="x-mark" class="h-5 w-5" />
        </button>
    </header>

    <div class="fichas-tabs" role="tablist" aria-label="Información del proveedor">
        @foreach ($pestanas as $clave => $etiqueta)
            <button type="button" role="tab" id="ficha-tab-{{ $clave }}" aria-controls="ficha-panel-{{ $clave }}" data-fichas-tab="{{ $clave }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" @if (! $loop->first) tabindex="-1" @endif>{{ $etiqueta }}</button>
        @endforeach
    </div>

    <div class="fichas-panel-cuerpo">
        {{-- ------------------------------------------------------------ Resumen --}}
        <section id="ficha-panel-resumen" role="tabpanel" aria-labelledby="ficha-tab-resumen" data-fichas-tab-panel="resumen">
            <div class="fichas-qr">
                <canvas data-qr-code="{{ base64_encode($panel->qrToken) }}" aria-label="Código QR de {{ $panel->nombreCompleto() }}"></canvas>
                <p>Código QR</p>
            </div>

            <div class="fichas-seccion">
                <div class="fichas-seccion-head">
                    <h3>Información personal</h3>
                    <button type="button" class="fichas-editar" data-modal-open="modal-editar-proveedor-{{ $panel->id }}" aria-label="Editar información personal de {{ $panel->nombreCompleto() }}">
                        <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar
                    </button>
                </div>
                <dl class="fichas-datos">
                    <div><dt>Nombres</dt><dd>{{ $panel->nombres }}</dd></div>
                    <div><dt>Apellidos</dt><dd>{{ $panel->apellidos }}</dd></div>
                    <div><dt>Documento (DNI)</dt><dd>{{ $panel->dni }}</dd></div>
                    <div><dt>Teléfono</dt><dd @class(['is-muted' => ! $panel->telefono])>{{ $panel->telefono ?? 'No registrado' }}</dd></div>
                    <div><dt>Estado</dt><dd>{{ $panel->estaActivo() ? 'Activo' : 'Inactivo' }}</dd></div>
                    <div><dt>Fecha de registro</dt><dd>{{ $panel->fechaRegistro }}</dd></div>
                </dl>
            </div>

            <div class="fichas-seccion">
                <div class="fichas-seccion-head">
                    <h3>Ubicación y zona</h3>
                    <a href="{{ $urlAsignar }}" class="fichas-editar" aria-label="Editar ubicación y zona de {{ $panel->nombreCompleto() }}">
                        <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar
                    </a>
                </div>
                <dl class="fichas-datos">
                    <div><dt>Zona asignada</dt><dd @class(['is-muted' => ! $panel->zona])>{{ $panel->zona ?? 'Sin asignar' }}</dd></div>
                    <div><dt>Ruta de acopio</dt><dd @class(['is-muted' => ! $panel->tieneRuta()])>{{ $panel->ruta ?? 'Sin asignar' }}</dd></div>
                    <div class="is-ancho"><dt>Referencia</dt><dd @class(['is-muted' => ! $panel->referencia])>{{ $panel->referencia ?? 'No registrada' }}</dd></div>
                    @if ($panel->tieneCoordenadas())
                        <div><dt>Latitud</dt><dd class="is-num">{{ $panel->latitud }}</dd></div>
                        <div><dt>Longitud</dt><dd class="is-num">{{ $panel->longitud }}</dd></div>
                    @endif
                </dl>

                @if ($panel->tieneCoordenadas())
                    <a href="{{ 'https://www.google.com/maps/search/?api=1&query='.$panel->latitud.','.$panel->longitud }}" target="_blank" rel="noopener" class="fichas-mapa">
                        <x-ui.icon name="map-pin" class="h-4 w-4" /> Ver en mapa
                    </a>
                @elseif ($panel->tieneRuta())
                    <p class="fichas-nota">Sin ubicación registrada en su ruta.</p>
                @elseif ($panel->zona)
                    <p class="fichas-nota">Tiene zona pero todavía no está asignado a una ruta de acopio.</p>
                @else
                    <p class="fichas-nota">Todavía no está asignado a una zona y ruta de acopio.</p>
                @endif
            </div>
        </section>

        {{-- ------------------------------------------------------------ Entregas --}}
        <section id="ficha-panel-entregas" role="tabpanel" aria-labelledby="ficha-tab-entregas" data-fichas-tab-panel="entregas" hidden>
            @if ($panel->entregas === [])
                <p class="fichas-panel-sin-datos">Este proveedor todavía no tiene entregas registradas.</p>
            @else
                <div class="fichas-mini-tabla">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Fecha</th>
                                <th scope="col">Zona</th>
                                <th scope="col" class="is-num">Litros</th>
                                <th scope="col">Identificación</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="is-accion">Ver</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($panel->entregas as $entrega)
                                <tr>
                                    <td>{{ $entrega['fecha'] }}</td>
                                    <td class="is-muted">{{ $entrega['zona'] }}</td>
                                    <td class="is-num font-semibold">{{ number_format($entrega['litros'], 2) }}</td>
                                    <td class="is-muted">{{ $entrega['identificacion'] }}</td>
                                    <td><x-ui.badge compact :status="$entrega['estado']">{{ $etiquetasEntrega[$entrega['estado']] ?? str($entrega['estado'])->replace('_', ' ')->lower()->ucfirst() }}</x-ui.badge></td>
                                    <td class="is-accion"><a href="{{ route('admin.entregas.show', $entrega['id']) }}" class="fichas-ver" aria-label="Ver entrega del {{ $entrega['fecha'] }}" title="Ver entrega"><x-ui.icon name="eye" class="h-4 w-4" /></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($panel->entregasTotal > $limite)
                    <p class="fichas-panel-pie">Últimas {{ $limite }} de {{ $panel->entregasTotal }} entregas. <a href="{{ route('admin.entregas.index', ['q' => $panel->dni]) }}">Ver todas en Acopio</a></p>
                @endif
            @endif
        </section>

        {{-- ------------------------------------------------------------ Calidad --}}
        <section id="ficha-panel-calidad" role="tabpanel" aria-labelledby="ficha-tab-calidad" data-fichas-tab-panel="calidad" hidden>
            @if ($panel->analisis === [])
                <p class="fichas-panel-sin-datos">Todavía no hay controles de calidad de sus entregas.</p>
            @else
                <div class="fichas-mini-tabla">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Fecha</th>
                                <th scope="col">Entrega</th>
                                <th scope="col">Resultado</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="is-accion">Ver</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($panel->analisis as $item)
                                @php [$varianteEstado, $etiquetaEstado] = $etiquetasAnalisis[$item['estado']] ?? [$item['estado'], str($item['estado'])->lower()->ucfirst()]; @endphp
                                <tr>
                                    <td>{{ $item['fecha'] }}</td>
                                    <td class="is-muted">#{{ $item['entregaId'] }}</td>
                                    <td>
                                        @if ($item['resultado'])
                                            <x-ui.badge compact :status="$item['resultado']" />
                                        @else
                                            <span class="is-muted">Sin resultado</span>
                                        @endif
                                    </td>
                                    <td><x-ui.badge compact :variant="$varianteEstado">{{ $etiquetaEstado }}</x-ui.badge></td>
                                    <td class="is-accion"><a href="{{ route('admin.analisis-calidad.show', $item['id']) }}" class="fichas-ver" aria-label="Ver análisis del {{ $item['fecha'] }}" title="Ver análisis"><x-ui.icon name="eye" class="h-4 w-4" /></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($panel->analisisTotal > $limite)
                    <p class="fichas-panel-pie">Últimos {{ $limite }} de {{ $panel->analisisTotal }} controles de calidad.</p>
                @endif
            @endif
        </section>

        {{-- ------------------------------------------------------------ Compras --}}
        <section id="ficha-panel-compras" role="tabpanel" aria-labelledby="ficha-tab-compras" data-fichas-tab-panel="compras" hidden>
            @if ($panel->compras === [])
                <p class="fichas-panel-sin-datos">No tiene compras de productos descontadas de su pago.</p>
            @else
                <div class="fichas-mini-tabla">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Fecha</th>
                                <th scope="col">Producto</th>
                                <th scope="col" class="is-num">Cantidad</th>
                                <th scope="col" class="is-num">Total (S/)</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($panel->compras as $compra)
                                <tr>
                                    <td>{{ $compra['fecha'] }}</td>
                                    <td class="fichas-mini-producto">{{ $compra['producto'] }}</td>
                                    <td class="is-num">{{ rtrim(rtrim(number_format($compra['cantidad'], 2), '0'), '.') }}</td>
                                    <td class="is-num font-semibold">{{ number_format($compra['total'], 2) }}</td>
                                    <td><x-ui.badge compact variant="SUCCESS" icon="check">Descontado</x-ui.badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="fichas-compras-resumen">
                <div>
                    <p class="fichas-compras-etiqueta">Total descontado</p>
                    <p class="fichas-compras-total">S/ {{ number_format($panel->totalDescontado, 2) }}</p>
                    <p class="fichas-compras-detalle">{{ $panel->comprasTotal }} {{ $panel->comprasTotal === 1 ? 'compra' : 'compras' }} a cuenta de su pago semanal</p>
                </div>
                <button type="button" class="fichas-btn is-outline is-sm" data-pagos-abrir>
                    <x-ui.icon name="banknotes" class="h-4 w-4" /> Ver pagos
                </button>
            </div>
            @if ($panel->comprasTotal > $limite)
                <p class="fichas-panel-pie">Últimas {{ $limite }} de {{ $panel->comprasTotal }} compras.</p>
            @endif
        </section>
    </div>
</div>

{{-- Formularios del proveedor seleccionado (fuera del contenido animado para que el modal quede fijo a la ventana). --}}
<x-ui.modal :id="'modal-editar-proveedor-'.$panel->id" title="Editar información personal" :open="$errors->any() && old('_modal') === 'editar-proveedor-'.$panel->id">
    <form method="POST" action="{{ route('admin.proveedores.update', $panel->id) }}" class="space-y-4">
        @csrf
        @method('PUT')
        <input type="hidden" name="_modal" value="editar-proveedor-{{ $panel->id }}">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-forms.input name="nombres" :id="'editar-nombres-'.$panel->id" label="Nombres" :value="$panel->nombres" required />
            <x-forms.input name="apellidos" :id="'editar-apellidos-'.$panel->id" label="Apellidos" :value="$panel->apellidos" required />
            <x-forms.input name="dni" :id="'editar-dni-'.$panel->id" label="DNI" :value="$panel->dni" required />
            <x-forms.input name="telefono" :id="'editar-telefono-'.$panel->id" label="Teléfono" :value="$panel->telefono" />
        </div>

        <div class="flex justify-end gap-2 border-t border-text-muted/10 pt-4">
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit">Guardar cambios</x-ui.button>
        </div>
    </form>
</x-ui.modal>
