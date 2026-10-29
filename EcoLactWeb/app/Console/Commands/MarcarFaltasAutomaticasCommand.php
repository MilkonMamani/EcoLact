<?php

namespace App\Console\Commands;

use App\Application\Pago\MarcarFaltasAutomaticas;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Corre todos los días a las 23:59 hora del negocio (routes/console.php),
 * el cierre oficial de asistencia (VentanaAsistencia): crea la FALTA
 * automática de quien debía asistir y no registró nada en los días ya
 * cerrados del mes. Idempotente: quien ya tiene jornada no se toca.
 */
class MarcarFaltasAutomaticasCommand extends Command
{
    protected $signature = 'ecolact:marcar-faltas
        {--fecha= : Último día (AAAA-MM-DD) a cerrar; por defecto el último día ya cerrado (hoy desde las 23:59)}';

    protected $description = 'Marca FALTA automática a quien debía asistir y no registró asistencia antes de las 23:59.';

    public function handle(MarcarFaltasAutomaticas $marcarFaltas): int
    {
        try {
            $hasta = $this->option('fecha') ? new DateTimeImmutable((string) $this->option('fecha')) : null;
        } catch (Throwable) {
            $this->error('La fecha indicada no es válida. Usa el formato AAAA-MM-DD.');

            return self::FAILURE;
        }

        $resultado = $marcarFaltas->execute($hasta);

        $this->info("Faltas automáticas creadas: {$resultado->creadas}");

        return self::SUCCESS;
    }
}
