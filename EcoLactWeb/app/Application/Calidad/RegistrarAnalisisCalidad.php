<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Calidad\AnalisisCalidadDetalle;
use App\Domain\Calidad\AnalisisCalidadDetalleRepository;
use App\Domain\Calidad\AnalisisCalidadRepository;
use App\Domain\Calidad\MuestreoCalidadRepository;
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
        private readonly MuestreoCalidadRepository $muestreos,
    ) {}

    /**
     * @param  array<string, float>  $valores  Valores ya extraídos, indexados por código de parámetro (ej. 'DENSIDAD', 'GRASA').
     * @param  array<string, string>  $origenes  OCR si el analista aceptó el valor propuesto por el ticket; MANUAL (por defecto) si lo escribió o corrigió.
     * @param  DateTimeImmutable|null  $fechaAnalisis  Hora REAL del análisis en el teléfono (aunque llegue después por sincronización offline). Por defecto, ahora.
     * @param  DateTimeImmutable|null  $fechaCapturaLocal  Hora en que se tomó la foto del ticket, si la hay.
     * @param  bool  $exigirMuestra  Portal y móvil del analista: Calidad trabaja por MUESTREO y solo analiza
     *                               entregas seleccionadas como muestra. Un análisis ya iniciado antes del
     *                               muestreo (histórico) puede continuarse. La corrección administrativa no lo exige.
     */
    public function execute(
        int $entregaId,
        array $valores,
        ?string $imagenPath = null,
        ?string $observacion = null,
        array $origenes = [],
        ?DateTimeImmutable $fechaAnalisis = null,
        ?DateTimeImmutable $fechaCapturaLocal = null,
        bool $exigirMuestra = false,
    ): AnalisisCalidad {
        if ($this->entregas->buscarPorId($entregaId) === null) {
            throw new DomainException('La entrega indicada no existe.');
        }

        $analisis = $this->analisisCalidad->buscarPorEntregaId($entregaId);
        $muestreo = $this->muestreos->buscarPorEntregaId($entregaId);

        if ($exigirMuestra && $analisis === null && ($muestreo === null || ! $muestreo->estaPendiente())) {
            throw new DomainException('Esta entrega no fue seleccionada como muestra. Selecciónala en Muestreo antes de analizarla.');
        }

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
                fechaAnalisis: $this->horaDelEvento($fechaAnalisis),
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

        // Si la entrega llegó por muestreo, la muestra pasa a EN_ANALISIS.
        if ($muestreo !== null && $muestreo->estaPendiente()) {
            $muestreo->iniciarAnalisis($fechaCapturaLocal !== null ? $this->horaDelEvento($fechaCapturaLocal) : null);
            $this->muestreos->actualizar($muestreo);
        }

        return $analisis;
    }

    /** Nunca una hora futura: un reloj adelantado en el teléfono no mueve el análisis. */
    private function horaDelEvento(?DateTimeImmutable $fecha): DateTimeImmutable
    {
        $ahora = new DateTimeImmutable;

        return $fecha !== null && $fecha < $ahora ? $fecha : $ahora;
    }
}
