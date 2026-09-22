@extends('layouts.proveedor')

@section('title', 'Mi perfil')

@section('content')
    <x-ui.card title="Mi perfil" subtitle="Información registrada en EcoLact.">
        <x-slot:actions>
            <x-ui.badge :status="$proveedor->estado()->value" />
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Nombre completo</dt>
                <dd class="mt-1 text-sm text-text">{{ $proveedor->nombreCompleto() }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">DNI</dt>
                <dd class="mt-1 text-sm text-text">{{ $proveedor->dni() }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Teléfono</dt>
                <dd class="mt-1 text-sm text-text">{{ $proveedor->telefono() ?? 'Sin registrar' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Zona</dt>
                <dd class="mt-1 text-sm text-text">Zona #{{ $proveedor->zonaId() }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Miembro desde</dt>
                <dd class="mt-1 text-sm text-text">{{ $proveedor->fechaRegistro()->format('d/m/Y') }}</dd>
            </div>
        </dl>
    </x-ui.card>
@endsection
