<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Compra\ProveedorComercial;
use App\Domain\Compra\ProveedorComercialRepository;
use App\Infrastructure\Persistence\Models\ProveedorComercialModel;

class EloquentProveedorComercialRepository implements ProveedorComercialRepository
{
    public function buscarOCrear(string $razonSocial, ?string $ruc): ProveedorComercial
    {
        $razonSocial = trim($razonSocial);

        $model = $ruc !== null
            ? ProveedorComercialModel::where('ruc', $ruc)->first()
            : ProveedorComercialModel::whereNull('ruc')->whereRaw('LOWER(razon_social) = ?', [mb_strtolower($razonSocial)])->first();

        $model ??= ProveedorComercialModel::create(['razon_social' => $razonSocial, 'ruc' => $ruc, 'estado' => 'ACTIVO']);

        return $this->toEntity($model);
    }

    public function listarActivos(): array
    {
        return ProveedorComercialModel::where('estado', 'ACTIVO')
            ->orderBy('razon_social')
            ->get()
            ->map(fn (ProveedorComercialModel $model): ProveedorComercial => $this->toEntity($model))
            ->all();
    }

    private function toEntity(ProveedorComercialModel $model): ProveedorComercial
    {
        return new ProveedorComercial(id: (int) $model->id, razonSocial: $model->razon_social, ruc: $model->ruc);
    }
}
