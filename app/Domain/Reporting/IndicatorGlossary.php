<?php

namespace App\Domain\Reporting;

use App\Models\Setting;

/**
 * Exigence 7 (pédagogie intégrée) — définition unique de chaque indicateur :
 * ce qu'il mesure, les exclusions appliquées, la règle métier de référence
 * (section 2), et le seuil réglable en vigueur.
 *
 * Source UNIQUE : alimente à la fois l'aide contextuelle « ? » de chaque écran
 * et la page « Comment lire ces chiffres ». Toute évolution d'une règle de la
 * section 2 se répercute ici, à un seul endroit.
 */
final class IndicatorGlossary
{
    /**
     * @return array<string, array{
     *     titre: string, definition: string, exclusions: list<string>,
     *     regle: string, seuil?: string, note?: string
     * }>
     */
    public static function all(): array
    {
        $seuilRetard = (int) Setting::get('seuil_retard_jours', 2);
        $seuilRupture = (float) Setting::get('seuil_rupture_pct', 0.5);

        return [
            'satisfaction' => [
                'titre' => 'Taux de satisfaction client',
                'definition' => 'Part des lignes de commande où la quantité livrée est supérieure ou égale à la quantité commandée.',
                'exclusions' => [
                    'Lignes hors périmètre : transferts entre sociétés du groupe, lignes sans quantité.',
                    'Valeurs aberrantes non validées par un administrateur.',
                    'Jamais de moyenne entre sociétés : Randa, SMT et Tunisie Farine affichent chacune leur taux.',
                ],
                'regle' => '2.3',
            ],
            'retard' => [
                'titre' => 'Retard de livraison',
                'definition' => 'Une commande locale est en retard si le délai entre la date de commande et la date de FACTURE dépasse le seuil.',
                'exclusions' => [
                    'Les commandes export ne sont jamais soumises à cette règle.',
                    'Seuil unique pour toute l’entreprise, quelle que soit la ville ou la distance.',
                    'Commande pas encore facturée : retard non calculable pour CETTE commande — jamais un faux « 0 retard ».',
                ],
                'regle' => '2.4',
                'seuil' => $seuilRetard.' jour(s)',
                'note' => 'Proxy accepté par l’entreprise, pas la mesure idéale : la source ne fournit pas de '
                    .'vraie date d’expédition/livraison physique. « Retard » mesure donc en réalité l’écart '
                    .'entre la commande et sa facturation — la facturation précède le départ du camion.',
            ],
            'rupture' => [
                'titre' => 'Rupture de stock',
                'definition' => 'Méthode B (par défaut) : taux de couverture = quantité livrée ÷ quantité commandée sur les commandes encore ouvertes. Méthode A (si un fichier de stock journalier est importé) : nombre de jours avant épuisement au rythme de vente actuel.',
                'exclusions' => [
                    'Méthode active toujours affichée à l’écran.',
                    'Module volontairement simple (priorité basse) — sera retravaillé en dernier.',
                ],
                'regle' => '2.5',
                'seuil' => 'Méthode B : rupture si couverture < '.rtrim(rtrim(number_format($seuilRupture * 100, 1, ',', ' '), '0'), ',').' %. Méthode A : rupture < 2 jours, alerte 2–7 jours.',
            ],
            'tonnes' => [
                'titre' => 'Tonnes commandées / livrées',
                'definition' => 'Quantités converties en kilogrammes puis en tonnes. Le poids d’une unité de vente est mémorisé par article (extrait de la désignation), jamais un facteur de conversion global.',
                'exclusions' => [
                    'Lignes dont le poids d’unité n’a pas pu être déterminé : comptées à part, hors tonnage.',
                    'Unités « quintal » sans précision : hypothèse 100 kg signalée comme non fiable.',
                ],
                'regle' => '2.8',
            ],
            'previsions' => [
                'titre' => 'Prévision de la demande',
                'definition' => 'Estimation par produit du mois à venir, apprise sur les quantités commandées (jamais livrées). Le palier de fiabilité dépend de la longueur d’historique disponible.',
                'exclusions' => [
                    'Local uniquement.',
                    '« Fiabilité haute » = assez d’historique, ce n’est pas une garantie de précision : la précision réelle est l’erreur moyenne mesurée par rétro-test (MAPE).',
                ],
                'regle' => '4.5',
            ],
            'etat_cycle' => [
                'titre' => 'État du cycle de vie d’une commande',
                'definition' => 'Saisie → Réservée → Expédiée → Facturée → Clôturée, dérivé des informations disponibles à l’import. « État incertain » quand la donnée ne permet pas de trancher.',
                'exclusions' => [
                    'Jamais réduit à un simple booléen livré / non livré.',
                ],
                'regle' => '1',
            ],
            'export' => [
                'titre' => 'Périmètre export',
                'definition' => 'Commandes dont le numéro commence par EXP. Totalement séparées du local : absentes du tableau de bord, de Livraisons et de Stock.',
                'exclusions' => [
                    'Aucune règle locale ne s’applique (pas de seuil de retard).',
                    'Satisfaction export calculée par société, jamais mélangée.',
                ],
                'regle' => '2.2',
            ],
            'classification_client' => [
                'titre' => 'Type de client',
                'definition' => 'Chaque client est classé une seule fois, par ordre de priorité : référentiel fourni, puis déduction, puis mots-clés sur le nom, puis « Indéfini ».',
                'exclusions' => [
                    'Le numéro de client est l’identifiant stable, pas le nom.',
                    'Modifier la liste de mots-clés ne reclasse pas automatiquement les clients déjà tranchés.',
                ],
                'regle' => '2.7',
            ],
            'famille' => [
                'titre' => 'Famille de produit',
                'definition' => 'Chaque article est rattaché à l’une des 32 familles officielles, par mémorisation du code article complet observé dans le fichier.',
                'exclusions' => [
                    'Changement de famille dans le temps = reclassement ; deux familles le même mois = ambigu, isolé en anomalie.',
                    'Famille inconnue : isolée, jamais rangée d’office ailleurs.',
                ],
                'regle' => '2.6',
            ],
            'anomalies' => [
                'titre' => 'Anomalies / valeurs aberrantes',
                'definition' => 'Lignes détectées automatiquement comme douteuses (quantité hors normes, famille ambiguë, poids indéterminé…). Elles sortent des indicateurs en attendant l’arbitrage d’un administrateur.',
                'exclusions' => [
                    'Valider = la ligne réintègre les indicateurs ; exclure = elle en reste dehors.',
                    'Une décision d’administrateur est respectée aux imports suivants.',
                ],
                'regle' => '2.9',
            ],
            'fraicheur' => [
                'titre' => 'Fraîcheur des données',
                'definition' => 'Date et heure du dernier import de fichier terminé avec succès. Tous les chiffres de l’application reflètent cet instant — ils ne sont pas recalculés à chaque affichage.',
                'exclusions' => [
                    'Un import échoué ne remplace pas les données : les chiffres restent ceux de l’import précédent.',
                ],
                'regle' => '7',
            ],
        ];
    }
}
