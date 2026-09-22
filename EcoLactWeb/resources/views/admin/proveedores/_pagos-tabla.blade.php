{{--
    Contenido del modal "Ver pagos" del detalle de un proveedor: tabla, contador y paginación.
    Se sirve por JSON desde ProveedorPagoController@index y se inserta sin recargar la página.
    Recibe $datos (ver PagoSemanalProveedoresQuery::historialProveedor()).
--}}
@php
    $soles = fn ($valor): string => 'S/ '.number_format((float) $valor, 2);
    $filas = $datos['filas'];
@endphp

@if ($datos['totalProveedor'] === 0)
    <x-ui.empty-state title="Aún no hay pagos registrados" description="Este proveedor todavía no tiene pagos o liquidaciones disponibles." />
@elseif ($datos['total'] === 0)
    <x-ui.empty-state title="No se encontraron pagos" description="No existen registros para los filtros seleccionados." />
@else
    <div class="ecolact-table-scroll">
        <table class="w-full min-w-[720px] border-collapse text-left text-[13px]">
            <thead>
                <tr class="border-y border-text-muted/10 bg-background/40 text-xs font-medium uppercase tracking-wide text-text-muted">
                    <th class="w-10 px-3 py-3">#</th>
                    <th class="px-3 py-3">Tipo</th>
                    <th class="px-3 py-3">Base de cálculo</th>
                    <th class="px-3 py-3">Periodo</th>
                    <th class="px-3 py-3">Monto</th>
                    <th class="px-3 py-3">Estado</th>
                    <th class="px-3 py-3 text-right">Acción</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $indice => $fila)
                    <tr class="border-b border-text-muted/10 last:border-0" data-pago-fila="{{ $fila['id'] }}">
                        <td class="px-3 py-3 text-text-muted">{{ $datos['desde'] + $indice }}</td>
                        <td class="whitespace-nowrap px-3 py-3 text-text">{{ $fila['tipo'] }}</td>
                        <td class="whitespace-nowrap px-3 py-3 text-text">{{ number_format($fila['litros'], 2) }} L &times; {{ $soles($fila['precioLitro']) }}</td>
                        <td class="whitespace-nowrap px-3 py-3 text-text">
                            <span class="font-medium text-[#12324a]">{{ $fila['semanaEtiqueta'] }}</span>
                            <span class="block text-xs text-text-muted">{{ $fila['periodoRango'] }}</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 font-semibold text-[#12324a]">{{ $soles($fila['total']) }}</td>
                        <td class="px-3 py-3"><x-ui.badge :status="$fila['estado']" :icon="$fila['pendiente'] ? 'clock' : 'check-circle'" class="rounded-full">{{ $fila['estadoEtiqueta'] }}</x-ui.badge></td>
                        <td class="px-3 py-3 text-right"><x-ui.action-button type="detail" :text="true" label="Ver detalle" data-pago-detalle="{{ $fila['id'] }}" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-text-muted">
            @if ($datos['paginas'] === 1)
                Mostrando {{ $datos['total'] }} de {{ $datos['total'] }} {{ $datos['total'] === 1 ? 'registro' : 'registros' }}
            @else
                Mostrando {{ $datos['desde'] }}-{{ $datos['hasta'] }} de {{ $datos['total'] }} registros
            @endif
        </p>

        @if ($datos['paginas'] > 1)
            @php
                $boton = 'flex h-9 min-w-9 items-center justify-center rounded-lg border px-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-40';
                $normal = 'border-text-muted/15 bg-surface text-text hover:bg-text-muted/10';
                $activo = 'border-info/30 bg-info-soft font-semibold text-primary-dark';
            @endphp
            <nav class="flex items-center gap-1.5" aria-label="Paginación de pagos">
                <button type="button" data-pagina="{{ $datos['pagina'] - 1 }}" @disabled($datos['pagina'] === 1) aria-label="Página anterior" class="{{ $boton }} {{ $normal }}">&lsaquo;</button>
                @for ($numero = 1; $numero <= $datos['paginas']; $numero++)
                    <button type="button" data-pagina="{{ $numero }}" aria-label="Página {{ $numero }}" @if ($numero === $datos['pagina']) aria-current="page" @endif class="{{ $boton }} {{ $numero === $datos['pagina'] ? $activo : $normal }}">{{ $numero }}</button>
                @endfor
                <button type="button" data-pagina="{{ $datos['pagina'] + 1 }}" @disabled($datos['pagina'] === $datos['paginas']) aria-label="Página siguiente" class="{{ $boton }} {{ $normal }}">&rsaquo;</button>
            </nav>
        @endif
    </div>
@endif
