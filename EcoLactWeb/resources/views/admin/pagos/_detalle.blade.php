{{-- Detalle de un pago ya realizado: se despliega en la misma fila. Es solo lectura (un pago pagado no se edita). --}}
<div class="grid gap-6 md:grid-cols-2">
    <div>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-text-muted">Cálculo del periodo</h3>
        <dl class="mt-2 space-y-1.5 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-text-muted">Regla de pago</dt><dd class="text-right text-text">{{ $fila['regla'] }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-text-muted">Periodo</dt><dd class="text-right text-text">{{ $fila['periodo'] }} · {{ $fila['rango'] }}</dd></div>
            @foreach ($fila['detalle'] as $etiqueta => $valor)
                <div class="flex justify-between gap-4"><dt class="text-text-muted">{{ $etiqueta }}</dt><dd class="text-right text-text">{{ $valor }}</dd></div>
            @endforeach
            <div class="flex justify-between gap-4 border-t border-text-muted/10 pt-1.5 font-semibold"><dt class="text-text">Monto pagado</dt><dd class="text-right text-text">S/ {{ number_format($fila['monto'], 2) }}</dd></div>
        </dl>
    </div>

    <div>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-text-muted">Pago registrado</h3>
        @if ($fila['pago'])
            <dl class="mt-2 space-y-1.5 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Fecha de pago</dt><dd class="text-right text-text">{{ $fila['pago']['fecha'] }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Método</dt><dd class="text-right text-text">{{ $fila['pago']['metodo'] }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">N° de operación</dt><dd class="text-right text-text">{{ $fila['pago']['operacion'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Registrado por</dt><dd class="text-right text-text">{{ $fila['pago']['registradoPor'] }}</dd></div>
                @if ($fila['pago']['observacion'])
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Observación</dt><dd class="text-right text-text">{{ $fila['pago']['observacion'] }}</dd></div>
                @endif
            </dl>
            <div class="mt-3 flex flex-wrap gap-2">
                @if ($fila['pago']['comprobante'])
                    <x-ui.action-button type="download" :text="true" label="Ver comprobante" :href="route('admin.pagos.comprobante', $fila['id'])" target="_blank" />
                @else
                    <span class="text-xs text-text-muted">Sin comprobante adjunto.</span>
                @endif
                @if ($fila['liquidacionId'])
                    <x-ui.action-button type="print" :text="true" label="Imprimir liquidación" :href="route('admin.liquidaciones.imprimir', $fila['liquidacionId'])" target="_blank" />
                @endif
            </div>
        @else
            <p class="mt-2 text-sm text-text-muted">No hay datos del pago.</p>
        @endif
    </div>
</div>
