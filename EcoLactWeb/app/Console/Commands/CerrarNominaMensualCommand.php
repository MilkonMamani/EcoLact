<?php

namespace App\Console\Commands;

use App\Application\Pago\CerrarNominaMensual;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Corre el día 1 de cada mes a las 00:05 (routes/console.php): cierra el mes
 * anterior de cada obligación de personal EN_CURSO (EN_CURSO->PENDIENTE, con
 * fecha_pago_programada = hoy) y abre el mes actual. Idempotente.
 */
class CerrarNominaMensualCommand extends Command
{
    protected $signature = 'ecolact:cerrar-nomina-mensual
        {--fecha= : Fecha (AAAA-MM-DD) a tomar como "hoy"; por defecto la fecha actual}';

    protected $description = 'Cierra la nómina del mes anterior (EN_CURSO->PENDIENTE) y abre el mes actual.';

    public function handle(CerrarNominaMensual $cerrarNomina): int
    {
        try {
            $hoy = $this->option('fecha') ? new DateTimeImmutable((string) $this->option('fecha')) : DateTimeImmutable::createFromInterface(today());
        } catch (Throwable) {
            $this->error('La fecha indicada no es válida. Usa el formato AAAA-MM-DD.');

            return self::FAILURE;
        }

        $resultado = $cerrarNomina->execute($hoy);

        $this->info("Obligaciones cerradas (EN_CURSO -> PENDIENTE): {$resultado->cerradas}");
        $this->line("Faltas reparadas defensivamente: {$resultado->faltasReparadas}");

        return self::SUCCESS;
    }
}
