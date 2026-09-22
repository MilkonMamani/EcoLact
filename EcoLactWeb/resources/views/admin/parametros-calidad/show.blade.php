@extends('layouts.admin')

@section('title', $parametro->nombre())

@section('content')
    <x-ui.button :href="route('admin.parametros-calidad.index')" variant="ghost" size="sm" as="a">&larr; Volver a parámetros</x-ui.button>

    <x-ui.card :title="$parametro->nombre()" subtitle="Detalle del parámetro de calidad">
        <x-slot:actions>
            <x-ui.badge :status="$parametro->estado()" />
            <x-ui.action-button type="edit" :text="true" label="Editar" data-modal-open="modal-editar-parametro" />
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Código</dt>
                <dd class="mt-1 font-mono text-sm text-text">{{ $parametro->codigo() }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Unidad</dt>
                <dd class="mt-1 text-sm text-text">{{ $parametro->unidad() ?? '—' }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card title="Reglas de calidad" subtitle="Límites definidos para evaluar este parámetro.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.parametros-calidad.reglas-calidad.index', $parametro->id())" variant="ghost" size="sm" as="a">Ver reglas</x-ui.button>
        </x-slot:actions>
        <p class="text-sm text-text-muted">Consulta o define los rangos válidos para este parámetro en la sección enlazada arriba.</p>
    </x-ui.card>

    <x-ui.modal id="modal-editar-parametro" title="Editar parámetro">
        <form method="POST" action="{{ route('admin.parametros-calidad.update', $parametro->id()) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <x-forms.input name="nombre" label="Nombre" :value="$parametro->nombre()" required />
            <x-forms.input name="unidad" label="Unidad" :value="$parametro->unidad()" />
            <x-forms.select name="estado" label="Estado" required :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" :value="$parametro->estado()" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar cambios</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
