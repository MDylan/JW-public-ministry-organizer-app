<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'role' => 'registered',
            'language' => 'hu',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function unverified()
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }

    public function role(string $role)
    {
        return $this->state(function () use ($role) {
            return [
                'role' => $role,
            ];
        });
    }

    public function profileIncomplete()
    {
        return $this->state(function () {
            return [
                'name' => null,
            ];
        });
    }

    public function asAdmin(): static
    {
        return $this->role('mainAdmin');
    }

    public function asGroupCreator(): static
    {
        return $this->role('groupCreator');
    }

    public function asTranslator(): static
    {
        return $this->role('translator');
    }

    public function asActivated(): static
    {
        return $this->role('activated');
    }

    public function withFullProfile(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'name'          => $this->faker->name(),
                'phone_number'  => '36' . $this->faker->numerify('#########'),
                'accepted_gdpr' => 1,
                'firstDay'      => 1,
                'language'      => 'hu',
            ];
        });
    }
}
