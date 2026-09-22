@extends('layouts.calidad')

@section('title', 'Entrega #' . $entrega->id())

@section('content')
    <x-ui.button :href="route('calidad.entregas.index')" variant="ghost" size="sm" as="a">&larr; Volver a entregas</x-ui.button>

    <x-ui.card :title="'Entrega #' . $entrega->id()" subtitle="Detalle de la entrega de leche">
        <x-slot:actions>
            <x-ui.badge :status="$entrega->estado()->value" />
            @if ($entrega->estado()->value === 'PENDIENTE_ANALISIS')
                <x-ui.button :href="route('calidad.entregas.analisis.index', $entrega->id())" size="sm" as="a">🧪 Analizar →</x-ui.button>
            @else
                <x-ui.button :href="route('calidad.entregas.analisis.index', $entrega->id())" variant="ghost" size="sm" as="a">Ver análisis →</x-ui.button>
            @endif
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Proveedor</dt>
                <dd class="mt-1 text-sm text-text">
                    <a class="text-primary hover:underline" href="{{ route('calidad.proveedores.show', $entrega->proveedorId()) }}">{{ $registro->proveedor ? trim($registro->proveedor->nombres.' '.$registro->proveedor->apellidos) : 'Proveedor #'.$entrega->proveedorId() }}</a>
                    <span class="block text-xs text-text-muted">DNI: {{ $registro->proveedor?->dni ?? '—' }}</span>
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Zona</dt>
                <dd class="mt-1 text-sm text-text">{{ $registro->zona?->nombre ?? 'Zona #'.$entrega->zonaId() }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Acopiador</dt>
                <dd class="mt-1 text-sm text-text">{{ $registro->acopiador ? trim($registro->acopiador->nombres.' '.$registro->acopiador->apellidos) : '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha y hora</dt>
                <dd class="mt-1 text-sm text-text">{{ $entrega->fechaHora()->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Litros entregados</dt>
                <dd class="mt-1 text-sm text-text">{{ number_format($entrega->litros(), 2) }} L</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Método de identificación</dt>
                <dd class="mt-1 text-sm text-text">{{ $entrega->metodoIdentificacion() }}</dd>
            </div>
            @if ($entrega->observacion())
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Observación</dt>
                    <dd class="mt-1 text-sm text-text">{{ $entrega->observacion() }}</dd>
                </div>
            @endif
        </dl>

        <div class="mt-6 flex items-center gap-3 text-sm">
            <span class="text-success">ENTREGA ✓</span>
            <span class="text-text-muted">→</span>
            @if ($entrega->estado()->value === 'PENDIENTE_ANALISIS')
                <span class="text-text-muted">ANÁLISIS ○ Pendiente</span>
            @else
                <span class="text-success">ANÁLISIS ✓</span>
            @endif
        </div>
    </x-ui.card>
@endsection
