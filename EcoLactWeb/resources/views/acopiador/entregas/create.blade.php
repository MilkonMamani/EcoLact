@extends('layouts.acopiador')

@section('title', 'Registrar entrega')

@section('content')
    <x-ui.button :href="route('acopiador.entregas.index')" variant="ghost" size="sm" as="a">&larr; Volver a entregas</x-ui.button>

    <x-ui.card title="Registrar entrega" subtitle="Identifica al proveedor por QR o por DNI, luego registra los litros recibidos.">
        <form method="POST" action="{{ route('acopiador.entregas.store') }}" class="space-y-4">
            @csrf

            <div>
                <p class="mb-1.5 text-sm font-medium text-text">Método de identificación</p>
                <div class="flex gap-2" role="tablist">
                    <button type="button" id="btn-metodo-qr" class="rounded-lg border border-primary bg-primary px-4 py-2 text-sm font-medium text-white" onclick="seleccionarMetodo('QR')">
                        Escanear / usar QR
                    </button>
                    <button type="button" id="btn-metodo-dni" class="rounded-lg border border-text-muted/20 px-4 py-2 text-sm font-medium text-text" onclick="seleccionarMetodo('DNI')">
                        Buscar por DNI
                    </button>
                </div>
            </div>

            <input type="hidden" name="metodo_identificacion" id="metodo_identificacion" value="{{ $dniPrefill ? 'DNI' : 'QR' }}">

            <x-forms.input name="identificador" id="input-identificador" label="Token QR del proveedor" :value="$dniPrefill" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-forms.input name="litros" type="number" label="Litros" required />
                <x-forms.input name="fecha_hora" type="datetime-local" label="Fecha y hora (opcional)" />
            </div>
            <x-forms.textarea name="observacion" label="Observación" :rows="3" />

            <x-ui.button type="submit">Registrar entrega</x-ui.button>
        </form>
    </x-ui.card>

    <script>
        function seleccionarMetodo(metodo) {
            document.getElementById('metodo_identificacion').value = metodo;
            document.getElementById('btn-metodo-qr').className = metodo === 'QR'
                ? 'rounded-lg border border-primary bg-primary px-4 py-2 text-sm font-medium text-white'
                : 'rounded-lg border border-text-muted/20 px-4 py-2 text-sm font-medium text-text';
            document.getElementById('btn-metodo-dni').className = metodo === 'DNI'
                ? 'rounded-lg border border-primary bg-primary px-4 py-2 text-sm font-medium text-white'
                : 'rounded-lg border border-text-muted/20 px-4 py-2 text-sm font-medium text-text';

            const label = document.querySelector('label[for="identificador"]');
            const input = document.getElementById('input-identificador');
            if (metodo === 'QR') {
                label.innerHTML = 'Token QR del proveedor <span class="text-danger" aria-hidden="true">*</span>';
                input.placeholder = 'Escanea o pega el token QR';
            } else {
                label.innerHTML = 'DNI del proveedor <span class="text-danger" aria-hidden="true">*</span>';
                input.placeholder = '8 dígitos';
            }
        }

        @if ($dniPrefill)
            seleccionarMetodo('DNI');
        @endif
    </script>
@endsection
