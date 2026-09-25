<?php

namespace App\Console\Commands;

use App\Application\Pago\MarcarFaltasAutomaticas;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Corre todos los días a las 12:05 (routes/console.php): crea la FALTA
 * automática de quien no marcó asistencia hoy. Idempotente: quien ya tiene
 * jornada hoy (PRESENTE o FALTA) no se toca.
 */
class MarcarFaltasAutomaticasCommand extends Command
{
    protected $signature = 'ecolact:marcar-faltas
        {--fecha= : Fecha (AAAA-MM-DD) a tomar como "hoy"; por defecto la fecha actual}';

    protected $description = 'Marca FALTA automática a quien no registró asistencia hoy.';

    public function handle(MarcarFaltasAutomaticas $marcarFaltas): int
    {
        try {
            $hoy = $this->option('fecha') ? new DateTimeImmutable((string) $this->option('fecha')) : DateTimeImmutable::createFromInterface(today());
        } catch (Throwable) {
            $this->error('La fecha indicada no es válida. Usa el formato AAAA-MM-DD.');

            return self::FAILURE;
        }

        $resultado = $marcarFaltas->execute($hoy);

        $this->info("Faltas automáticas creadas: {$resultado->creadas}");

        return self::SUCCESS;
    }
}
