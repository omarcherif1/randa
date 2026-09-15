<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Paramètres réglables (page Paramètres, section 4.9). Valeurs par défaut =
 * règles métier validées par l'entreprise.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['seuil_retard_jours', 2, 'int', 'Seuil de retard (jours)',
                'Règle 2.4 — une commande locale est en retard si (date de facture − date de commande) dépasse ce '
                .'nombre de jours. Décision entreprise : la date de facture sert de proxy accepté en l\'absence '
                .'d\'une vraie date d\'expédition. Chiffre unique pour toute l\'entreprise.'],

            ['seuil_rupture_pct', 0.5, 'float', 'Seuil de rupture (méthode B, ratio)',
                'Règle 2.5 — rupture si (quantité livrée / quantité commandée) sur commandes ouvertes est inférieur à ce ratio.'],

            ['rupture_couverture_jours_bas', 2, 'int', 'Rupture si couverture < N jours (méthode A)',
                'Règle 2.5 — méthode A (fichier de stock journalier).'],

            ['rupture_couverture_jours_haut', 7, 'int', 'Alerte si couverture <= N jours (méthode A)',
                'Règle 2.5 — méthode A.'],

            ['stock_methode_active', 'B', 'string', 'Méthode de calcul des ruptures active',
                'Règle 2.5 — "A" = fichier de stock journalier réel ; "B" = déduction depuis les commandes ouvertes. Affiché dans l\'interface.'],

            ['outlier_qte_max_kg', 1000000, 'int', 'Plafond de quantité par ligne (kg)',
                'Règle 2.9 — à partir de ce seuil (inclus), la ligne part en "anomalies à vérifier" et ne compte pas '
                .'dans les totaux. Décision entreprise du 2026-09-11 (voir docs/DATA_NOTES.md) : les lignes "Déchet" '
                .'à 1 000 000 kg sont des valeurs aberrantes confirmées, à exclure définitivement.'],

            ['outlier_ratio_commande_livree_max', 1000, 'int', 'Ratio commande/livraison maximal',
                'Règle 2.9 — un écart supérieur signale une probable erreur de saisie (ex. 200 504 005 commandé / 99 livré).'],

            ['outlier_ratio_mediane_max', 25, 'int', 'Écart maximal à la médiane (par article)',
                'Règle 2.9 — contrôle statistique : une quantité dépassant N× la médiane des quantités du même article est signalée.'],

            ['famille_colonne_index', null, 'int', 'Colonne "Famille statistique" retenue',
                'Règle 2.6 — auto-détectée à chaque import ; peut être forcée ici si l\'entreprise identifie la bonne colonne.'],

            ['unite_reference', 'KG', 'string', 'Unité de référence des agrégations',
                'Règle 2.8 — toutes les quantités sont converties dans cette unité (kg) avant toute somme.'],

            ['qx_poids_kg_hypothese', 100, 'int', 'Poids d\'un quintal (kg) — hypothèse de secours',
                'Règle 2.8 — il n\'existe PAS de facteur unique pour QX : le poids est d\'abord extrait de la '
                .'désignation article par article (ex. "Tchich 25KG"), exactement comme SAC/T. Cette valeur ne sert '
                .'de filet de sécurité (hypothèse "non confirmée") que si aucun poids n\'est trouvé dans le texte.'],
        ];

        foreach ($settings as [$key, $value, $type, $libelle, $description]) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => ['v' => $value], 'type' => $type, 'libelle' => $libelle, 'description' => $description],
            );
        }

        Setting::flush();
    }
}
