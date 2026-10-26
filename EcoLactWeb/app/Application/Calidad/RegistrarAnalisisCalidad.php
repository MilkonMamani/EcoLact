<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Calidad\AnalisisCalidadDetalle;
use App\Domain\Calidad\AnalisisCalidadDetalleRepository;
use App\Domain\Calidad\AnalisisCalidadRepository;
use App\Domain\Calidad\ParametroCalidadRepository;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Shared\Enums\EstadoAnalisis;
use DateTimeImmutable;
use DomainException;

/**
 * Crea (o revisa, mientras esté en BORRADOR) el análisis de calidad de una
 * entrega a partir de valores ya revisados por el analista (el OCR del
 * ticket solo los propone, ver EscanearTicketAnalizador). El resultado final
 * (ACEPTADA/OBSERVADA/RECHAZADA) solo se calcula y persiste al confirmar el
 * análisis, ver FinalizarAnalisisCalidad.
 */
final class RegistrarAnalisisCalidad
{
    public function __construct(
        private readonly EntregaRepository $entregas,
        private readonly AnalisisCalidadRepository $analisisCalidad,
        private readonly AnalisisCalidadDetalleRepository $detalles,
        private readonly ParametroCalidadRepository $parametros,
    ) {}

    /**
     * @param  array<string, float>  $valores  Valores ya extraídos, indexados por código de parámetro (ej. 'DENSIDAD', 'GRASA').
     * @param  array<string, string>  $origenes  OCR si el analista aceptó el valor propuesto por el ticket; MANUAL (por defecto) si lo escribió o corrigió.
     */
    public function execute(
        int $entregaId,
        array $valores,
        ?string $imagenPath = null,
        ?string $observacion = null,
        array $origenes = [],
    ): AnalisisCalidad {
        if ($this->entregas->buscarPorId($entregaId) === null) {
            throw new DomainException('La entrega indicada no existe.');
        }

        $analisis = $this->analisisCalidad->buscarPorEntregaId($entregaId);

        if ($analisis !== null && $analisis->estaConfirmado()) {
            throw new DomainException('El análisis de esta entrega ya fue confirmado y no puede modificarse.');
        }

        if ($analisis !== null && $imagenPath !== null) {
            // Un ticket nuevo (re-escaneo) reemplaza la foto del borrador.
            $analisis = $this->analisisCalidad->actualizar(new AnalisisCalidad(
                id: $analisis->id(),
                entregaId: $analisis->entregaId(),
                imagenPath: $imagenPath,
                estado: $analisis->estado(),
                resultado: $analisis->resultado(),
                fechaAnalisis: $analisis->fechaAnalisis(),
                confirmadoPorUserId: $analisis->confirmadoPorUserId(),
                observacion: $analisis->observacion(),
            ));
        }

        if ($analisis === null) {
            $analisis = $this->analisisCalidad->guardar(new AnalisisCalidad(
                id: null,
                entregaId: $entregaId,
                imagenPath: $imagenPath,
                estado: EstadoAnalisis::BORRADOR,
                resultado: null,
                fechaAnalisis: new DateTimeImmutable,
                confirmadoPorUserId: null,
                observacion: $observacion,
            ));
        }

        $analisisId = $analisis->id() ?? throw new DomainException('No se pudo registrar el análisis de calidad.');

        foreach ($valores as $codigoParametro => $valor) {
            // Un campo del formulario que el analista dejó en blanco llega
            // aquí como null (no se está midiendo ese parámetro): se omite
            // en vez de guardarlo como 0, que casi siempre violaría
            // cualquier regla real (ej. Densidad 1.028-1.034) y forzaría un
            // resultado que nadie pidió.
            if ($valor === null) {
                continue;
            }

            $parametro = $this->parametros->buscarPorCodigo($codigoParametro);

            if ($parametro === null) {
                throw new DomainException("El parámetro de calidad '{$codigoParametro}' no está configurado.");
            }

            $this->detalles->guardar(new AnalisisCalidadDetalle(
                id: null,
                analisisId: $analisisId,
                parametroId: $parametro->id() ?? throw new DomainException("El parámetro '{$codigoParametro}' no tiene identificador válido."),
                valor: (float) $valor,
                origen: ($origenes[$codigoParametro] ?? null) === 'OCR' ? 'OCR' : 'MANUAL',
            ));
        }

        return $analisis;
    }
}
