<?php

namespace Database\Seeders;

use App\Models\Famille;
use Illuminate\Database\Seeder;

/**
 * Règle 2.6 — les 32 familles officielles fournies par l'entreprise
 * (source : « Table famille.csv »).
 */
class FamilleSeeder extends Seeder
{
    public function run(): void
    {
        $familles = [
            ['100', 'COUSCOUS', 'COUSCOUS'],
            ['101', 'COUSCOUS AU BLE COMPLET', 'COUS BLE C'],
            ['102', 'FARINES', 'FARINES'],
            ['103', 'FARINE SPECIALE', 'FARINE SPE'],
            ['104', 'MIX FARINES', 'MIX FARINES'],
            ['105', 'BORGHOL', 'BORGHOL'],
            ['106', 'PATES NORMALES', 'PAT NORMALES'],
            ['107', 'PATES SPECIALES', 'PAT SPECIALE'],
            ['108', 'PRODUIT PROFESSIONNEL', 'PR PROF'],
            ['109', 'PRODUITS A BASE ORGE', 'PR BASE ORGE'],
            ['110', 'PRODUITS DIETETIQUES', 'PR DIETET'],
            ['111', 'PRODUITS SUCRES', 'PR SUCRES'],
            ['112', 'PRODUITS ORGE', 'PR ORGE'],
            ['113', 'SEMOULE', 'SEMOULE'],
            ['114', 'DECHETS', 'DECHETS'],
            ['115', 'RISTOURNES', 'RISTOURNES'],
            ['116', 'PATES IMPORTES', 'PAT IMPORTES'],
            ['117', 'LEGUMINEUSES', 'LEGUMINEUSES'],
            ['118', 'ORGE', 'ORGE'],
            ['119', 'SON LOCAL', 'SON LOCAL'],
            ['120', 'SON EXPORT', 'SON EXPORT'],
            ['121', 'PATE ARTISANALE', 'ARTISANA'],
            ['122', 'HORS-POLE', 'HORS-POLE'],
            ['123', 'DIVERS', 'DIVERS'],
            ['124', 'PRODUITS SURGELES', 'PRODUITS SUR'],
            ['125', 'PRODUITS CUITS', 'PROD CUITS'],
            ['126', 'PRODUITS PRECUITS', 'PROD PRECUIT'],
            ['127', 'TRANSPORT', 'TRANSPORT'],
            ['128', 'DROMADAIRE', 'Dromadaire'],
            ['129', 'EMBALLAGE', 'EMBALLAGE'],
            ['130', 'PATES CEREALES', 'PATES CEREAL'],
            ['131', 'RIZ', 'RIZ'],
        ];

        foreach ($familles as [$code, $intitule, $court]) {
            Famille::updateOrCreate(
                ['code' => $code],
                ['intitule' => $intitule, 'intitule_court' => $court],
            );
        }
    }
}
