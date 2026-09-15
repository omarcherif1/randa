<?php

namespace Database\Seeders;

use App\Models\Societe;
use Illuminate\Database\Seeder;

/**
 * Sociétés du groupe. Le `code` correspond au segment présent dans le numéro
 * de commande (CM<code>... / EXP<code>...). Une couleur unique par société,
 * réutilisée sur TOUS les graphiques (sections 2.3 / 8).
 */
class SocieteSeeder extends Seeder
{
    public function run(): void
    {
        $societes = [
            ['code' => 'RAN', 'nom' => 'Randa', 'couleur' => '#C8102E'],           // rouge Randa
            ['code' => 'SMT', 'nom' => 'SMT', 'couleur' => '#1D4ED8'],             // bleu
            ['code' => 'TF', 'nom' => 'Tunisie Farine', 'couleur' => '#047857'],   // vert
        ];

        foreach ($societes as $s) {
            Societe::updateOrCreate(['code' => $s['code']], $s);
        }
    }
}
