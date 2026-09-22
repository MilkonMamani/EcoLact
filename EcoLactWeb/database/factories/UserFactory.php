<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Models\RoleModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => fn () => RoleModel::firstOrCreate(
                ['nombre' => 'PROVEEDOR'],
                ['descripcion' => 'Rol generado por la factory de pruebas'],
            )->id,
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'estado' => 'ACTIVO',
            'debe_cambiar_password' => false,
            'ultimo_acceso' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user's account is inactive.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'estado' => 'INACTIVO',
        ]);
    }

    /**
     * Indicate that the user must change their password on next login.
     */
    public function debeCambiarPassword(): static
    {
        return $this->state(fn (array $attributes): array => [
            'debe_cambiar_password' => true,
        ]);
    }
}
