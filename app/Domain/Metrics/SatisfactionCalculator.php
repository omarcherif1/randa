<?php

namespace App\Domain\Metrics;

/**
 * Règle métier 2.3 — taux de satisfaction client.
 *
 *   - Une ligne est SATISFAITE si : quantité livrée (kg) >= quantité commandée (kg).
 *   - JAMAIS de moyenne entre sociétés : chaque société a son taux propre.
 *   - N'entrent dans le calcul que les lignes éligibles (règles 2.1 / 2.9) :
 *       exclusions, valeurs aberrantes et commandes ouvertes sans livraison
 *       sont retirées EN AMONT (is_eligible = false).
 *
 * ⚠️ `compute()` ci-dessous calcule le taux d'une société comme un ratio
 * GLOBAL sur les LIGNES (lignes satisfaites / lignes éligibles, toutes
 * commandes de la société confondues) — utilisé uniquement par
 * `AnalyzeSourceCommand` (outil CLI de diagnostic), PAS par le tableau de
 * bord/export en production.
 * Depuis le 2026-09-11 (décision produit, redéfinie une 2ᵉ fois le même
 * jour), le tableau de bord et l'export utilisent une méthode différente, au
 * niveau de la COMMANDE (pas de la ligne) : une commande est satisfaite si sa
 * quantité livrée (kg) couvre sa quantité commandée (kg) ; le taux d'une
 * société = nombre de commandes satisfaites / nombre total de commandes —
 * voir `App\Domain\Dashboard\DashboardMetrics::tauxSatisfactionCommandes()`
 * et `App\Domain\Export\ExportMetrics::tauxSatisfactionCommandes()`, qui
 * portent la définition qui fait foi pour l'utilisateur final.
 *
 * Ce calcul respecte les filtres actifs (client, produit, famille, période) —
 * la sélection des lignes est faite par l'appelant, pas ici.
 */
final class SatisfactionCalculator
{
    /**
     * @param  iterable<array{societe_code:string, eligible:bool, satisfaite:bool}>  $lignes
     */
    public function compute(iterable $lignes): SatisfactionResult
    {
        $acc = [];

        foreach ($lignes as $ligne) {
            $code = $ligne['societe_code'];
            $acc[$code] ??= ['eligibles' => 0, 'satisfaites' => 0];

            if (! $ligne['eligible']) {
                continue;
            }

            $acc[$code]['eligibles']++;
            if ($ligne['satisfaite']) {
                $acc[$code]['satisfaites']++;
            }
        }

        $parSociete = [];
        foreach ($acc as $code => $v) {
            $parSociete[$code] = [
                'eligibles' => $v['eligibles'],
                'satisfaites' => $v['satisfaites'],
                'taux' => $v['eligibles'] > 0
                    ? round($v['satisfaites'] / $v['eligibles'], 4)
                    : null,
            ];
        }

        return new SatisfactionResult($parSociete);
    }

    /**
     * Décision unitaire "cette ligne est-elle satisfaite ?" — même définition
     * partout (import, recalcul, tests).
     */
    public function isLineSatisfied(float $qteCommandeeKg, float $qteLivreeKg): bool
    {
        return $qteLivreeKg + 0.0001 >= $qteCommandeeKg;
    }
}
