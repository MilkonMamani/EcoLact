@extends('layouts.admin')

@section('title', 'Mi perfil')

@section('content')
    <x-ui.card :title="$administrador->username" subtitle="Mi perfil">
        <x-slot:actions>
            <x-ui.badge status="ADMINISTRADOR" />
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Usuario</dt>
                <dd class="mt-1 text-sm text-text">{{ $administrador->username }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Rol</dt>
                <dd class="mt-1 text-sm text-text">Administrador</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card>
        <x-pagos.control-periodo :control-periodo="$controlPeriodo" :trabajador-id="$administrador->id" :trabajador-nombre="$administrador->username" />
    </x-ui.card>
@endsection
