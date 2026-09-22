@php
    /** @var list<\App\Application\Zona\ProveedorAsignadoFila> $proveedores */
    /** @var bool $esSinAsignar */
    /** @var ?array<string, mixed> $zonaSeleccionada */
    $conUbicacion = collect($proveedores)->filter(fn ($fila) => $fila->tieneUbicacion())->values();
    $titulo = $esSinAsignar ? 'Sin zona asignada' : ($zonaSeleccionada['nombre'] ?? '');
@endphp

<x-ui.card :padded="false">
    <div class="border-b border-text-muted/10 px-5 py-4">
        <h2 class="text-base font-semibold text-text">{{ $titulo }}</h2>
        <p class="mt-0.5 text-sm text-text-muted">
            @if (count($proveedores) > 0)
                {{ $conUbicacion->count() }} de {{ count($proveedores) }} proveedores con ubicación
            @else
                Sin proveedores en esta selección
            @endif
        </p>
    </div>

    <div class="p-4">
        @if ($conUbicacion->isEmpty())
            <div class="flex h-72 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-text-muted/25 bg-background/60 px-6 text-center lg:h-80">
                <x-ui.icon name="map-pin" class="h-8 w-8 text-text-muted/50" />
                <p class="text-sm font-medium text-text">Sin ubicaciones registradas</p>
                <p class="max-w-[16rem] text-xs text-text-muted">
                    {{ $esSinAsignar ? 'Los proveedores sin zona todavía no tienen coordenadas disponibles.' : 'Ningún proveedor de esta zona tiene coordenadas todavía.' }}
                </p>
            </div>
        @else
            <div data-zona-mapa-lienzo class="h-72 w-full rounded-lg bg-text-muted/5 ring-1 ring-text-muted/15 lg:h-80"></div>
            <p data-zona-mapa-aviso hidden class="mt-2 text-xs text-warning"></p>
        @endif
    </div>
</x-ui.card>

@unless ($conUbicacion->isEmpty())
    <script type="application/json" data-zona-mapa-datos>{!! json_encode($conUbicacion->map(fn ($fila) => [
        'id' => $fila->proveedorId,
        'nombre' => $fila->nombre,
        'dni' => $fila->dni,
        'zonaNombre' => $fila->zonaNombre,
        'lat' => $fila->latitud,
        'lng' => $fila->longitud,
    ])->values()) !!}</script>
@endunless
