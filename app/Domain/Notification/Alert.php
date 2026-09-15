<?php

namespace App\Domain\Notification;

/**
 * Une alerte du centre de notifications (section 6). Objet immuable produit par
 * une {@see AlertRule} et consommé tel quel par la vue — aucune logique métier ici.
 */
final class Alert
{
    public const CRITIQUE = 'critique';

    public const ALERTE = 'alerte';

    public const INFO = 'info';

    /**
     * @param  string  $type  famille d'alerte (ex. « retard_commande », « rupture_stock »)
     * @param  string  $severity  self::CRITIQUE | self::ALERTE | self::INFO
     * @param  string  $titre  résumé court
     * @param  string  $detail  phrase explicative (chiffres exacts, jamais approximés)
     * @param  string|null  $routeName  lien profond vers la page concernée
     * @param  array<string, mixed>  $routeParams
     * @param  string|null  $reference  identifiant stable du sujet (dédoublonnage / suivi)
     */
    public function __construct(
        public readonly string $type,
        public readonly string $severity,
        public readonly string $titre,
        public readonly string $detail,
        public readonly ?string $routeName = null,
        public readonly array $routeParams = [],
        public readonly ?string $reference = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'severity' => $this->severity,
            'titre' => $this->titre,
            'detail' => $this->detail,
            'route' => $this->routeName,
            'route_params' => (object) $this->routeParams,
            'reference' => $this->reference ?? $this->type,
        ];
    }
}
