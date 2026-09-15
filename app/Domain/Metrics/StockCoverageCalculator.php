<?php

namespace App\Domain\Metrics;

use App\Models\Setting;

/**
 * Règle métier 2.5 — ruptures de stock. Deux méthodes ; l'interface doit
 * indiquer clairement laquelle est active.
 *
 * Méthode A — fichier de stock journalier réel disponible :
 *   couverture (jours) = stock disponible / rythme de vente journalier
 *   rupture si < 2 jours ; alerte si 2 à 7 jours ; sinon normal.
 *
 * Méthode B — déduction depuis les commandes ouvertes :
 *   taux de couverture = quantité livrée / quantité commandée (commandes encore ouvertes)
 *   rupture si taux < seuil réglable (50 % par défaut) ; alerte au-dessus (< 100 %) ;
 *   normal si entièrement couvert.
 *
 * NB (section 2.5) : module le moins abouti, à retravailler en dernier.
 * Implémentation fidèle mais volontairement simple.
 */
final class StockCoverageCalculator
{
    public function __construct(
        private readonly float $seuilRupturePct,
        private readonly float $couvertureJoursRupture,
        private readonly float $couvertureJoursAlerte,
    ) {}

    public static function fromSettings(): self
    {
        return new self(
            seuilRupturePct: (float) Setting::get('seuil_rupture_pct', 0.5),
            couvertureJoursRupture: (float) Setting::get('rupture_couverture_jours_bas', 2),
            couvertureJoursAlerte: (float) Setting::get('rupture_couverture_jours_haut', 7),
        );
    }

    /** Méthode A. */
    public function fromDailyStock(float $stockKg, float $ventesJournalieresKg): StockCoverageResult
    {
        if ($ventesJournalieresKg <= 0) {
            return new StockCoverageResult(
                StockStatus::Indetermine,
                methode: 'A',
                detail: 'Rythme de vente nul ou inconnu',
            );
        }

        $jours = $stockKg / $ventesJournalieresKg;

        $status = match (true) {
            $jours < $this->couvertureJoursRupture => StockStatus::Rupture,
            $jours <= $this->couvertureJoursAlerte => StockStatus::Alerte,
            default => StockStatus::Normal,
        };

        return new StockCoverageResult($status, methode: 'A', joursCouverture: round($jours, 1));
    }

    /** Méthode B. */
    public function fromOpenOrders(float $qteCommandeeKg, float $qteLivreeKg): StockCoverageResult
    {
        if ($qteCommandeeKg <= 0) {
            return new StockCoverageResult(
                StockStatus::Indetermine,
                methode: 'B',
                detail: 'Aucune quantité commandée ouverte',
            );
        }

        $taux = $qteLivreeKg / $qteCommandeeKg;

        $status = match (true) {
            $taux < $this->seuilRupturePct => StockStatus::Rupture,
            $taux < 1.0 => StockStatus::Alerte,
            default => StockStatus::Normal,
        };

        return new StockCoverageResult($status, methode: 'B', tauxCouverture: round($taux, 4));
    }
}
