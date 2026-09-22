@php
    $layout = match (true) {
        auth()->user()->esAdministrador() => 'layouts.admin',
        auth()->user()->esAcopiador() => 'layouts.acopiador',
        auth()->user()->esCalidad() => 'layouts.calidad',
        default => 'layouts.proveedor',
    };
@endphp

@extends($layout)

@section('title', 'Notificaciones')

@section('content')
    <x-ui.card title="Notificaciones" subtitle="Avisos del sistema para tu cuenta.">
        @if (count($notificaciones) === 0)
            <x-ui.empty-state title="No tienes notificaciones" description="Aquí aparecerán los avisos relacionados con tu actividad en EcoLact." />
        @else
            <ul class="divide-y divide-text-muted/10">
                @foreach ($notificaciones as $notificacion)
                    <li class="flex items-start gap-3 py-4 {{ $notificacion->leido() ? '' : 'bg-primary-soft/40 -mx-5 px-5' }}">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $notificacion->leido() ? 'bg-text-muted/10 text-text-muted' : 'bg-primary-soft text-primary-dark' }}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-text">{{ $notificacion->titulo() }}</p>
                                @if (! $notificacion->leido())
                                    <span class="h-2 w-2 rounded-full bg-accent" aria-label="No leída"></span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-sm text-text-muted">{{ $notificacion->mensaje() }}</p>
                        </div>

                        @unless ($notificacion->leido())
                            <form method="POST" action="{{ route('notificaciones.marcar-leida', $notificacion->id()) }}">
                                @csrf
                                @method('PATCH')
                                <x-ui.button type="submit" variant="ghost" size="sm">Marcar como leída</x-ui.button>
                            </form>
                        @endunless
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
@endsection
