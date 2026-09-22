@php
    $abierta = old('_modal') === 'nueva';
    $tipoActual = $abierta ? old('tipo_beneficiario', 'PROVEEDOR') : 'PROVEEDOR';
    $esPersonal = $tipoActual === 'PERSONAL';
    $roles = collect($datos['personal'])->mapWithKeys(fn ($fila) => [$fila['rolId'] => $fila['rolNombre']])->all();
    $hoy = today()->format('Y-m-d');
    $subtitulos = [
        'PROVEEDOR' => 'Define cómo se calculará el pago del proveedor y cómo luego aparecerá en el módulo Pagos.',
        'PERSONAL' => 'Define el sueldo del personal y cómo luego aparecerá en el módulo Pagos.',
    ];
    $tipos = [
        'PROVEEDOR' => ['Proveedor', 'Configura el pago para proveedores de leche.', 'truck'],
        'PERSONAL' => ['Personal', 'Configura el sueldo para el personal de la planta.', 'users'],
    ];
@endphp

<x-ui.modal id="modal-nueva-configuracion" title="Nueva configuración de pago" size="form" :open="$abierta">
    <p class="-mt-1 text-sm text-label" data-config-subtitulo data-subtitulos='@json($subtitulos)'>{{ $subtitulos[$tipoActual] }}</p>

    <form method="POST" action="{{ route('admin.configuracion-pagos.store') }}" class="mt-5" data-config-form>
        @csrf
        <input type="hidden" name="_modal" value="nueva">

        @if ($abierta && $errors->has('configuracion'))
            <x-ui.alert variant="error" class="mb-4">{{ $errors->first('configuracion') }}</x-ui.alert>
        @endif

        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-text">Tipo de configuración</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($tipos as $valor => [$titulo, $descripcion, $icono])
                    <label class="cursor-pointer">
                        <input type="radio" name="tipo_beneficiario" value="{{ $valor }}" data-config-tipo class="peer sr-only" @checked($tipoActual === $valor)>
                        <span class="flex h-full items-center gap-3 rounded-xl border border-text-muted/25 bg-surface p-4 transition peer-checked:border-primary peer-checked:bg-primary-soft/60 peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary-dark"><x-ui.icon :name="$icono" class="h-6 w-6" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-text">{{ $titulo }}</span>
                                <span class="mt-0.5 block text-xs text-label">{{ $descripcion }}</span>
                            </span>
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 border-text-muted/40" aria-hidden="true">
                                <span class="h-2.5 w-2.5 rounded-full bg-primary {{ $tipoActual === $valor ? 'opacity-100' : 'opacity-0' }} transition" data-config-punto></span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            <x-forms.error name="tipo_beneficiario" />
        </fieldset>

        <section class="mt-5 rounded-xl border border-text-muted/15 p-4 sm:p-5" aria-labelledby="config-pago-titulo">
            <h3 id="config-pago-titulo" class="mb-4 text-sm font-semibold text-text">Configuración del pago</h3>

            <div data-config-seccion="PROVEEDOR" @if ($esPersonal) hidden @endif>
                @include('admin.configuracion-pagos._campos-proveedor', [
                    'p' => 'nueva-prov', 'precio' => $datos['precioBaseVigente'], 'aplica' => true, 'fecha' => $hoy, 'observacion' => null,
                    'ajustes' => $datos['ajustesVigentes'], 'deshabilitado' => $esPersonal, 'etiquetaFecha' => 'Fecha de inicio', 'sticky' => $abierta,
                ])
            </div>

            <div data-config-seccion="PERSONAL" @if (! $esPersonal) hidden @endif>
                @include('admin.configuracion-pagos._campos-personal', [
                    'p' => 'nueva-pers', 'roles' => $roles, 'monto' => null, 'fecha' => $hoy, 'observacion' => null,
                    'deshabilitado' => ! $esPersonal, 'etiquetaFecha' => 'Fecha de inicio', 'sticky' => $abierta,
                ])
            </div>
        </section>

        <div class="mt-6 flex flex-wrap justify-end gap-3">
            <x-ui.button type="button" data-modal-close variant="ghost" class="ring-1 ring-inset ring-text-muted/25">Cancelar</x-ui.button>
            <x-ui.button type="submit">Guardar configuración</x-ui.button>
        </div>
    </form>
</x-ui.modal>
