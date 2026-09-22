@extends('layouts.acopiador')

@section('title', 'Recorrido · '.$vista->rutaNombre)

@section('content')
    <x-ui.button :href="route('acopiador.recorridos.index')" variant="ghost" size="sm" as="a">&larr; Mis recorridos</x-ui.button>

    <div data-recorrido data-recorrido-id="{{ $vista->id }}" class="space-y-4">
        <script type="application/json" id="recorrido-paradas-data">
            {{ Js::from(['paradas' => collect($vista->paradas)->map(fn ($p) => [
                'id' => $p->id,
                'proveedorId' => $p->proveedorId,
                'proveedorNombre' => $p->proveedorNombre,
                'proveedorDni' => $p->proveedorDni,
                'ordenVisita' => $p->ordenVisita,
                'latitud' => $p->latitud,
                'longitud' => $p->longitud,
                'referenciaUbicacion' => $p->referenciaUbicacion,
                'estado' => $p->estado,
                'observacion' => $p->observacion,
                'litrosRegistrados' => $p->litrosRegistrados,
            ])]) }}
        </script>

        <x-ui.card :title="$vista->rutaNombre" subtitle="{{ $vista->zonaNombre }} · {{ \Illuminate\Support\Carbon::parse($vista->fechaOperativa)->format('d/m/Y') }}">
            <x-slot:actions>
                <x-ui.badge :status="$vista->estado" />
                @if ($vista->estado === 'PENDIENTE')
                    <form method="POST" action="{{ route('acopiador.recorridos.iniciar', $vista->id) }}">
                        @csrf
                        @method('PATCH')
                        <x-ui.button type="submit" variant="primary" size="sm">Iniciar recorrido</x-ui.button>
                    </form>
                @endif
                @if (in_array($vista->estado, ['PENDIENTE', 'EN_PROGRESO'], true))
                    <form method="POST" action="{{ route('acopiador.recorridos.finalizar', $vista->id) }}" data-confirm="¿Finalizar este recorrido? Ya no podrás registrar más acopios en él.">
                        @csrf
                        @method('PATCH')
                        <x-ui.button type="submit" variant="secondary" size="sm">Finalizar</x-ui.button>
                    </form>
                @endif
            </x-slot:actions>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-text-muted/10 bg-surface p-4">
                    <p class="text-xs text-text-muted">Recolectados</p>
                    <p data-progreso-recolectados class="mt-1 text-2xl font-semibold text-success">{{ $vista->progreso['recolectados'] }}</p>
                </div>
                <div class="rounded-xl border border-text-muted/10 bg-surface p-4">
                    <p class="text-xs text-text-muted">Pendientes</p>
                    <p data-progreso-pendientes class="mt-1 text-2xl font-semibold text-text">{{ $vista->progreso['pendientes'] }}</p>
                </div>
                <div class="rounded-xl border border-text-muted/10 bg-surface p-4">
                    <p class="text-xs text-text-muted">No entregó</p>
                    <p data-progreso-no-entrego class="mt-1 text-2xl font-semibold text-warning">{{ $vista->progreso['no_entrego'] }}</p>
                </div>
                <div class="rounded-xl border border-text-muted/10 bg-surface p-4">
                    <p class="text-xs text-text-muted">Litros acumulados</p>
                    <p data-progreso-litros class="mt-1 text-2xl font-semibold text-text">{{ number_format($vista->progreso['litros_validos'], 2) }}</p>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Identificar proveedor">
            <div class="flex flex-wrap items-end gap-2">
                <div class="flex-1">
                    <x-forms.input name="buscar_dni" data-buscar-dni-input label="DNI del proveedor" placeholder="8 dígitos" autocomplete="off" inputmode="numeric" />
                </div>
                <x-ui.button data-buscar-dni-boton type="button" variant="secondary" size="sm">Buscar</x-ui.button>
                <x-ui.button data-escanear-boton type="button" variant="secondary" size="sm">Escanear QR</x-ui.button>
            </div>
            <p data-buscar-dni-error class="mt-2 hidden text-sm text-danger"></p>
            <p data-escanear-aviso hidden class="mt-2 text-sm text-warning"></p>
            <video data-escanear-video hidden muted playsinline class="mt-2 w-full max-w-sm rounded-lg"></video>
            <p class="mt-2 text-xs text-text-muted">El escaneo solo reconoce el código QR interno de EcoLact (el que se genera en el perfil del proveedor). No lee documentos de identidad físicos.</p>
        </x-ui.card>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-ui.card title="Mapa" subtitle="Toca un punto para identificarlo.">
                <div class="mb-2 flex flex-wrap gap-3 text-xs text-text-muted">
                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full" style="background:#2563eb"></span> Pendiente</span>
                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full" style="background:#16a34a"></span> Recolectado</span>
                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full" style="background:#ea580c"></span> No entregó</span>
                </div>
                <div data-recorrido-mapa class="h-72 w-full rounded-lg bg-text-muted/5"></div>
                <p data-recorrido-mapa-aviso hidden class="mt-2 text-sm text-warning">No se pudo cargar el mapa (falta configurar la clave o hubo un error de red). La lista y el registro manual siguen funcionando con normalidad.</p>
            </x-ui.card>

            <x-ui.card title="Paradas">
                <div data-recorrido-lista class="max-h-72 space-y-2 overflow-y-auto"></div>
            </x-ui.card>
        </div>

        <x-ui.card title="Parada seleccionada">
            <div data-parada-panel>
                <p class="text-sm text-text-muted">Selecciona una parada en el mapa o en la lista, o identifica al proveedor por DNI/QR.</p>
            </div>
        </x-ui.card>
    </div>
@endsection
