{{--
    Hoja A4 vertical "Comprobante de pago" de un trabajador: documento formal en UNA sola
    hoja (cabecera breve, título, aviso de vista previa, resumen, trabajador, tabla de
    conceptos, totales y firmas al pie). Estilos en resources/css/components/comprobante-pago.css.
    Solo pinta ComprobantePagoTrabajador (snapshot de la obligación): no calcula dinero.

    La tabla explica el acumulado con la regla real de nómina:
    - Mes completo: sueldo mensual − faltas × valor por día.
    - Mes parcial (ingresó, salió o estuvo inactivo): asistencias × valor por día.

    @var \App\Application\Pago\ComprobantePagoTrabajador $c
--}}
@php
    $soles = fn (float $valor): string => 'S/ '.number_format($valor, 2);
    $empresa = config('empresa.nombre') ?: 'EcoLact';
    $identificador = 'Comprobante '.$c->numero().' - '.$c->nombre;
@endphp
<article class="cp-hoja" data-comprobante="{{ $c->obligacionId }}" aria-label="{{ $identificador }}">
    <div class="cp-cuerpo">
        <div class="cp-encabezado">
            <span>{{ $c->fechaHoraEmision }}</span>
            <span>{{ $identificador }}</span>
        </div>

        <header class="cp-titulo">
            <h1>Comprobante de pago</h1>
            <p class="cp-numero">N.° {{ $c->numero() }}</p>
        </header>

        @unless ($c->estaPagado())
            <p class="cp-aviso" data-comprobante-previa>Vista previa - pago aún no registrado{{ $c->estado === 'EN_CURSO' ? ' (mes en curso: montos parciales)' : '' }}</p>
        @endunless

        <dl class="cp-resumen">
            <div>
                <dt>Fecha de emisión</dt>
                <dd>{{ $c->fechaEmision }}</dd>
            </div>
            <div>
                <dt>Periodo</dt>
                <dd>{{ $c->periodoEtiqueta }}<small>{{ $c->periodoRango }}</small></dd>
            </div>
            @if ($c->estaPagado() && $c->pagadoEl)
                <div>
                    <dt>Fecha de pago</dt>
                    <dd>{{ $c->pagadoEl }}</dd>
                </div>
            @endif
            <div class="is-total">
                <dt>{{ $c->estaPagado() ? 'Total pagado' : 'Total a pagar' }}</dt>
                <dd>{{ $soles($c->total) }}</dd>
            </div>
        </dl>

        <section class="cp-trabajador" aria-label="Datos del trabajador">
            <p class="cp-etiqueta">Trabajador</p>
            <p class="cp-trabajador-nombre">{{ $c->nombre }}</p>
            <dl class="cp-trabajador-datos">
                <div><dt>DNI</dt><dd>{{ $c->dni ?? '—' }}</dd></div>
                <div><dt>Rol</dt><dd>{{ $c->rol }}</dd></div>
                <div><dt>Zona</dt><dd>{{ $c->zona ?? '—' }}</dd></div>
                <div><dt>Fecha de ingreso</dt><dd>{{ $c->fechaIngresoEtiqueta ?? '—' }}</dd></div>
                <div><dt>Periodo</dt><dd>{{ $c->periodoEtiqueta }}</dd></div>
            </dl>
        </section>

        <table class="cp-tabla">
            <thead>
                <tr>
                    <th scope="col">Concepto</th>
                    <th scope="col">Detalle</th>
                    <th scope="col" class="is-num">Cantidad</th>
                    <th scope="col" class="is-num">Monto (S/)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Sueldo mensual</td>
                    <td>Base mensual (Configuración de pagos)</td>
                    <td class="is-num">1</td>
                    <td class="is-num">{{ $soles($c->sueldoBase) }}</td>
                </tr>
                <tr>
                    <td>Valor por día</td>
                    <td>Sueldo mensual ÷ 30</td>
                    <td class="is-num">—</td>
                    <td class="is-num">{{ $soles($c->valorDia) }}</td>
                </tr>
                <tr>
                    <td>Asistencias</td>
                    <td>{{ $c->esMesCompleto ? 'Días trabajados' : 'Días trabajados × valor por día' }}</td>
                    <td class="is-num">{{ $c->diasTrabajados }}</td>
                    <td class="is-num">{{ $c->esMesCompleto ? '—' : $soles($c->acumulado) }}</td>
                </tr>
                <tr>
                    <td>Faltas</td>
                    <td>{{ $c->esMesCompleto ? 'Días no trabajados (se descuentan del sueldo)' : 'Días no trabajados (no se pagan)' }}</td>
                    <td class="is-num">{{ $c->faltas }}</td>
                    <td @class(['is-num', 'is-negativo' => $c->esMesCompleto && $c->descuentoPorFaltas > 0])>{{ $c->esMesCompleto ? ($c->descuentoPorFaltas > 0 ? '− '.$soles($c->descuentoPorFaltas) : $soles(0)) : '—' }}</td>
                </tr>
                <tr class="is-acumulado">
                    <td>Acumulado del periodo</td>
                    <td>{{ $c->esMesCompleto ? 'Sueldo mensual − faltas' : 'Proporcional a días trabajados' }}</td>
                    <td class="is-num">—</td>
                    <td class="is-num">{{ $soles($c->acumulado) }}</td>
                </tr>
                <tr>
                    <td>Bonificaciones</td>
                    <td>Ajustes positivos</td>
                    <td class="is-num">—</td>
                    <td class="is-num">{{ $soles($c->bonificacion) }}</td>
                </tr>
                <tr>
                    <td>Descuentos</td>
                    <td>Ajustes negativos</td>
                    <td class="is-num">—</td>
                    <td @class(['is-num', 'is-negativo' => $c->descuento > 0])>{{ $c->descuento > 0 ? '− '.$soles($c->descuento) : $soles(0) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="cp-cierre">
            <div class="cp-notas">
                @if ($c->estaPagado())
                    <p><span>Método de pago</span>{{ $c->metodoPago }}@if ($c->numeroOperacion) · Op. {{ $c->numeroOperacion }}@endif</p>
                    @if ($c->pagadoPor)
                        <p><span>Registrado por</span>{{ $c->pagadoPor }}</p>
                    @endif
                @endif
                @if ($c->observacion)
                    <p><span>Observación</span>{{ \Illuminate\Support\Str::limit($c->observacion, 160) }}</p>
                @endif
            </div>

            <dl class="cp-totales">
                <div><dt>Subtotal</dt><dd>{{ $soles($c->acumulado) }}</dd></div>
                <div><dt>Bonificaciones</dt><dd>{{ $soles($c->bonificacion) }}</dd></div>
                <div><dt>Descuentos</dt><dd>{{ $c->descuento > 0 ? '− '.$soles($c->descuento) : $soles(0) }}</dd></div>
                <div class="is-total"><dt>Total</dt><dd data-comprobante-total>{{ $soles($c->total) }}</dd></div>
            </dl>
        </div>
    </div>

    <footer class="cp-firmas">
        <div><span></span><p>Recibí conforme - {{ $c->nombre }}</p></div>
        <div><span></span><p>Entregado por - {{ $empresa }}</p></div>
    </footer>
</article>
