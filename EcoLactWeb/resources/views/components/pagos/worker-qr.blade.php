@props(['qrToken', 'nombre'])

{{-- Card del QR de asistencia de un trabajador: nunca muestra el token crudo, solo el código generado (mismo patrón que resources/views/acopiador/proveedores/qr.blade.php). --}}
<x-ui.profile-section title="Código QR de asistencia" icon="qr-code" {{ $attributes }}>
    @if ($qrToken)
        <div class="flex flex-col items-center gap-3 py-4 text-center">
            <div class="flex h-48 w-48 items-center justify-center rounded-2xl border-2 border-primary/30 bg-primary-soft p-3">
                <canvas data-qr-code="{{ base64_encode($qrToken) }}" class="rounded-lg bg-white p-2" aria-label="Código QR de asistencia de {{ $nombre }}"></canvas>
            </div>
            <p class="max-w-xs text-xs text-text-muted">Presenta este código al administrador para registrar tu asistencia.</p>
        </div>
    @else
        <p class="text-sm text-text-muted">Aún no se generó un código QR para este trabajador.</p>
    @endif
</x-ui.profile-section>
