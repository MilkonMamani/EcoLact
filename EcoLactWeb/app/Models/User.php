<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use App\Infrastructure\Persistence\Models\EncargadoPlantaModel;
use App\Infrastructure\Persistence\Models\PersonalVentasModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\RoleModel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['role_id', 'username', 'password', 'estado', 'debe_cambiar_password', 'ultimo_acceso'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Nombres oficiales de rol (tabla roles.nombre). Únicos roles del
     * sistema: no comparar por role_id numérico, siempre por este nombre.
     */
    public const ROL_ADMINISTRADOR = 'ADMINISTRADOR';

    public const ROL_ACOPIADOR = 'ACOPIADOR';

    public const ROL_PROVEEDOR = 'PROVEEDOR';

    public const ROL_ENCARGADO_PLANTA = 'ENCARGADO_PLANTA';

    public const ROL_VENTAS = 'VENTAS';

    public const ROL_CALIDAD = 'CALIDAD';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debe_cambiar_password' => 'boolean',
            'ultimo_acceso' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(RoleModel::class, 'role_id');
    }

    public function proveedor(): HasOne
    {
        return $this->hasOne(ProveedorModel::class, 'user_id');
    }

    public function acopiador(): HasOne
    {
        return $this->hasOne(AcopiadorModel::class, 'user_id');
    }

    public function analistaCalidad(): HasOne
    {
        return $this->hasOne(AnalistaCalidadModel::class, 'user_id');
    }

    public function encargadoPlanta(): HasOne
    {
        return $this->hasOne(EncargadoPlantaModel::class, 'user_id');
    }

    public function personalVentas(): HasOne
    {
        return $this->hasOne(PersonalVentasModel::class, 'user_id');
    }

    /**
     * Compara el rol del usuario por nombre (nunca por role_id numérico).
     */
    public function tieneRol(string ...$nombresRol): bool
    {
        return in_array($this->role?->nombre, $nombresRol, true);
    }

    public function esAdministrador(): bool
    {
        return $this->tieneRol(self::ROL_ADMINISTRADOR);
    }

    public function esAcopiador(): bool
    {
        return $this->tieneRol(self::ROL_ACOPIADOR);
    }

    public function esProveedor(): bool
    {
        return $this->tieneRol(self::ROL_PROVEEDOR);
    }

    public function esEncargadoPlanta(): bool
    {
        return $this->tieneRol(self::ROL_ENCARGADO_PLANTA);
    }

    public function esVentas(): bool
    {
        return $this->tieneRol(self::ROL_VENTAS);
    }

    public function esCalidad(): bool
    {
        return $this->tieneRol(self::ROL_CALIDAD);
    }

    /**
     * Un usuario está operativo si su cuenta está ACTIVA y, cuando el rol
     * tiene un registro operativo vinculado (Acopiador o Proveedor), ese
     * registro también está ACTIVO. DesactivarAcopiador (Application) solo
     * cambia acopiadores.estado, no users.estado, así que ambos estados
     * deben revisarse: no son la misma bandera.
     */
    public function estaOperativo(): bool
    {
        if ($this->estado !== EstadoUsuario::ACTIVO->value) {
            return false;
        }

        if ($this->esAcopiador()) {
            return $this->acopiador !== null
                && $this->acopiador->estado === EstadoUsuario::ACTIVO->value;
        }

        if ($this->esProveedor()) {
            return $this->proveedor !== null
                && $this->proveedor->estado === EstadoUsuario::ACTIVO->value;
        }

        return true;
    }
}
