<?php

namespace App\Application\Pago;

/** Resumen de una corrida de GenerarPagosPendientes. */
final class ResultadoGeneracionPagos
{
    /**
     * @param  list<array{beneficiario: string, periodo: string, motivo: string}>  $omitidas
     */
    public function __construct(
        public int $creadas = 0,
        public int $existentes = 0,
        public array $omitidas = [],
    ) {}

    public function fusionar(self $otro): self
    {
        return new self(
            $this->creadas + $otro->creadas,
            $this->existentes + $otro->existentes,
            [...$this->omitidas, ...$otro->omitidas],
        );
    }

    public function omitir(string $beneficiario, string $periodo, string $motivo): void
    {
        $this->omitidas[] = ['beneficiario' => $beneficiario, 'periodo' => $periodo, 'motivo' => $motivo];
    }
}
