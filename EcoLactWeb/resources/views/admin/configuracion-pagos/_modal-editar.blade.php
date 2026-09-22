@php
    $configuracion = $fila['configuracion'];
    $esProveedor = $fila['rolId'] === null;
    $modalId = 'modal-editar-config-'.$configuracion->id();
    $abierta = old('_modal') === $modalId;
    $inicioSugerido = max(DateTimeImmutable::createFromInterface(today()), $configuracion->fechaInicio())->format('Y-m-d');
@endphp

<x-ui.modal :id="$modalId" :title="'Editar configuración de pago'" size="form" :open="$abierta">
    <p class="-mt-1 text-sm text-label">
        {{ $esProveedor ? 'Pagos a proveedores' : $fila['rolNombre'] }} · El cambio abre una nueva vigencia desde la fecha indicada; los pagos ya generados conservan su monto.
    </p>

    <form method="POST" action="{{ route('admin.configuracion-pagos.update', $configuracion->id()) }}" class="mt-5" data-config-form>
        @csrf
        @method('PATCH')
        <input type="hidden" name="_modal" value="{{ $modalId }}">

        @if ($abierta && $errors->has('configuracion'))
            <x-ui.alert variant="error" class="mb-4">{{ $errors->first('configuracion') }}</x-ui.alert>
        @endif

        <section class="rounded-xl border border-text-muted/15 p-4 sm:p-5" aria-label="Configuración del pago">
            @if ($esProveedor)
                @include('admin.configuracion-pagos._campos-proveedor', [
                    'p' => 'editar-'.$configuracion->id(), 'precio' => $datos['precioBaseVigente'], 'aplica' => $configuracion->aplicaCalidad(),
                    'fecha' => $inicioSugerido, 'observacion' => $configuracion->observacion(), 'ajustes' => $datos['ajustesVigentes'],
                    'deshabilitado' => false, 'etiquetaFecha' => 'Nueva vigencia desde', 'sticky' => $abierta,
                ])
            @else
                @include('admin.configuracion-pagos._campos-personal', [
                    'p' => 'editar-'.$configuracion->id(), 'rolNombre' => $fila['rolNombre'], 'monto' => $configuracion->montoBase(),
                    'fecha' => $inicioSugerido, 'observacion' => $configuracion->observacion(),
                    'deshabilitado' => false, 'etiquetaFecha' => 'Nueva vigencia desde', 'sticky' => $abierta,
                ])
            @endif
        </section>

        <div class="mt-6 flex flex-wrap justify-end gap-3">
            <x-ui.button type="button" data-modal-close variant="ghost" class="ring-1 ring-inset ring-text-muted/25">Cancelar</x-ui.button>
            <x-ui.button type="submit">Guardar cambios</x-ui.button>
        </div>
    </form>
</x-ui.modal>
