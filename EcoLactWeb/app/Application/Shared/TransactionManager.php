<?php

namespace App\Application\Shared;

use Closure;

/**
 * Abstracción de frontera transaccional para los Use Cases de Application.
 *
 * No pertenece al Domain (que debe permanecer libre de infraestructura); su
 * implementación concreta vive en Infrastructure y envuelve el mecanismo de
 * transacciones real (por ejemplo, DB::transaction() de Laravel).
 */
interface TransactionManager
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Closure $callback): mixed;
}
