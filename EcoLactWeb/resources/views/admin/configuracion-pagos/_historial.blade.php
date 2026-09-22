{{-- Vigencias anteriores (o posteriores): se despliegan en la misma fila, sin salir de la pantalla. --}}
<details class="text-sm">
    <summary class="cursor-pointer select-none text-xs font-medium text-primary hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
        Historial de vigencias ({{ count($fila['historial']) + ($esProveedor ? max(count($precios) - 1, 0) : 0) }})
    </summary>
    <div class="mt-2 grid gap-4 text-xs md:grid-cols-2">
        @if (count($fila['historial']) > 0)
            <div>
                <p class="mb-1 font-semibold uppercase tracking-wide text-label">{{ $esProveedor ? 'Configuración' : 'Sueldo' }}</p>
                <ul class="divide-y divide-text-muted/10 text-label">
                    @foreach ($fila['historial'] as $version)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                            <span>{{ $version->fechaInicio()->format('d/m/Y') }} – {{ $version->fechaFin()?->format('d/m/Y') ?? 'vigente' }}</span>
                            <span class="text-text">
                                @if ($esProveedor)
                                    Por litros · {{ $version->aplicaCalidad() ? 'con ajustes de calidad' : 'sin ajustes de calidad' }}
                                @else
                                    S/ {{ number_format((float) $version->montoBase(), 2) }} mensual
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($esProveedor && count($precios) > 0)
            <div>
                <p class="mb-1 font-semibold uppercase tracking-wide text-label">Precio base por litro</p>
                <ul class="divide-y divide-text-muted/10 text-label">
                    @foreach ($precios as $precio)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                            <span>{{ $precio->fechaInicio()->format('d/m/Y') }} – {{ $precio->fechaFin()?->format('d/m/Y') ?? 'vigente' }}</span>
                            <span class="text-text">S/ {{ number_format($precio->precioLitro(), 2) }} por litro</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</details>
