<?php

namespace App\Domain\Metrics;

use App\Domain\Scope\OrderScope;
use App\Models\Setting;
use Carbon\CarbonInterface;

/**
 * Règle métier 2.4 — retards de livraison.
 *
 *   - Seuil unique de 2 jours (48 h) pour TOUTES les commandes locales,
 *     quelle que soit la ville/distance. Réglable par un admin (setting
 *     `seuil_retard_jours`) mais reste un chiffre unique pour l'entreprise.
 *   - Les commandes EXPORT ne sont JAMAIS soumises à cette règle.
 *
 * Décision de l'encadreur (section 10) : en l'absence d'une vraie date
 * d'expédition dans la source, la **date de facture** sert de date de référence
 * pour le retard — un PROXY accepté par l'entreprise, pas la mesure idéale.
 * « Retard » mesure donc en réalité l'écart commande → facturation, pas
 * commande → livraison physique (à documenter dans l'aide contextuelle).
 *
 *   - En retard si : (date de facture - date de commande) > seuil.
 *   - Si la commande n'a pas encore de date de facture (pas encore facturée),
 *     le retard reste NON CALCULABLE pour CETTE commande — jamais un faux 0,
 *     même si le mécanisme n'est plus bloqué globalement (défaut critique V1).
 */
final class DelayCalculator
{
    public function __construct(
        private readonly int $seuilJours,
    ) {}

    public static function fromSettings(): self
    {
        return new self(seuilJours: (int) Setting::get('seuil_retard_jours', 2));
    }

    public function seuilJours(): int
    {
        return $this->seuilJours;
    }

    /**
     * @param  CarbonInterface|null  $dateFacture  proxy accepté pour la date d'expédition (cf. docblock)
     */
    public function evaluate(
        OrderScope $scope,
        ?CarbonInterface $dateCommande,
        ?CarbonInterface $dateFacture,
    ): DelayVerdict {
        if ($scope === OrderScope::Export) {
            return new DelayVerdict(DelayVerdict::NON_APPLICABLE, raison: 'Commande export — règle locale non applicable');
        }

        if ($dateCommande === null) {
            return new DelayVerdict(DelayVerdict::DONNEE_INDISPONIBLE, seuilJours: $this->seuilJours, raison: 'Date de commande absente');
        }

        if ($dateFacture === null) {
            return new DelayVerdict(
                DelayVerdict::DONNEE_INDISPONIBLE,
                seuilJours: $this->seuilJours,
                raison: 'Commande pas encore facturée — retard non calculable pour cette commande.',
            );
        }

        $jours = $dateCommande->startOfDay()->diffInDays($dateFacture->startOfDay(), false);
        $jours = (int) $jours;

        return new DelayVerdict(
            status: $jours > $this->seuilJours ? DelayVerdict::EN_RETARD : DelayVerdict::A_TEMPS,
            joursEcoules: $jours,
            seuilJours: $this->seuilJours,
        );
    }
}
