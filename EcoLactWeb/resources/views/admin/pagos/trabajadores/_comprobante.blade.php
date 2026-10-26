{{--
    Hoja A4 vertical "Comprobante de pago" de un trabajador. La misma hoja se ve escalada en el
    panel derecho de Pagos a trabajadores, en la página individual y en el papel: siempre UNA sola
    hoja con las firmas al pie. Documento formal sin logo gráfico (solo el texto "EcoLact").
    Estilos en resources/css/components/comprobante-pago.css.

    Solo pinta ComprobantePagoTrabajador (snapshot de la obligación del mes): no calcula dinero ni
    muestra reglas internas de configuración.

    @var \App\Application\Pago\ComprobantePagoTrabajador $c
--}}
@php
    $soles = fn (float $valor): string => 'S/ '.number_format($valor, 2);
    $dias = fn (int $cantidad): string => $cantidad.' '.($cantidad === 1 ? 'día' : 'días');
    $empresa = config('empresa.nombre') ?: 'EcoLact';
    $estado = match ($c->estado) {
        'PAGADO' => 'Pagado',
        'PENDIENTE' => 'Pendiente',
        'EN_CURSO' => 'En curso',
        default => $c->estado,
    };
@endphp
<article class="cp-hoja" data-comprobante="{{ $c->obligacionId }}" aria-label="Comprobante {{ $c->numero() }} - {{ $c->nombre }}">
    <div class="cp-cuerpo">
        <header class="cp-cabecera">
            <p class="cp-emisor">{{ $empresa }}</p>
            <h1>Comprobante de pago</h1>
            <p class="cp-numero">N.° {{ $c->numero() }}</p>
        </header>

        <dl class="cp-fechas">
            <div>
                <dt>Fecha de emisión</dt>
                <dd>{{ $c->fechaEmision }}</dd>
            </div>
            <div>
                <dt>Periodo</dt>
                <dd>{{ $c->periodoEtiqueta }}<small>{{ $c->periodoRango }}</small></dd>
            </div>
            <div>
                <dt>Fecha de pago</dt>
                <dd>{{ $c->estaPagado() && $c->pagadoEl ? $c->pagadoEl : '—' }}</dd>
            </div>
        </dl>

        <section class="cp-seccion" aria-label="Datos del trabajador">
            <h2>Datos del trabajador</h2>
            <dl class="cp-datos">
                <div class="is-ancho"><dt>Nombre</dt><dd class="cp-nombre">{{ $c->nombre }}</dd></div>
                <div><dt>DNI</dt><dd>{{ $c->dni ?? '—' }}</dd></div>
                <div><dt>Rol</dt><dd>{{ $c->rol }}</dd></div>
                <div><dt>Fecha de ingreso</dt><dd>{{ $c->fechaIngresoEtiqueta ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="cp-seccion" aria-label="Detalle del pago">
            <h2>Detalle del pago</h2>
            <dl class="cp-detalle">
                <div><dt>Sueldo mensual</dt><dd>{{ $soles($c->sueldoBase) }}</dd></div>
                <div><dt>Valor por día</dt><dd>{{ $soles($c->valorDia) }}</dd></div>
                <div><dt>Asistencias</dt><dd>{{ $dias($c->diasTrabajados) }}</dd></div>
                <div><dt>Faltas</dt><dd @class(['is-negativo' => $c->faltas > 0])>{{ $dias($c->faltas) }}</dd></div>
                <div class="is-subtotal">
                    <dt>Acumulado @unless ($c->esMesCompleto)<small>Proporcional a los días trabajados del periodo</small>@endunless</dt>
                    <dd>{{ $soles($c->acumulado) }}</dd>
                </div>
                <div><dt>Bonificaciones</dt><dd>{{ $c->bonificacion > 0 ? '+ '.$soles($c->bonificacion) : $soles(0) }}</dd></div>
                <div><dt>Descuentos</dt><dd @class(['is-negativo' => $c->descuento > 0])>{{ $c->descuento > 0 ? '− '.$soles($c->descuento) : $soles(0) }}</dd></div>
            </dl>

            <div class="cp-total">
                <span>{{ $c->estaPagado() ? 'Total pagado' : 'Total a pagar' }}</span>
                <strong data-comprobante-total>{{ $soles($c->total) }}</strong>
            </div>

            <dl class="cp-estado">
                <div><dt>Estado</dt><dd data-comprobante-estado>{{ $estado }}@if ($c->estado === 'EN_CURSO')<small>El mes aún no ha cerrado: los montos son parciales.</small>@endif</dd></div>
                @if ($c->estaPagado())
                    <div><dt>Método de pago</dt><dd>{{ $c->metodoPago }}@if ($c->numeroOperacion) · Op. {{ $c->numeroOperacion }}@endif</dd></div>
                    @if ($c->pagadoPor)
                        <div><dt>Registrado por</dt><dd>{{ $c->pagadoPor }}</dd></div>
                    @endif
                @endif
                @if ($c->observacion)
                    <div class="is-ancho"><dt>Observación</dt><dd>{{ \Illuminate\Support\Str::limit($c->observacion, 160) }}</dd></div>
                @endif
            </dl>
        </section>
    </div>

    <footer class="cp-firmas">
        <div>
            <span></span>
            <p class="cp-firma-titulo">Firma del trabajador</p>
            <p>{{ $c->nombre }}@if ($c->dni) · DNI {{ $c->dni }}@endif</p>
        </div>
        <div>
            <span></span>
            <p class="cp-firma-titulo">Firma autorizada</p>
            <p>{{ $empresa }}</p>
        </div>
    </footer>
</article>
