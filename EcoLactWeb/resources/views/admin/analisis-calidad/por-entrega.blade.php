@extends('layouts.admin')

@section('title', 'Análisis de la entrega')

@section('content')
    <x-ui.button :href="route('admin.analisis-calidad.index')" variant="ghost" size="sm" as="a">&larr; Calidad</x-ui.button>

    <x-ui.card title="Análisis de calidad" :subtitle="'Entrega #'.$entrega->id.' · '.$entrega->proveedor->nombres.' '.$entrega->proveedor->apellidos.' · '.number_format((float) $entrega->litros, 2).' L · '.\Illuminate\Support\Carbon::parse($entrega->fecha_hora)->format('d/m/Y H:i')">
        @if ($analisis && $analisis->estaConfirmado())
            <x-slot:actions>
                <x-ui.badge :status="$analisis->estado()->value" />
                @if ($analisis->resultado())
                    <x-ui.badge :status="$analisis->resultado()->value" />
                @endif
            </x-slot:actions>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha de análisis</dt>
                    <dd class="mt-1 text-sm text-text">{{ $analisis->fechaAnalisis()->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Resultado</dt>
                    <dd class="mt-1 text-sm text-text"><x-ui.badge :status="$analisis->resultado()->value" /></dd>
                </div>
            </dl>

            <p class="mt-4 text-sm text-text-muted">Este análisis ya fue confirmado y es inmutable.</p>
            <div class="mt-4">
                <x-ui.button :href="route('admin.analisis-calidad.show', $analisis->id())" variant="secondary" size="sm" as="a">Ver detalle completo</x-ui.button>
            </div>
        @else
            <x-slot:actions>
                <x-ui.badge status="PENDIENTE" icon="clock">{{ $analisis ? 'Borrador' : 'Pendiente' }}</x-ui.badge>
            </x-slot:actions>

            @if ($analisis)
                <x-ui.alert variant="info" :dismissible="false">
                    Este análisis está en <strong>borrador</strong>. Puedes corregir los valores registrando de nuevo, o confirmarlo abajo cuando esté listo.
                </x-ui.alert>
            @else
                <p class="text-sm text-text-muted">Esta entrega todavía no tiene análisis de calidad. El análisis es opcional: mientras tanto la leche se acopia y se paga al precio base.</p>
            @endif

            <form method="POST" action="{{ route('admin.analisis-calidad.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="entrega_id" value="{{ $entrega->id }}">

                <div>
                    <p class="mb-2 text-sm font-medium text-text">Parámetros de calidad</p>
                    @if (count($parametros) === 0)
                        <x-ui.empty-state title="No hay parámetros de calidad configurados" :icon="false" />
                    @else
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($parametros as $parametro)
                                <x-forms.input
                                    name="valores[{{ $parametro->codigo() }}]"
                                    type="number"
                                    step="any"
                                    :label="$parametro->nombre() . ($parametro->unidad() ? ' (' . $parametro->unidad() . ')' : '')"
                                />
                            @endforeach
                        </div>
                    @endif
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-text" for="imagen-analisis">Evidencia del análisis (opcional)</label>
                    <input id="imagen-analisis" type="file" name="imagen" accept="image/jpeg,image/png" class="w-full rounded-lg border border-text-muted/25 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-text-muted">Formatos jpg, jpeg o png, máximo 5 MB.</p>
                </div>

                <x-forms.textarea name="observacion" label="Observación (opcional)" :rows="3" />

                <div class="flex justify-end">
                    <x-ui.button type="submit">Guardar valores</x-ui.button>
                </div>
            </form>

            @if ($analisis)
                <div class="mt-4 rounded-xl border border-warning/30 bg-warning-soft p-4">
                    <p class="text-sm font-semibold text-text">Confirmar análisis</p>
                    <p class="mt-1 text-sm text-text-muted">Al confirmar se calcula el resultado, el análisis queda bloqueado y el pago pendiente del proveedor se recalcula con el ajuste de calidad.</p>
                    <form method="POST" action="{{ route('admin.analisis-calidad.finalizar', $analisis->id()) }}" class="mt-3" data-confirm="¿Confirmar análisis? Después de confirmar no podrás modificar los valores ni el resultado.">
                        @csrf
                        @method('PATCH')
                        <x-ui.button type="submit" variant="primary">Confirmar análisis</x-ui.button>
                    </form>
                </div>
            @endif
        @endif
    </x-ui.card>
@endsection
