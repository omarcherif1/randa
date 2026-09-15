<?php

namespace App\Domain\Import;

/**
 * Section 1 — reconstitue l'état d'une commande dans son cycle réel :
 *   saisie → réservée → expédiée → facturée → clôturée.
 *
 * Le fichier ne permet pas toujours de trancher entre "réservé" (stock bloqué,
 * rien n'a quitté l'usine) et "physiquement expédié". Quand c'est le cas,
 * `incertain` est vrai — l'interface ne doit jamais afficher un simple
 * booléen livré/non livré.
 */
final class OrderLifecycle
{
    public const SAISIE = 'saisie';

    public const RESERVEE = 'reservee';

    public const EXPEDIEE = 'expediee';

    public const FACTUREE = 'facturee';

    public const CLOTUREE = 'cloturee';

    /** @return list<string> états du cycle, dans l'ordre (section 1) */
    public static function etatsOrdonnes(): array
    {
        return [self::SAISIE, self::RESERVEE, self::EXPEDIEE, self::FACTUREE, self::CLOTUREE];
    }

    public static function libelle(string $etat): string
    {
        return match ($etat) {
            self::SAISIE => 'Saisie',
            self::RESERVEE => 'Réservée',
            self::EXPEDIEE => 'Expédiée',
            self::FACTUREE => 'Facturée',
            self::CLOTUREE => 'Clôturée',
            default => $etat,
        };
    }

    /**
     * @param  list<array{etat_ligne:string, numero_livraison:?string, numero_facture:?string, date_facture:mixed}>  $lignes
     * @return array{etat:string, incertain:bool}
     */
    public function derive(array $lignes): array
    {
        $aLivraison = false;
        $aFacture = false;
        $lignesAvecEtat = 0;
        $toutesSoldees = true;

        foreach ($lignes as $l) {
            if (! empty($l['numero_livraison'])) {
                $aLivraison = true;
            }
            if (! empty($l['numero_facture']) && ! empty($l['date_facture'])) {
                $aFacture = true;
            }
            $etat = mb_strtolower(trim((string) ($l['etat_ligne'] ?? '')));
            if ($etat !== '') {
                $lignesAvecEtat++;
                if (! str_starts_with($etat, 'sold')) {
                    $toutesSoldees = false;
                }
            }
        }

        if ($lignesAvecEtat > 0 && $toutesSoldees && $aFacture) {
            return ['etat' => self::CLOTUREE, 'incertain' => false];
        }
        if ($aFacture) {
            // facturation obligatoire avant que le camion parte (section 1)
            return ['etat' => self::FACTUREE, 'incertain' => false];
        }
        if ($aLivraison) {
            // livraison ouverte : stock réservé — impossible de confirmer l'expédition physique
            return ['etat' => self::RESERVEE, 'incertain' => true];
        }

        return ['etat' => self::SAISIE, 'incertain' => false];
    }
}
