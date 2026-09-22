@extends('layouts.admin')

@section('title', 'Entrega #' . $entrega->id())

@section('content')
    <x-ui.button :href="route('admin.entregas.index')" variant="ghost" size="sm" as="a">&larr; Volver a entregas</x-ui.button>

    <x-ui.card :title="'Entrega #' . $entrega->id()" subtitle="Detalle de la entrega de leche">
        <x-slot:actions>
            <x-ui.badge :status="$entrega->estado()->value" />
            @can('update', $registro)
                @if ($entrega->estado()->value === 'PENDIENTE_ANALISIS')
                    <x-ui.action-button type="edit" :text="true" label="Editar" data-modal-open="modal-editar-entrega" />
                @endif
            @endcan
            @if ($entrega->estado()->value !== 'PENDIENTE_ANALISIS')
                <x-ui.button :href="route('admin.entregas.analisis-calidad.index', $entrega->id())" variant="ghost" size="sm" as="a">Ver análisis</x-ui.button>
            @endif
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Proveedor</dt>
                <dd class="mt-1 text-sm text-text">
                    <a class="text-primary hover:underline" href="{{ route('admin.proveedores.show', $entrega->proveedorId()) }}">{{ $registro->proveedor ? trim($registro->proveedor->nombres.' '.$registro->proveedor->apellidos) : 'Proveedor #'.$entrega->proveedorId() }}</a>
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

        <div class="mt-6 rounded-xl border border-text-muted/10 p-4">
            <h3 class="mb-3 text-sm font-semibold text-text">Registro</h3>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Registrado por</dt>
                    <dd class="mt-1 text-sm text-text">{{ $registro->registradoPor?->username ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha de registro</dt>
                    <dd class="mt-1 text-sm text-text">{{ $registro->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
            </dl>
        </div>

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

    <x-ui.card title="Análisis de calidad" subtitle="Resultado del análisis asociado a esta entrega.">
        @if ($entrega->estado()->value === 'PENDIENTE_ANALISIS')
            <p class="text-sm text-text-muted">⏳ Esta entrega todavía no tiene un análisis de calidad asociado.</p>
        @else
            <x-slot:actions>
                <x-ui.button :href="route('admin.entregas.analisis-calidad.index', $entrega->id())" variant="ghost" size="sm" as="a">Ver análisis completo →</x-ui.button>
            </x-slot:actions>
            <p class="text-sm text-text-muted">Consulta el resultado del análisis de calidad en la sección enlazada arriba.</p>
        @endif
    </x-ui.card>

    @can('update', $registro)
        @if ($entrega->estado()->value === 'PENDIENTE_ANALISIS')
        <x-ui.modal id="modal-editar-entrega" title="Editar entrega">
            <form method="POST" action="{{ route('admin.entregas.update', $entrega->id()) }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-forms.input name="proveedor_id" type="number" label="ID de proveedor" :value="$entrega->proveedorId()" />
                    <x-forms.input name="zona_id" type="number" label="ID de zona" :value="$entrega->zonaId()" />
                    <x-forms.input name="acopiador_id" type="number" label="ID de acopiador" :value="$entrega->acopiadorId()" />
                    <x-forms.input name="fecha_hora" type="datetime-local" label="Fecha y hora" :value="$entrega->fechaHora()->format('Y-m-d\TH:i')" />
                    <x-forms.input name="litros" type="number" step="any" label="Litros" :value="$entrega->litros()" />
                    <x-forms.select name="metodo_identificacion" label="Método de identificación" :options="['QR' => 'QR', 'DNI' => 'DNI']" :value="$entrega->metodoIdentificacion()" />
                </div>
                <x-forms.textarea name="observacion" label="Observación" :rows="3" :value="$entrega->observacion()" />
                <x-forms.input name="motivo_modificacion" label="Motivo de modificación" required placeholder="¿Por qué se modifica esta entrega?" />
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                    <x-ui.button type="submit">Guardar cambios</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
        @endif
    @endcan
@endsection
