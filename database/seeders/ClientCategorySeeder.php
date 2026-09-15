<?php

namespace Database\Seeders;

use App\Models\ClientCategory;
use Illuminate\Database\Seeder;

/**
 * Règle 2.7 — les 21 catégories officielles de type de client
 * (source : « Table client.csv »).
 *
 * - code 109 « Exportation » : is_export = true (déduction : client livré à l'étranger).
 * - code 199 « Divers »      : is_fallback = true — sert de catégorie "Indéfini",
 *   explicitement affichée et filtrable (jamais masquée).
 */
class ClientCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['100', 'Revendeurs', 'Revendeurs'],
            ['101', 'Eleveurs', 'Eleveurs'],
            ['102', 'Centre de collecte', 'Centre Coll'],
            ['103', 'Usine Aliment Betail', 'UAB'],
            ['104', 'Colporteurs', 'Colporteurs'],
            ['105', 'Grossiste', 'Grossiste'],
            ['106', 'Industries alimentaires', 'Ind.Aliment'],
            ['107', 'Grande Surface', 'GMS'],
            ['108', 'Boulanger', 'Boulanger'],
            ['109', 'Exportation', 'Exportation'],
            ['110', 'Ste du Groupe', 'Ste Grp'],
            ['111', 'Hotel', 'Hotel'],
            ['112', 'Secteur Publique', 'Publique'],
            ['113', 'Association', 'Association'],
            ['114', 'Contentieux', 'Contentieux'],
            ['115', 'Douteux', 'Douteux'],
            ['116', 'Vente Directe', 'V Directe'],
            ['117', 'Patisserie', 'Patisserie'],
            ['118', 'Restaurant', 'Restaurant'],
            ['119', 'Agriculteur', 'Agriculteur'],
            ['199', 'Divers / Indéfini', 'Divers'],
        ];

        foreach ($categories as [$code, $intitule, $court]) {
            ClientCategory::updateOrCreate(
                ['code' => $code],
                [
                    'intitule' => $intitule,
                    'intitule_court' => $court,
                    'is_export' => $code === '109',
                    'is_fallback' => $code === '199',
                ],
            );
        }
    }
}
