{{-- Proveedor, zona, litros y acopiador salen de la ENTREGA, nunca del ticket del analizador. --}}
<dl class="grid gap-3 rounded-xl bg-text-muted/5 p-4 text-sm sm:grid-cols-3">
    <div>
        <dt class="text-xs uppercase tracking-wide text-text-muted">Proveedor</dt>
        <dd class="mt-1 font-medium text-text">{{ $entrega->proveedor ? $entrega->proveedor->nombres.' '.$entrega->proveedor->apellidos : '—' }}</dd>
    </div>
    <div>
        <dt class="text-xs uppercase tracking-wide text-text-muted">DNI</dt>
        <dd class="mt-1 font-medium tabular-nums text-text">{{ $entrega->proveedor?->dni ?? '—' }}</dd>
    </div>
    <div>
        <dt class="text-xs uppercase tracking-wide text-text-muted">Entrega</dt>
        <dd class="mt-1 font-medium tabular-nums text-text">#{{ $entrega->id }}</dd>
    </div>
    <div>
        <dt class="text-xs uppercase tracking-wide text-text-muted">Zona</dt>
        <dd class="mt-1 font-medium text-text">{{ $entrega->zona?->nombre ?? '—' }}</dd>
    </div>
    <div>
        <dt class="text-xs uppercase tracking-wide text-text-muted">Litros</dt>
        <dd class="mt-1 font-medium text-text">{{ number_format((float) $entrega->litros, 2) }} L</dd>
    </div>
    <div>
        <dt class="text-xs uppercase tracking-wide text-text-muted">Acopiador</dt>
        <dd class="mt-1 font-medium text-text">{{ $entrega->acopiador ? $entrega->acopiador->nombres.' '.$entrega->acopiador->apellidos : '—' }}</dd>
    </div>
</dl>
