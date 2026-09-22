<?php

namespace App\Infrastructure\Persistence;

use App\Application\Shared\TransactionManager;
use Closure;
use Illuminate\Support\Facades\DB;

class LaravelTransactionManager implements TransactionManager
{
    public function run(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
