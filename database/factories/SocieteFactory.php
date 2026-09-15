<?php

namespace Database\Factories;

use App\Models\Societe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Societe>
 */
class SocieteFactory extends Factory
{
    protected $model = Societe::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'nom' => fake()->company(),
            'couleur' => fake()->hexColor(),
            'actif' => true,
        ];
    }
}
