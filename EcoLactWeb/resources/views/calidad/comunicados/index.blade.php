@extends('layouts.calidad')

@section('title', 'Comunicados')

@section('content')
    <x-ui.card title="Comunicados" subtitle="Avisos publicados para ti.">
        @if (count($comunicados) === 0)
            <x-ui.empty-state title="No tienes comunicados" description="Los avisos dirigidos a ti aparecerán aquí." />
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($comunicados as $comunicado)
                    <a href="{{ route('calidad.comunicados.show', $comunicado->id()) }}" class="block rounded-xl border border-text-muted/10 bg-surface p-4 shadow-sm transition-shadow hover:shadow-md">
                        <div class="flex items-center justify-between gap-2">
                            <x-ui.badge variant="info">{{ $comunicado->tipo() }}</x-ui.badge>
                            <span class="text-xs text-text-muted">{{ $comunicado->fechaPublicacion()->format('d/m/Y') }}</span>
                        </div>
                        <p class="mt-2 font-medium text-text">{{ $comunicado->titulo() }}</p>
                        <p class="mt-1 line-clamp-2 text-sm text-text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($comunicado->contenido()), 120) }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </x-ui.card>
@endsection
