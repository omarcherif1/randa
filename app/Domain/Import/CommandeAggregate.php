<?php

namespace App\Domain\Import;

use App\Domain\Metrics\SatisfactionCalculator;
use App\Domain\Units\UnitWeightOrigin;
use App\Models\Commande;
use App\Models\CommandeLigne;

/**
 * Recalcule, à UN SEUL endroit, les indicateurs dérivés d'une ligne puis les
 * agrégats de sa commande — à partir des données déjà persistées.
 *
 * Utilisé quand une correction manuelle d'un administrateur change une ligne
 * (résolution d'anomalie, poids unitaire corrigé, famille corrigée) : les KPI
 * doivent refléter la décision, pas rester figés sur l'état de l'import.
 */
final class CommandeAggregate
{
    private const ORIGINES_FIABLES = [
        UnitWeightOrigin::KG_NEUTRE,
        UnitWeightOrigin::EXTRAIT_DESIGNATION,
        UnitWeightOrigin::CORRECTION_MANUELLE,
    ];

    /** Recalcule is_eligible / is_satisfied / compte_volume / volume_fiable d'une ligne. */
    public function rafraichirLigne(CommandeLigne $ligne): void
    {
        $satisfaction = new SatisfactionCalculator;

        $eligible = $ligne->exclusion_reason === null && ! $ligne->is_outlier;
        $compteVolume = $eligible && $ligne->qte_livree_kg !== null
            && $ligne->poids_origine !== UnitWeightOrigin::NON_DETERMINE;

        $ligne->forceFill([
            'is_eligible' => $eligible,
            'is_satisfied' => $eligible && $satisfaction->isLineSatisfied(
                (float) $ligne->qte_commandee_raw, (float) $ligne->qte_livree_raw,
            ),
            'compte_volume' => $compteVolume,
            'volume_fiable' => $compteVolume && in_array($ligne->poids_origine, self::ORIGINES_FIABLES, true),
        ])->save();
    }

    /** Recalcule les agrégats précalculés d'une commande depuis ses lignes. */
    public function rafraichirCommande(Commande $commande): void
    {
        $lignes = $commande->lignes()->get();
        $volume = $lignes->where('compte_volume', true);

        $eligibles = $lignes->where('is_eligible', true)->count();

        $commande->forceFill([
            'qte_commandee_kg' => round((float) $volume->sum('qte_commandee_kg'), 3),
            'qte_livree_kg' => round((float) $volume->sum('qte_livree_kg'), 3),
            'qte_facturee_kg' => round((float) $volume->sum('qte_facturee_kg'), 3),
            'lignes_total' => $lignes->count(),
            'lignes_eligibles' => $eligibles,
            'lignes_satisfaites' => $lignes->where('is_satisfied', true)->count(),
            'is_excluded' => $eligibles === 0,
            'has_outlier' => $lignes->where('is_outlier', true)->count() > 0,
        ])->save();
    }
}
