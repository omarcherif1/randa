<?php

namespace Database\Seeders;

use App\Models\ClassificationKeyword;
use App\Models\ClientCategory;
use App\Support\Text;
use Illuminate\Database\Seeder;

/**
 * Règle 2.7 point 3 — liste de démarrage des mots-clés de reconnaissance du
 * type de client. Modifiable ensuite depuis Paramètres, SANS toucher au code.
 *
 * Les mots-clés sont stockés normalisés (majuscules, sans accents).
 */
class ClassificationKeywordSeeder extends Seeder
{
    public function run(): void
    {
        $byCategory = [
            '107' => [ // Grande Surface
                ['MONOPRIX', 90], ['CARREFOUR', 90], ['GEANT', 90], ['MG', 60],
                ['MAGASIN GENERAL', 90], ['AZIZA', 80], ['SUPERMARCHE', 85],
                ['HYPERMARCHE', 85], ['GRANDE SURFACE', 85], ['PROMOGRO', 80],
                ['ENVIE', 50], ['CHAMPION', 80],
            ],
            '108' => [ // Boulanger
                ['BOULANGERIE', 90], ['BOULANGER', 85], ['FOUR ', 40], ['KHOBZ', 60],
            ],
            '117' => [ // Patisserie
                ['PATISSERIE', 90], ['PATISSIER', 85], ['GATEAU', 60],
            ],
            '118' => [ // Restaurant
                ['RESTAURANT', 90], ['RESTO', 70], ['SNACK', 70], ['FAST FOOD', 75],
            ],
            '111' => [ // Hotel
                ['HOTEL', 90], ['RESIDENCE', 50], ['RESORT', 80], ['SPA', 40],
            ],
            '105' => [ // Grossiste
                ['GROS', 55], ['GROSSISTE', 90], ['DISTRIBUTION', 60], ['COMPTOIR', 55],
            ],
            '106' => [ // Industries alimentaires
                ['INDUSTRIE', 70], ['CONSERVERIE', 75], ['MINOTERIE', 70],
                ['SEMOULERIE', 70], ['AGRO', 55],
            ],
            '103' => [ // Usine Aliment Betail
                ['ALIMENT BETAIL', 90], ['UAB', 85], ['PROVENDERIE', 80],
            ],
            '112' => [ // Secteur Publique
                ['MINISTERE', 90], ['OFFICE ', 60], ['MUNICIPALITE', 85],
                ['ARMEE', 80], ['HOPITAL', 80], ['LYCEE', 70], ['UNIVERSITE', 70],
            ],
            '110' => [ // Ste du Groupe  (=> lignes exclues des indicateurs, règle 2.1)
                ['RANDA', 95], ['SMT', 95], ['TUNISIE FARINE', 95],
                ['STE DU GROUPE', 95], ['GROUPE RANDA', 95],
            ],
            '113' => [ // Association
                ['ASSOCIATION', 90], ['CROISSANT ROUGE', 85], ['FONDATION', 80],
            ],
        ];

        $categories = ClientCategory::pluck('id', 'code');

        foreach ($byCategory as $code => $keywords) {
            $categoryId = $categories[$code] ?? null;
            if ($categoryId === null) {
                continue;
            }

            foreach ($keywords as [$keyword, $priorite]) {
                ClassificationKeyword::updateOrCreate(
                    ['client_category_id' => $categoryId, 'keyword' => Text::normalize($keyword)],
                    ['priorite' => $priorite, 'actif' => true],
                );
            }
        }
    }
}
