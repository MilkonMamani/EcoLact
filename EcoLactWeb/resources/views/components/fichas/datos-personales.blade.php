@props(['ficha', 'modalEditar'])

{{--
    Resumen de las fichas de personal: QR real de asistencia (trabajadores.qr_token; si no
    tiene, no se inventa) e información personal con su único "Editar".
--}}
@if ($ficha->qrToken)
    <div class="fichas-qr is-grande">
        <canvas data-qr-code="{{ base64_encode($ficha->qrToken) }}" aria-label="Código QR de {{ $ficha->nombreCompleto() }}"></canvas>
        <p>Código QR</p>
    </div>
@endif

<div @class(['fichas-seccion', 'is-primera' => ! $ficha->qrToken])>
    <div class="fichas-seccion-head">
        <h3>Información personal</h3>
        <button type="button" class="fichas-editar" data-modal-open="{{ $modalEditar }}" aria-label="Editar información personal de {{ $ficha->nombreCompleto() }}">
            <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar
        </button>
    </div>
    <dl class="fichas-datos">
        <div><dt>Nombres</dt><dd>{{ $ficha->nombres }}</dd></div>
        <div><dt>Apellidos</dt><dd>{{ $ficha->apellidos }}</dd></div>
        <div><dt>Documento (DNI)</dt><dd>{{ $ficha->dni }}</dd></div>
        <div><dt>Teléfono</dt><dd @class(['is-muted' => ! $ficha->telefono])>{{ $ficha->telefono ?? 'No registrado' }}</dd></div>
        <div><dt>Fecha de ingreso</dt><dd @class(['is-muted' => ! $ficha->fechaIngreso])>{{ $ficha->fechaIngreso ?? 'Sin registrar' }}</dd></div>
        <div><dt>Estado</dt><dd>{{ $ficha->estaActivo() ? 'Activo' : 'Inactivo' }}</dd></div>
    </dl>
</div>
