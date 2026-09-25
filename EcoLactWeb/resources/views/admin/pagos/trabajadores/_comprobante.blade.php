{{--
    Hoja "Pago a trabajador" (A4). Reutiliza el mismo sistema visual pp-* que
    "Pago semanal a proveedor" (resources/css/components/pago-semanal.css).
    Recibe: $c: App\Application\Pago\ComprobantePagoTrabajador
--}}
@php
    $soles = fn (float $valor): string => ($valor < 0 ? '- ' : '').'S/ '.number_format(abs($valor), 2);
@endphp
<article class="pp-hoja" data-hoja-id="{{ $c->obligacionId }}" aria-label="Pago a trabajador · {{ $c->nombre }}">
    <header class="pp-cabecera">
        <div class="pp-marca">
            <img src="{{ asset('images/ecolact-mark.svg') }}" alt="">
            <div><strong>EcoLact</strong><small>Del campo a tu mesa</small></div>
        </div>
        <div class="pp-lema">
            <span>Productores de hoy, una mejor mañana</span>
            <i></i>
            <x-ui.icon name="leaf" class="" />
        </div>
    </header>

    <div class="pp-titulo-fila">
        <div class="pp-titulo-caja">
            <span class="pp-icono-caja"><x-ui.icon name="users" class="" /></span>
            <div>
                <h1>Pago a trabajador</h1>
                <p>Comprobante de pago de sueldo mensual</p>
            </div>
        </div>
        <div class="pp-semana">
            <x-ui.icon name="calendar-days" class="" />
            <div>
                <strong>{{ $c->periodoEtiqueta }}</strong>
                <span>{{ $c->periodoRango }}</span>
                @if ($c->estaPagado() && $c->pagadoEl)
                    <em class="pp-pagado-sello not-italic">Pagado el {{ $c->pagadoEl }}@if ($c->pagadoPor) · por {{ $c->pagadoPor }}@endif</em>
                @endif
            </div>
        </div>
    </div>

    <section class="pp-seccion">
        <div class="pp-seccion-cabecera">
            <span class="pp-seccion-icono"><x-ui.icon name="user" class="" /></span>
            <h2>1. Datos del trabajador</h2>
        </div>
        <dl class="pp-datos">
            <div><dt>Nombre</dt><dd>{{ $c->nombre }}</dd></div>
            <div><dt>DNI</dt><dd>{{ $c->dni ?? '—' }}</dd></div>
            <div><dt>Rol</dt><dd>{{ $c->rol }}</dd></div>
            <div><dt>Periodo</dt><dd>{{ $c->periodoEtiqueta }}</dd></div>
            @if (! $c->esMesCompleto)
                <div><dt>Fecha de ingreso</dt><dd>{{ $c->fechaIngresoEtiqueta }}</dd></div>
            @endif
        </dl>
    </section>

    <section class="pp-seccion">
        <div class="pp-seccion-cabecera">
            <span class="pp-seccion-icono"><x-ui.icon name="calendar-days" class="" /></span>
            <h2>2. Resumen de asistencia</h2>
        </div>
        <table class="pp-tabla">
            <thead>
                <tr>
                    <th class="pp-izq" style="width: 60%">Concepto</th>
                    <th style="width: 40%">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="pp-izq">Asistencias</td>
                    <td class="pp-importe">{{ $c->diasTrabajados }}</td>
                </tr>
                <tr>
                    <td class="pp-izq">Faltas</td>
                    <td class="pp-importe">{{ $c->faltas }}</td>
                </tr>
            </tbody>
        </table>
    </section>

    <section class="pp-seccion">
        <div class="pp-seccion-cabecera">
            <span class="pp-seccion-icono"><x-ui.icon name="banknote" class="" /></span>
            <h2>3. Cálculo</h2>
        </div>
        <table class="pp-tabla">
            <thead>
                <tr>
                    <th class="pp-izq" style="width: 60%">Concepto</th>
                    <th style="width: 40%">Importe (S/)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="pp-izq">Sueldo mensual</td>
                    <td class="pp-importe">{{ $soles($c->sueldoBase) }}</td>
                </tr>
                <tr>
                    <td class="pp-izq">Valor por día</td>
                    <td class="pp-importe">{{ $soles($c->valorDia) }}</td>
                </tr>
                @if (! $c->esMesCompleto)
                    <tr>
                        <td class="pp-izq">Pago proporcional</td>
                        <td class="pp-importe">{{ $soles($c->acumulado) }}</td>
                    </tr>
                @else
                    <tr>
                        <td class="pp-izq">Descuento por faltas</td>
                        <td class="pp-importe">{{ $c->descuentoPorFaltas > 0 ? '- '.$soles($c->descuentoPorFaltas) : $soles(0) }}</td>
                    </tr>
                    <tr>
                        <td class="pp-izq">Acumulado</td>
                        <td class="pp-importe">{{ $soles($c->acumulado) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="pp-izq">Bonificaciones</td>
                    <td class="pp-importe">{{ $c->bonificacion > 0 ? '+ '.$soles($c->bonificacion) : $soles(0) }}</td>
                </tr>
                <tr>
                    <td class="pp-izq">Otros descuentos</td>
                    <td class="pp-importe">{{ $c->descuento > 0 ? '- '.$soles($c->descuento) : $soles(0) }}</td>
                </tr>
            </tbody>
        </table>
    </section>

    <section class="pp-total">
        <span class="pp-icono-caja"><x-ui.icon name="banknote" class="" /></span>
        <div>
            <h3>Monto final total a entregar</h3>
            <p>{{ $c->metodoPago ?? 'Pago pendiente' }}</p>
        </div>
        <strong>S/ {{ number_format($c->total, 2) }}</strong>
    </section>

    @if ($c->observacion)
        <p style="margin-top: 10px; font-size: 13px; color: var(--pp-muted);">Observación: {{ $c->observacion }}</p>
    @endif

    <div class="pp-firmas">
        <div class="pp-firma">
            <x-ui.icon name="pen-line" class="" />
            <div class="pp-firma-linea"></div>
            <h4>Recibí conforme</h4>
            <dl>
                <div><dt>Nombre:</dt><dd></dd></div>
                <div><dt>DNI:</dt><dd></dd></div>
                <div><dt>Fecha:</dt><dd class="pp-fecha"><i></i>/<i></i>/ {{ $c->anioFirma }}</dd></div>
            </dl>
        </div>
        <div class="pp-firma">
            <x-ui.icon name="pen-line" class="" />
            <div class="pp-firma-linea"></div>
            <h4>Entregado por (EcoLact)</h4>
            <dl>
                <div><dt>Nombre:</dt><dd></dd></div>
                <div><dt>Cargo:</dt><dd></dd></div>
                <div><dt>Fecha:</dt><dd class="pp-fecha"><i></i>/<i></i>/ {{ $c->anioFirma }}</dd></div>
            </dl>
        </div>
    </div>

    <footer class="pp-pie">
        <div class="pp-pie-frase">
            <x-ui.icon name="leaf" class="" />
            <p>¡Gracias por tu esfuerzo, juntos llevamos nutrición a más familias!</p>
        </div>
        <div class="pp-pie-marca"><strong>EcoLact</strong><small>Del campo a tu mesa</small></div>
    </footer>
</article>
