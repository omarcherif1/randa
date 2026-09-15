<?php

namespace Database\Factories;

use App\Models\Commande;
use App\Models\Societe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commande>
 */
class CommandeFactory extends Factory
{
    protected $model = Commande::class;

    public function definition(): array
    {
        return [
            'numero' => 'CM'.fake()->unique()->numerify('##########'),
            'scope' => 'local',
            'societe_id' => Societe::query()->inRandomOrder()->value('id')
                ?? Societe::factory(),
            'date_commande' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'etat' => 'cloturee',
            'is_excluded' => false,
            'has_outlier' => false,
        ];
    }
}
