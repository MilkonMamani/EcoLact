@extends('layouts.acopiador')

@section('title', 'Comunicados')

@section('content')
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Comunicados" :value="$kpis['total']" hint="Publicados" />
        <x-ui.stat-card label="No leídos" :value="$kpis['noLeidos']" accent="warning" />
        <x-ui.stat-card label="Último" :value="$kpis['ultimo']?->fechaPublicacion()?->format('d/m/Y') ?? '—'" />
    </div>

    <x-ui.card title="Comunicados" subtitle="Mantente informado sobre novedades, actividades y avisos importantes de EcoLact.">
        @if (count($comunicados) === 0)
            <x-ui.empty-state title="No hay comunicados" description="Cuando EcoLact publique información dirigida a ti, aparecerá aquí." />
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($comunicados as $comunicado)
                    @php $leido = $lecturas[$comunicado->id()] ?? false; @endphp
                    <a href="{{ route('acopiador.comunicados.show', $comunicado->id()) }}" class="block rounded-xl border p-4 shadow-sm transition-shadow hover:shadow-md {{ $leido ? 'border-text-muted/10 bg-surface' : 'border-primary/30 bg-primary-soft/40' }}">
                        <div class="flex items-center justify-between gap-2">
                            <x-ui.badge variant="info">{{ $comunicado->tipo() }}</x-ui.badge>
                            <span class="text-xs text-text-muted">{{ $comunicado->fechaPublicacion()->format('d/m/Y') }}</span>
                        </div>
                        <p class="mt-2 font-medium text-text">
                            @unless ($leido)
                                <span class="mr-1 inline-block h-2 w-2 rounded-full bg-primary align-middle" aria-label="No leído"></span>
                            @endunless
                            {{ $comunicado->titulo() }}
                        </p>
                        <p class="mt-1 line-clamp-2 text-sm text-text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($comunicado->contenido()), 120) }}</p>
                        <p class="mt-2 text-xs {{ $leido ? 'text-text-muted' : 'font-medium text-primary' }}">{{ $leido ? '✓ Leído' : '● No leído' }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </x-ui.card>
@endsection
