@extends('layouts.acopiador')

@section('title', $comunicado->titulo())

@section('content')
    <x-ui.button :href="route('acopiador.comunicados.index')" variant="ghost" size="sm" as="a">&larr; Volver a comunicados</x-ui.button>

    <x-ui.card :title="$comunicado->titulo()" :subtitle="'Publicado el ' . $comunicado->fechaPublicacion()->format('d/m/Y')">
        <x-slot:actions>
            <x-ui.badge variant="info">{{ $comunicado->tipo() }}</x-ui.badge>
        </x-slot:actions>

        <p class="whitespace-pre-line text-sm text-text">{{ $comunicado->contenido() }}</p>

        @if ($comunicado->fechaEvento())
            <p class="mt-4 text-sm text-text-muted">Fecha del evento: {{ $comunicado->fechaEvento()->format('d/m/Y') }}</p>
        @endif
    </x-ui.card>
@endsection
