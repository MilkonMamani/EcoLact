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
        @elseif (! $analisis && ! in_array($muestra['estado'] ?? null, ['SELECCIONADA', 'EN_ANALISIS'], true))
            {{-- Calidad trabaja por muestreo: solo se analizan entregas seleccionadas. --}}
            <x-ui.alert variant="info" :dismissible="false">
                Esta entrega no fue seleccionada como muestra. Selecciónala para poder analizarla.
            </x-ui.alert>
            @if ($entrega->estado === 'PENDIENTE_ANALISIS' && $muestra === null)
                <form method="POST" action="{{ route('calidad.muestreo.store') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="entrega_id" value="{{ $entrega->id }}">
                    <input type="hidden" name="metodo_seleccion" value="MANUAL">
                    <x-ui.button type="submit">Seleccionar como muestra</x-ui.button>
                </form>
            @endif
        @else
            @if ($muestra)
                <p class="text-sm text-text-muted">Muestra {{ strtolower($muestra['metodoSeleccion']) }} · seleccionada el {{ \Illuminate\Support\Carbon::parse($muestra['fechaSeleccion'])->format('d/m/Y H:i') }}</p>
            @endif
            @if ($analisis)
                <x-ui.alert variant="info" :dismissible="false">
                    Este análisis está en <strong>borrador</strong>. Puedes corregir los valores registrando de nuevo, o confirmarlo abajo cuando esté listo.
                </x-ui.alert>
            @endif

            <div class="mt-4">
                @include('calidad.analisis._entrega-trazabilidad')
            </div>

            <form method="POST" action="{{ route('calidad.analisis-calidad.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="entrega_id" value="{{ $entrega->id }}">

                @include('calidad.analisis._ticket-analizador', ['urlEscanear' => route('calidad.analisis-calidad.escanear-ticket')])

                @include('calidad.analisis._parametros')

                <x-forms.textarea name="observacion" label="Observación (opcional)" :rows="3" />

                <div class="flex justify-end">
                    <x-ui.button type="submit">Guardar análisis</x-ui.button>
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
