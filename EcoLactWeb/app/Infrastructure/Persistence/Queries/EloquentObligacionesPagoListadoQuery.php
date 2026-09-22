<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Pago\Contracts\ObligacionesPagoListadoQuery;
use App\Application\Pago\ObligacionesPagoFiltro;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\MetodoPago;
use App\Domain\Shared\Enums\PeriodicidadPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PagoModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final class EloquentObligacionesPagoListadoQuery implements ObligacionesPagoListadoQuery
{
    public function obtener(ObligacionesPagoFiltro $filtro, DateTimeImmutable $hoy): array
    {
        $inicioMes = CarbonImmutable::instance($hoy)->startOfMonth();
        $pagosDelMes = PagoModel::whereBetween('fecha_pago', [$inicioMes->toDateString(), $inicioMes->endOfMonth()->toDateString()]);

        $kpis = [
            'pendientes' => ObligacionPagoModel::where('estado', EstadoObligacionPago::PENDIENTE->value)->count(),
            'pagadosMes' => (clone $pagosDelMes)->count(),
            'totalPendiente' => round((float) ObligacionPagoModel::where('estado', EstadoObligacionPago::PENDIENTE->value)->sum('monto_calculado'), 2),
            'totalPagado' => round((float) (clone $pagosDelMes)->sum('monto'), 2),
        ];

        $filas = $this->consulta($filtro)
            ->paginate($filtro->porPagina)
            ->withQueryString()
            ->through(fn (ObligacionPagoModel $obligacion): array => $this->fila($obligacion));

        return ['kpis' => $kpis, 'filas' => $filas];
    }

    private function consulta(ObligacionesPagoFiltro $filtro): Builder
    {
        return ObligacionPagoModel::query()
            ->with(['rol', 'configuracion', 'pago.registradoPor', 'detalles.entrega'])
            ->when($filtro->busqueda !== null, fn (Builder $query) => $query->where('beneficiario_nombre', 'like', '%'.$filtro->busqueda.'%'))
            ->when($filtro->tipo === 'PROVEEDOR', fn (Builder $query) => $query->where('tipo_beneficiario', TipoBeneficiarioPago::PROVEEDOR->value))
            ->when($filtro->tipo !== null && $filtro->tipo !== 'PROVEEDOR', fn (Builder $query) => $query->whereHas('rol', fn (Builder $rol) => $rol->where('nombre', $filtro->tipo)))
            ->when($filtro->periodo !== null, function (Builder $query) use ($filtro): void {
                $mes = CarbonImmutable::createFromFormat('Y-m-d', $filtro->periodo.'-01');
                $query->whereBetween('periodo_inicio', [$mes->toDateString(), $mes->endOfMonth()->toDateString()]);
            })
            ->when($filtro->estado !== null, fn (Builder $query) => $query->where('estado', $filtro->estado->value))
            ->orderByRaw("CASE estado WHEN 'PENDIENTE' THEN 0 ELSE 1 END")
            ->orderByDesc('periodo_inicio')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(ObligacionPagoModel $obligacion): array
    {
        $esProveedor = $obligacion->tipo_beneficiario === TipoBeneficiarioPago::PROVEEDOR->value;
        $periodicidad = PeriodicidadPago::from($obligacion->periodicidad);
        $inicio = DateTimeImmutable::createFromInterface($obligacion->periodo_inicio);
        $fin = DateTimeImmutable::createFromInterface($obligacion->periodo_fin);
        $base = $obligacion->base_calculo ?? [];
        $tipo = $esProveedor ? 'Proveedor' : ($obligacion->rol?->nombre ?? 'PERSONAL');
        $pago = $obligacion->pago;

        return [
            'id' => $obligacion->id,
            'beneficiario' => $obligacion->beneficiario_nombre,
            'tipo' => $tipo,
            'esProveedor' => $esProveedor,
            'base' => $esProveedor ? $this->baseProveedor($base) : $obligacion->concepto,
            'regla' => $this->regla($obligacion, $esProveedor),
            'periodo' => PeriodoPago::etiquetaDe($periodicidad, $inicio),
            'rango' => $inicio->format('d/m/Y').' – '.$fin->format('d/m/Y'),
            'periodoInicio' => $obligacion->periodo_inicio->format('Y-m-d'),
            'monto' => (float) $obligacion->monto_calculado,
            'estado' => $obligacion->estado,
            'estadoEtiqueta' => EstadoObligacionPago::from($obligacion->estado)->etiqueta(),
            'detalle' => $this->detalleCalculo($obligacion, $esProveedor, $base) + $this->detalleEntregas($obligacion),
            'liquidacionId' => $obligacion->liquidacion_id,
            'pago' => $pago === null ? null : [
                'fecha' => $pago->fecha_pago->format('d/m/Y'),
                'metodo' => MetodoPago::from($pago->metodo_pago)->etiqueta(),
                'operacion' => $pago->numero_operacion,
                'observacion' => $pago->observacion,
                'registradoPor' => $pago->registradoPor?->username ?? '—',
                'comprobante' => $pago->comprobante_path !== null ? ($pago->comprobante_nombre ?? 'Comprobante') : null,
            ],
        ];
    }

    /**
     * Litros × precio aplicado. En liquidaciones anteriores sin precio guardado
     * (0) se deduce el precio del subtotal, para no mostrar S/ 0.00.
     *
     * @param  array<string, mixed>  $base
     */
    private function baseProveedor(array $base): string
    {
        $litros = (float) ($base['litros_pagables'] ?? 0);

        if ($litros <= 0.0) {
            return 'Leche entregada';
        }

        $aplicado = (float) ($base['precio_litro_aplicado'] ?? 0);

        if ($aplicado <= 0.0 && isset($base['subtotal'])) {
            $aplicado = (float) $base['subtotal'] / $litros;
        }

        $precioBase = (float) ($base['precio_base_litro'] ?? 0);
        $precioBase = $precioBase > 0.0 ? $precioBase : $aplicado;
        $ajuste = abs($aplicado - $precioBase) < 0.00005 ? '' : ($aplicado > $precioBase ? ' + ajuste' : ' − ajuste');

        return number_format($litros, 2).' L × S/ '.number_format($precioBase, 2).$ajuste;
    }

    /**
     * Una línea por entrega que compone el pago (solo pagos calculados por entregas).
     *
     * @return array<string, string> etiqueta => valor
     */
    private function detalleEntregas(ObligacionPagoModel $obligacion): array
    {
        $lineas = [];

        foreach ($obligacion->detalles->sortBy('entrega_id') as $detalle) {
            $fecha = $detalle->entrega?->fecha_hora?->format('d/m/Y');
            $resultado = $detalle->resultado_calidad !== null ? ' · '.ucfirst(strtolower($detalle->resultado_calidad)) : ' · Sin análisis';
            $ajuste = (float) $detalle->ajuste_calidad !== 0.0 ? ' ('.((float) $detalle->ajuste_calidad > 0 ? '+' : '−').'S/ '.number_format(abs((float) $detalle->ajuste_calidad), 2).')' : '';

            $lineas['Entrega #'.$detalle->entrega_id.($fecha ? ' · '.$fecha : '')] = number_format((float) $detalle->litros_pagables, 2).' L × S/ '.number_format((float) $detalle->precio_final, 2).$ajuste.' = S/ '.number_format((float) $detalle->subtotal, 2).$resultado;
        }

        return $lineas;
    }

    private function regla(ObligacionPagoModel $obligacion, bool $esProveedor): string
    {
        $configuracion = $obligacion->configuracion;

        if ($configuracion === null) {
            return $esProveedor ? 'Por litros entregados (liquidación anterior)' : $obligacion->concepto;
        }

        if ($esProveedor) {
            return 'Por litros entregados · precio de leche vigente'.($configuracion->aplica_calidad ? ' · con ajustes de calidad' : '');
        }

        return $obligacion->concepto.' · S/ '.number_format((float) $configuracion->monto_base, 2);
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, string> etiqueta => valor
     */
    private function detalleCalculo(ObligacionPagoModel $obligacion, bool $esProveedor, array $base): array
    {
        $soles = fn (mixed $valor): string => 'S/ '.number_format((float) $valor, 2);

        if (! $esProveedor) {
            return array_filter([
                'Rol' => $base['rol'] ?? $obligacion->rol?->nombre,
                'Sueldo base' => isset($base['sueldo_base']) ? $soles($base['sueldo_base']) : null,
                'Configuración vigente desde' => isset($base['vigente_desde']) ? CarbonImmutable::parse($base['vigente_desde'])->format('d/m/Y') : null,
            ], fn ($valor) => $valor !== null);
        }

        return array_filter([
            'Litros entregados' => isset($base['litros_entregados']) ? number_format((float) $base['litros_entregados'], 2).' L' : null,
            'Litros válidos a pagar' => isset($base['litros_pagables']) ? number_format((float) $base['litros_pagables'], 2).' L' : null,
            'Precio de leche' => isset($base['precio_base_litro']) ? $soles($base['precio_base_litro']).' / L' : null,
            'Precio aplicado' => isset($base['precio_litro_aplicado']) ? $soles($base['precio_litro_aplicado']).' / L' : null,
            'Ajustes de calidad' => isset($base['aplica_calidad']) ? ($base['aplica_calidad'] ? (($base['tuvo_incidencia'] ?? false) ? 'Aplicados (hubo incidencias)' : 'Sin incidencias') : 'No aplican') : null,
            'Subtotal' => isset($base['subtotal']) ? $soles($base['subtotal']) : null,
            'Descuentos y ajustes' => isset($base['ajustes']) ? $soles($base['ajustes']) : null,
        ], fn ($valor) => $valor !== null);
    }
}
