<?php

namespace App\Domain\Quality;

use App\Models\Setting;

/**
 * Règle métier 2.9 — détection des valeurs aberrantes.
 *
 * Tourne AUTOMATIQUEMENT à chaque import. Les lignes suspectes ne rentrent
 * JAMAIS dans les totaux affichés à la direction : elles vont dans la liste
 * "anomalies à vérifier" (table `anomalies`), qu'un admin valide ou exclut.
 *
 * Deux niveaux :
 *   1. contrôles unitaires par ligne (`inspectRow`) — pendant le streaming d'import ;
 *   2. contrôle statistique par article (`statisticalOutliers`) — après chargement,
 *      quand on connaît la distribution des quantités d'un même produit.
 */
final class OutlierDetector
{
    public function __construct(
        private readonly float $plafondKgParLigne,
        private readonly float $ratioCommandeLivreeMax,
        private readonly float $ratioEcartMedianeMax,
    ) {}

    public static function fromSettings(): self
    {
        return new self(
            plafondKgParLigne: (float) Setting::get('outlier_qte_max_kg', 1_000_000),
            ratioCommandeLivreeMax: (float) Setting::get('outlier_ratio_commande_livree_max', 1000),
            ratioEcartMedianeMax: (float) Setting::get('outlier_ratio_mediane_max', 25),
        );
    }

    /**
     * Contrôles indépendants de la distribution (applicables ligne par ligne).
     */
    public function inspectRow(float $qteCommandeeKg, float $qteLivreeKg): OutlierVerdict
    {
        if ($qteCommandeeKg < 0 || $qteLivreeKg < 0) {
            return OutlierVerdict::flag('quantite_negative', [
                'qte_commandee_kg' => $qteCommandeeKg,
                'qte_livree_kg' => $qteLivreeKg,
            ]);
        }

        // >= (pas >) : la décision entreprise du 2026-09-11 exclut les lignes
        // "Déchet" à EXACTEMENT 1 000 000 kg — un seuil strictement supérieur
        // laisserait passer cette valeur pile au plafond.
        $maxLigne = max($qteCommandeeKg, $qteLivreeKg);
        if ($maxLigne >= $this->plafondKgParLigne) {
            return OutlierVerdict::flag('quantite_hors_plafond', [
                'valeur_kg' => $maxLigne,
                'plafond_kg' => $this->plafondKgParLigne,
            ]);
        }

        // Écart commande/livraison manifestement impossible :
        // ex. 200 504 005 commandé / 99 livré => erreur de saisie sur la commande.
        if ($qteLivreeKg > 0 && $qteCommandeeKg > 0) {
            $ratio = $qteCommandeeKg / $qteLivreeKg;
            if ($ratio > $this->ratioCommandeLivreeMax) {
                return OutlierVerdict::flag('ratio_commande_livree_impossible', [
                    'ratio' => round($ratio, 1),
                    'seuil' => $this->ratioCommandeLivreeMax,
                ]);
            }
        }

        return OutlierVerdict::ok();
    }

    /**
     * Contrôle statistique : pour une liste de quantités (kg) d'un même article,
     * renvoie les index des valeurs qui s'écartent trop de la médiane.
     *
     * @param  array<int, float>  $quantitesKg
     * @return array<int, array{index:int, valeur:float, mediane:float, ratio:float}>
     */
    public function statisticalOutliers(array $quantitesKg): array
    {
        $valeurs = array_values(array_filter($quantitesKg, fn ($v) => $v > 0));
        if (count($valeurs) < 5) {
            return []; // pas assez d'historique pour juger
        }

        sort($valeurs);
        $mediane = $this->median($valeurs);
        if ($mediane <= 0) {
            return [];
        }

        $out = [];
        foreach ($quantitesKg as $i => $v) {
            if ($v <= 0) {
                continue;
            }
            $ratio = $v / $mediane;
            if ($ratio > $this->ratioEcartMedianeMax) {
                $out[] = [
                    'index' => $i,
                    'valeur' => $v,
                    'mediane' => $mediane,
                    'ratio' => round($ratio, 1),
                ];
            }
        }

        return $out;
    }

    private function median(array $sorted): float
    {
        $n = count($sorted);
        $mid = intdiv($n, 2);

        return $n % 2 === 0
            ? ($sorted[$mid - 1] + $sorted[$mid]) / 2
            : $sorted[$mid];
    }
}
