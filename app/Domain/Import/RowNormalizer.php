<?php

namespace App\Domain\Import;

use App\Domain\Classification\ClientCategoryClassifier;
use App\Domain\Classification\FamilyResolver;
use App\Domain\Metrics\SatisfactionCalculator;
use App\Domain\Quality\LineExclusionEvaluator;
use App\Domain\Quality\OutlierDetector;
use App\Domain\Scope\OrderScope;
use App\Domain\Scope\OrderScopeClassifier;
use App\Domain\Units\ArticleUnitResolver;
use App\Domain\Units\UnitWeightOrigin;
use App\Support\Text;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * SEUL endroit qui transforme une ligne brute du fichier unifié en NormalizedLine,
 * en appliquant dans l'ordre TOUTES les règles de la section 2 :
 *   2.2 scope export/local · 2.8 conversion d'unités · 2.6 famille ·
 *   2.7 catégorie client · 2.1 exclusions · 2.9 valeurs aberrantes ·
 *   2.3 satisfaction de la ligne.
 *
 * Aucune de ces règles n'est ré-implémentée ailleurs : les pages, exports et
 * agrégats consomment le résultat, ils ne recalculent pas.
 */
final class RowNormalizer
{
    public function __construct(
        private readonly ColumnMap $columns,
        private readonly ReferenceData $ref,
        private readonly ArticleUnitResolver $units,
        private readonly FamilyResolver $familyResolver,
        private readonly ClientCategoryClassifier $clientClassifier,
        private readonly OrderScopeClassifier $scopeClassifier = new OrderScopeClassifier,
        private readonly LineExclusionEvaluator $exclusion = new LineExclusionEvaluator,
        private readonly ?OutlierDetector $outliers = null,
        private readonly SatisfactionCalculator $satisfaction = new SatisfactionCalculator,
    ) {}

