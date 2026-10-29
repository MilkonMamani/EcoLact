<?php

namespace App\Console\Commands;

use App\Application\Caja\RegistrarMovimientoCaja;
use App\Application\Shared\TransactionManager;
use App\Domain\Caja\MovimientoCaja;
use App\Infrastructure\Persistence\Models\MovimientoCajaModel;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Registra el SALDO INICIAL de la caja (el dinero con que arranca) como una
 * ENTRADA por RegistrarMovimientoCaja, la única puerta de la caja. Es único:
 * si ya existe no se crea otro ni se cambia su monto. Caja sigue siendo de
 * solo lectura en la interfaz; esto es solo para la apertura.
 */
class RegistrarSaldoInicialCajaCommand extends Command
{
    protected $signature = 'caja:saldo-inicial
        {monto : Monto en soles con que arranca la caja}
        {--usuario=admin : Usuario ADMINISTRADOR que registra la apertura}
        {--observacion= : Nota opcional}';

    protected $description = 'Registra el saldo inicial de la caja (una sola vez).';

    public function handle(RegistrarMovimientoCaja $caja, TransactionManager $transacciones): int
    {
        $monto = round((float) $this->argument('monto'), 2);

        if ($monto <= 0.0) {
            $this->error('El saldo inicial debe ser mayor a cero.');

            return self::FAILURE;
        }

        $usuario = User::query()->with('role')->where('username', $this->option('usuario'))->first();

        if ($usuario === null || ! $usuario->tieneRol(User::ROL_ADMINISTRADOR)) {
            $this->error('El usuario indicado no existe o no es ADMINISTRADOR.');

            return self::FAILURE;
        }

        $existente = MovimientoCajaModel::where('referencia_tipo', MovimientoCaja::REFERENCIA_SALDO_INICIAL)->first();

        if ($existente !== null) {
            $this->warn('La caja ya tiene saldo inicial de S/ '.number_format((float) $existente->monto, 2).'. No se registró otro.');

            return self::SUCCESS;
        }

        $movimiento = $transacciones->run(fn (): MovimientoCaja => $caja->porSaldoInicial($monto, $usuario->id, $this->option('observacion') ?: null));

        $this->info('Saldo inicial registrado: S/ '.number_format($movimiento->monto(), 2).'.');

        return self::SUCCESS;
    }
}
