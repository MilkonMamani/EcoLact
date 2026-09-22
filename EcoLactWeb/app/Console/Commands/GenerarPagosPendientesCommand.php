<?php

namespace App\Console\Commands;

use App\Application\Pago\GenerarPagosPendientes;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Genera los pagos pendientes que corresponden hoy: el sueldo mensual del
 * personal y la leche entregada por los proveedores en semanas ya cerradas.
 * Es idempotente: se puede ejecutar varias veces sin duplicar pagos. El
 * scheduler lo corre a diario (routes/console.php).
 */
class GenerarPagosPendientesCommand extends Command
{
    protected $signature = 'ecolact:generar-pagos
        {--fecha= : Fecha (AAAA-MM-DD) a tomar como "hoy"; por defecto la fecha actual}';

    protected $description = 'Genera (de forma idempotente) los pagos pendientes de proveedores y personal según Configuración de pagos.';

    public function handle(GenerarPagosPendientes $generar): int
    {
        try {
            $hoy = $this->option('fecha') ? new DateTimeImmutable((string) $this->option('fecha')) : DateTimeImmutable::createFromInterface(today());
        } catch (Throwable) {
            $this->error('La fecha indicada no es válida. Usa el formato AAAA-MM-DD.');

            return self::FAILURE;
        }

        $resultado = $generar->execute($hoy);

        $this->info("Pagos pendientes creados: {$resultado->creadas}");
        $this->line("Ya existían (no se duplicaron): {$resultado->existentes}");

        if ($resultado->omitidas !== []) {
            $this->warn('Omitidos: '.count($resultado->omitidas));
            $this->table(['Beneficiario', 'Periodo', 'Motivo'], $resultado->omitidas);
        }

        return self::SUCCESS;
    }
}
