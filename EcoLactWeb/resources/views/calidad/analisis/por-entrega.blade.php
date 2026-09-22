@extends('layouts.calidad')

@section('title', 'Análisis de la entrega')

@section('content')
    <x-ui.button :href="route('calidad.entregas.show', $entrega->id)" variant="ghost" size="sm" as="a">&larr; Volver a la entrega</x-ui.button>

    <x-ui.card title="Análisis de calidad" :subtitle="'Entrega #'.$entrega->id.' · '.\Illuminate\Support\Carbon::parse($entrega->fecha_hora)->format('d/m/Y H:i')">
        @if ($analisis && $analisis->estaConfirmado())
            <x-slot:actions>
                <x-ui.badge :status="$analisis->estado()->value" />
                @if ($analisis->resultado())
                    <x-ui.badge :status="$analisis->resultado()->value" />
                @endif
            </x-slot:actions>

            <p class="text-sm text-text-muted">Este análisis ya fue confirmado y es inmutable.</p>

            <div class="mt-4">
                <x-ui.button :href="route('calidad.analisis-calidad.show', $analisis->id())" variant="secondary" size="sm" as="a">Ver detalle completo →</x-ui.button>
            </div>
        @else
            @if ($analisis)
                <x-ui.alert variant="info" :dismissible="false">
                    Este análisis está en <strong>borrador</strong>. Puedes corregir los valores registrando de nuevo, o confirmarlo abajo cuando esté listo.
                </x-ui.alert>
            @endif

            <form method="POST" action="{{ route('calidad.analisis-calidad.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
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
                    <label class="mb-1 block text-sm font-medium text-text">Evidencia del análisis (opcional)</label>
                    <input type="file" name="imagen" accept="image/jpeg,image/png" class="w-full rounded-lg border border-text-muted/25 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-text-muted">Formatos jpg, jpeg o png, máximo 5 MB.</p>
                </div>

                <x-forms.textarea name="observacion" label="Observación (opcional)" :rows="3" />

                <div class="flex justify-end">
                    <x-ui.button type="submit">Guardar valores</x-ui.button>
                </div>
            </form>

            @if ($analisis)
                <div class="mt-4 rounded-xl border border-warning/30 bg-warning/5 p-4">
                    <p class="text-sm font-semibold text-text">Confirmar análisis</p>
                    <p class="mt-1 text-sm text-text-muted">Después de confirmar, el análisis quedará bloqueado y no podrás modificar los valores ni el resultado.</p>
                    <form method="POST" action="{{ route('calidad.analisis-calidad.finalizar', $analisis->id()) }}" class="mt-3" onsubmit="return confirm('¿Confirmar análisis? Después de confirmar no podrás modificar los valores ni el resultado.');">
                        @csrf
                        @method('PATCH')
                        <x-ui.button type="submit" variant="primary">Confirmar análisis</x-ui.button>
                    </form>
                </div>
            @endif
        @endif
    </x-ui.card>
@endsection
