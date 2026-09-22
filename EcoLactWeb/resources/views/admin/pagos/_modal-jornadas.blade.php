{{--
    Fragmento HTML (sin layout) del contenido del modal "Ver jornadas": lo pide
    el JS al abrir el modal (ver resources/js/pages/admin-jornadas.js).
    Recibe: $trabajador (App\Models\User), $jornadas (App\Application\Pago\JornadaPersonalFila[])
--}}
@if (count($jornadas) === 0)
    <x-ui.empty-state title="Aún no hay jornadas registradas" description="Registra el primer día trabajado del periodo con 'Registrar jornada'." :icon="false" />
@else
    <div class="max-h-80 overflow-y-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-text-muted/10 text-[11px] font-medium uppercase tracking-wider text-label">
                    <th class="py-2 pr-3">Fecha</th>
                    <th class="py-2 pl-3">Observación</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($jornadas as $jornada)
                    <tr class="border-b border-text-muted/5 last:border-0">
                        <td class="whitespace-nowrap py-2 pr-3 text-text">{{ $jornada->fechaEtiqueta }}</td>
                        <td class="py-2 pl-3 text-text-muted">{{ $jornada->observacion ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-sm text-text-muted">Total: <strong class="text-text">{{ count($jornadas) }} días trabajados</strong></p>
@endif