    /** @param list<string> $row */
    public function normalize(array $row): NormalizedLine
    {
        $c = $this->columns;
        $anomalies = [];

        $numeroCommande = $c->value($row, 'no_commande');
        $scope = $this->scopeClassifier->scope($numeroCommande);
        if (! $this->scopeClassifier->isRecognized($numeroCommande)) {
            $anomalies[] = 'numero_commande_non_reconnu';
        }
        $societeCode = $this->scopeClassifier->societeCode($numeroCommande);

        $numeroClient = $c->value($row, 'client_numero');
        $nomClient = $c->value($row, 'client_nom');

        // --- Règle 2.7 : catégorie du client (livré à l'étranger si commande export) ---
        $resolution = $this->clientClassifier->classify(
            $numeroClient,
            $nomClient,
            livreALEtranger: $scope === OrderScope::Export,
        );
        $clientEstGroupe = $this->ref->isGroupCategory($resolution->categoryCode);

        // --- Quantités brutes (unité d'origine — base de la satisfaction) ---
        $qc = Text::toFloat($c->value($row, 'qte_commandee'));
        $ql = Text::toFloat($c->value($row, 'qte_livree'));
        $qf = Text::toFloat($c->value($row, 'qte_facturee'));
        $unite = $c->value($row, 'unite_vente');
        $article = $c->value($row, 'article');
        $designation = $c->has('designation') ? $c->value($row, 'designation') : '';
        $dateCommande = $this->parseDate($c->value($row, 'date_commande'));

        // --- Règle 2.8 : poids unitaire (article par article) puis conversion en kg ---
        // KG -> neutre · SAC/T -> poids extrait de la désignation · QX -> hypothèse 100 kg
        // · rien de trouvable -> non déterminé (ligne exclue des tonnages, jamais devinée).
        //
        // « Qté facturée US » (colonne source) est DÉJÀ exprimée en kg — contrairement à
        // « Quantité commandée »/« Quantité livrée », qui sont dans l'unité de vente brute
        // (SAC/T/QX/KG). Vérifié ligne par ligne sur le fichier source (2026-09-11) : ex.
        // SAC 50 kg, commandée=2000, facturée US=100000 = 2000×50 (déjà le kg, pas un
        // second facteur à appliquer) ; QX hypothèse 100 kg, commandée=6, facturée US=600
        // = 6×100, même constat. Lui appliquer la conversion (comme pour commandée/livrée)
        // la multipliait une seconde fois par le poids unitaire — d'où des valeurs de
        // "Facturé" aberrantes (jusqu'à ×1000 la réalité). Elle ne dépend donc PAS de la
        // résolution du poids unitaire (même quand celui-ci est non déterminé).
        $poids = $this->units->resolve($article, $designation, $unite);
        $qcKg = $poids->convertir($qc);
        $qlKg = $poids->convertir($ql);
        $qfKg = round($qf, 3);

        if ($poids->origine === UnitWeightOrigin::NON_DETERMINE) {
            $anomalies[] = 'poids_unitaire_indetermine:'.$unite;
        } elseif ($poids->origine === UnitWeightOrigin::HYPOTHESE_QUINTAL) {
            $anomalies[] = 'poids_quintal_hypothese';
        }
        if ($poids->enConflit) {
            $anomalies[] = 'poids_unitaire_conflit';
        }

        // --- Règle 2.6 : famille (clé = code article COMPLET, jamais un préfixe) ---
        $familleCodeFichier = $c->familyCode($row);
        $fam = $this->familyResolver->resolve(
            $familleCodeFichier,
            $article,
            $dateCommande?->format('Y-m-d'),
            $designation,
        );
        if ($fam->code === null) {
            $anomalies[] = 'famille_inconnue';
        }

        // --- Règle 2.1 : exclusions (sur les quantités brutes : seul le zéro compte) ---
        $exclusionReason = $this->exclusion->evaluate($qc, $ql, $clientEstGroupe);

        // --- Règle 2.9 : valeurs aberrantes (sur les quantités en kg) ---
        $isOutlier = false;
        $outlierReason = null;
        if ($this->outliers !== null && $qcKg !== null && $qlKg !== null) {
            $verdict = $this->outliers->inspectRow($qcKg, $qlKg);
            if ($verdict->isOutlier) {
                $isOutlier = true;
                $outlierReason = $verdict->reason;
                $anomalies[] = 'outlier:'.$verdict->reason;
            }
        }

        // --- Règle 2.3 : la ligne est-elle éligible / satisfaite ? ---
        // La satisfaction (livrée >= commandée) est indépendante de l'unité :
        // le facteur de conversion se simplifie. On juge donc sur les quantités
        // brutes, pour ne pas exclure du taux de satisfaction les lignes dont
        // seule la CONVERSION en kg est incertaine (règle 2.8).
        $isEligible = $exclusionReason === null && ! $isOutlier;
        $isSatisfied = $isEligible && $this->satisfaction->isLineSatisfied($qc, $ql);

        // Pour les AGRÉGATS DE VOLUME (tonnes) : il faut un poids unitaire déterminé.
        // Les lignes QX (hypothèse) comptent mais sont marquées "volume non fiable".
        $compteVolume = $isEligible && $poids->compteVolume();
        $volumeFiable = $compteVolume && $poids->volumeFiable();

        return new NormalizedLine(
            numeroCommande: $numeroCommande,
            scope: $scope,
            societeCode: $societeCode,
            dateCommande: $dateCommande,
            dateFacture: $c->has('date_facture') ? $this->parseDate($c->value($row, 'date_facture')) : null,
            siteVente: $c->has('site_vente') ? $c->value($row, 'site_vente') : '',
            villeCommande: $c->has('ville_commande') ? $c->value($row, 'ville_commande') : '',
            adresseLivraison: $c->has('adresse_livraison') ? $c->value($row, 'adresse_livraison') : '',
            numeroClient: $numeroClient,
            nomClient: $nomClient,
            clientCategoryCode: $resolution->categoryCode,
            clientCategorySource: $resolution->source,
            clientCategoryDetail: $resolution->detail,
            article: $article,
            designation: $designation,
            uniteVente: $unite,
            qteCommandeeRaw: $qc,
            qteLivreeRaw: $ql,
            qteFactureeRaw: $qf,
            qteCommandeeKg: $qcKg,
            qteLivreeKg: $qlKg,
            qteFactureeKg: $qfKg,
            poidsUnitaireKg: $poids->poidsUnitaireKg,
            poidsOrigine: $poids->origine,
            volumeFiable: $volumeFiable,
            poidsEnConflit: $poids->enConflit,
            familleCode: $fam->code,
            familleSource: $fam->source,
            familleCodeFichier: $familleCodeFichier,
            numeroLivraison: $c->has('no_livraison') ? ($c->value($row, 'no_livraison') ?: null) : null,
            numeroFacture: $c->has('no_facture') ? ($c->value($row, 'no_facture') ?: null) : null,
            etatLigne: $c->has('etat_ligne') ? $c->value($row, 'etat_ligne') : '',
            exclusionReason: $exclusionReason,
            isOutlier: $isOutlier,
            outlierReason: $outlierReason,
            isEligible: $isEligible,
            isSatisfied: $isSatisfied,
            compteVolume: $compteVolume,
            anomalies: $anomalies,
        );
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'd/m/y', 'Y-m-d', 'd-m-Y'] as $fmt) {
            try {
                $d = CarbonImmutable::createFromFormat($fmt, $value);
                if ($d !== false) {
                    return $d->startOfDay();
                }
            } catch (Throwable) {
                // format suivant
            }
        }

        return null;
    }
}
