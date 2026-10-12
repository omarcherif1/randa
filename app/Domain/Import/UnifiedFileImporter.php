<?php

namespace App\Domain\Import;

use App\Domain\Classification\FamilyColumnDetector;
use App\Domain\Classification\FamilyConsolidation;
use App\Domain\Quality\OutlierDetector;
use App\Domain\Units\ArticleUnitResolver;
use App\Domain\Units\UnitWeightOrigin;
use App\Models\ArticleFamily;
use App\Models\ArticleUnitWeight;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CommandeLigne;
use App\Models\ImportBatch;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;

/**
 * Étape 2 — moteur d'import du fichier unifié quotidien.
 *
 * Étape INDÉPENDANTE et remplaçable (objectif V3 : dépôt/synchro automatique) :
 * ne dépend d'aucune requête HTTP, prend un chemin de fichier + un ImportBatch.
 *
 * Garanties :
 *   - dédoublonnage par REMPLACEMENT (règle 4.7) : une commande déjà connue voit
 *     ses lignes entièrement remplacées, jamais additionnées ;
 *   - détection automatique des valeurs aberrantes (2.9) et des familles
 *     manquantes/ambiguës (2.6) à CHAQUE import ;
 *   - toutes les règles de la section 2 passent par RowNormalizer (un seul endroit) ;
 *   - tout est écrit dans une transaction : un échec ne laisse pas de demi-import.
 */
final class UnifiedFileImporter
{
    public function __construct(
        private readonly UnifiedFileValidator $validator = new UnifiedFileValidator,
        private readonly OrderLifecycle $lifecycle = new OrderLifecycle,
    ) {}

    public function import(string $path, ImportBatch $batch): ImportResult
    {
        @ini_set('memory_limit', '1024M');

        $validation = $this->validator->validate($path);
        if (! $validation->ok()) {
            throw new ImportValidationException($validation->messageErreur());
        }

        $ref = ReferenceData::load();
        $reader = new CsvReader($path);
        $header = $reader->header();
        $columns = new ColumnMap($header);

        // --- Règle 2.6 : colonne famille (forcée par un admin, sinon auto-détectée) ---
        $familleColonne = $ref->familleColonneForcee();
        $candidates = null;
        if ($familleColonne === null) {
            $sample = [];
            foreach ($reader->rows(3000) as $r) {
                $sample[] = $r;
            }
            $detection = (new FamilyColumnDetector)->detect($header, $sample, $ref->familleCodes, $columns->familyColumnCandidates);
            $familleColonne = $detection->chosenIndex;
            $candidates = $detection->candidates;
        }
        $columns->withFamilyColumn($familleColonne);

        $unitResolver = $ref->articleUnitResolver();
        $familyResolver = $ref->familyResolver();
        $normalizer = new RowNormalizer(
            columns: $columns,
            ref: $ref,
            units: $unitResolver,
            familyResolver: $familyResolver,
            clientClassifier: $ref->clientClassifier(),
            outliers: OutlierDetector::fromSettings(),
        );

        $result = new ImportResult(
            familleColonneIndex: $familleColonne,
            familleColonnesCandidates: $candidates,
            warnings: $validation->warnings,
        );

        // === Lecture + normalisation ===
        /** @var array<string, list<NormalizedLine>> $grouped */
        $grouped = [];
        foreach ($reader->rows() as $row) {
            $result->rowsTotal++;
            $line = $normalizer->normalize($row);
            if ($line->numeroCommande === '') {
                $result->rowsSkipped++;

                continue;
            }
            $grouped[$line->numeroCommande][] = $line;
        }

        $nonRepares = $reader->reparation?->nonRepares() ?? [];
        if ($nonRepares !== []) {
            arsort($nonRepares);
            $result->warnings[] = 'Accents non réparés (à ajouter dans config/lexique_accents.php) : '
                .implode(', ', array_map(fn ($mot, $n) => "« {$mot} » ×{$n}", array_keys($nonRepares), $nonRepares)).'.';
        }

        // === Consolidation famille (règle 2.6, fin d'import) ===
        $consolidations = $familyResolver->consolidate();
        /** @var array<string, string> article => code famille tranché (confirmé / reclassé) */
        $familleParArticle = [];
        foreach ($consolidations as $cons) {
            if ($cons->familleCode !== null && ! $cons->estAmbigu()) {
                $familleParArticle[$cons->article] = $cons->familleCode;
            }
        }

        $recorder = new AnomalyRecorder;

        DB::transaction(function () use (
            $grouped, $result, $ref, $unitResolver, $consolidations,
            $familleParArticle, $recorder, $batch
        ) {
            $this->persistArticleUnitWeights($unitResolver, $recorder);
            $this->persistArticleFamilies($consolidations, $recorder, $ref);

            [$clientIdByNumero, $result->clientsNew] = $this->upsertClients($grouped, $ref);
            [$produitIdByArticle, $result->produitsNew] = $this->upsertProduits($grouped, $ref, $familleParArticle);

            $this->persistCommandes(
                $grouped, $result, $ref, $clientIdByNumero, $produitIdByArticle,
                $familleParArticle, $recorder, $batch,
            );

            $this->recordLineAnomalies($grouped, $recorder);
            $result->anomaliesCount = $recorder->flush($batch);
        });

        $result->rowsImported = $result->rowsTotal - $result->rowsSkipped;
        $result->stats = [
            'commandes_total' => count($grouped),
            'articles_distincts' => count($consolidations),
            'articles_famille_ambigue' => count(array_filter($consolidations, fn ($c) => $c->estAmbigu())),
            'articles_famille_reclassee' => count(array_filter($consolidations, fn ($c) => $c->estReclassification())),
            'poids_conflits' => count($unitResolver->conflicts()),
            'quintal_divergences' => count($unitResolver->quintalDivergences()),
        ];

        return $result;
    }

    /** Règle 2.8 — mémorise le poids unitaire par article (jamais un facteur global). */
    private function persistArticleUnitWeights(ArticleUnitResolver $resolver, AnomalyRecorder $recorder): void
    {
        $conflits = [];
        foreach ($resolver->conflicts() as $c) {
            $conflits[$c['article']] = $c;
        }

        foreach ($resolver->pendingWeights() as $w) {
            $existant = ArticleUnitWeight::where('article', $w['article'])->first();

            // On ne touche jamais une valeur corrigée manuellement par un admin.
            if ($existant && $existant->origine === UnitWeightOrigin::CORRECTION_MANUELLE) {
                continue;
            }

            $enConflit = isset($conflits[$w['article']]);
            ArticleUnitWeight::updateOrCreate(
                ['article' => $w['article']],
                [
                    'designation' => $w['designation'] ?: ($existant->designation ?? null),
                    'unite_vente' => $w['unite'],
                    'poids_unitaire_kg' => $w['poids'],
                    'origine' => $w['origine'],
                    'designation_reference' => $w['origine'] === UnitWeightOrigin::EXTRAIT_DESIGNATION
                        ? $w['designation']
                        : ($existant->designation_reference ?? null),
                    'en_conflit' => $enConflit,
                    'conflit_details' => $enConflit ? $conflits[$w['article']] : null,
                ],
            );

            if ($w['origine'] === UnitWeightOrigin::NON_DETERMINE) {
                $recorder->add(
                    'poids_unitaire_indetermine', 'warning',
                    "Poids d'une unité « {$w['unite']} » introuvable pour l'article {$w['article']} — lignes exclues des tonnages",
                    "poids_indetermine:{$w['article']}",
                    ['article' => $w['article'], 'unite' => $w['unite'], 'designation' => $w['designation']],
                    $w['article'],
                );
            }
        }

        foreach ($resolver->conflicts() as $c) {
            $recorder->add(
                'poids_unitaire_conflit', 'critical',
                "Poids de l'article {$c['article']} : {$c['poids_valide_kg']} kg validé vs {$c['poids_extrait_kg']} kg extrait — ancienne valeur conservée",
                "poids_conflit:{$c['article']}",
                $c,
                $c['article'],
            );
        }

        foreach ($resolver->quintalDivergences() as $d) {
            $recorder->add(
                'quintal_hypothese_divergente', 'warning',
                "QX article {$d['article']} : hypothèse {$d['poids_hypothese_kg']} kg mais « {$d['designation']} » évoque {$d['poids_dans_designation_kg']} kg",
                "quintal_divergence:{$d['article']}",
                $d,
                $d['article'],
            );
        }
    }

    /**
     * Règle 2.6 — mémorise la famille par CODE ARTICLE COMPLET + journalise
     * reclassements et ambiguïtés.
     *
     * @param  array<string, FamilyConsolidation>  $consolidations
     */
    private function persistArticleFamilies(array $consolidations, AnomalyRecorder $recorder, ReferenceData $ref): void
    {
        foreach ($consolidations as $cons) {
            $existant = ArticleFamily::where('article', $cons->article)->first();
            if ($existant && $existant->statut === 'correction_manuelle') {
                continue; // décision admin intouchable
            }

            ArticleFamily::updateOrCreate(
                ['article' => $cons->article],
                [
                    'famille_code' => $cons->familleCode,
                    'famille_id' => $cons->familleCode !== null ? ($ref->familleIdByCode[$cons->familleCode] ?? null) : null,
                    'statut' => $cons->statut,
                    'historique' => $cons->historique,
                ],
            );

            if ($cons->estAmbigu()) {
                $recorder->add(
                    'famille_ambigue', 'warning',
                    "Article {$cons->article} : plusieurs familles sans logique de date — confirmation manuelle requise",
                    "famille_ambigue:{$cons->article}",
                    ['article' => $cons->article, 'historique' => $cons->historique],
                    $cons->article,
                );
            } elseif ($cons->estReclassification()) {
                $recorder->add(
                    'famille_reclassee', 'info',
                    "Article {$cons->article} : reclassé dans le temps — retient {$cons->familleCode} (à confirmer)",
                    "famille_reclassee:{$cons->article}",
                    ['article' => $cons->article, 'historique' => $cons->historique],
                    $cons->article,
                );
            }
        }
    }

    /**
     * @param  array<string, list<NormalizedLine>>  $grouped
     * @return array{0: array<string,int>, 1: int}
     */
    private function upsertClients(array $grouped, ReferenceData $ref): array
    {
        // priorité des sources de catégorie (règle 2.7)
        $priorite = ['reference' => 3, 'deduced' => 2, 'keyword' => 1, 'undefined' => 0];

        /** @var array<string, array<string,mixed>> $clients */
        $clients = [];
        foreach ($grouped as $lines) {
            foreach ($lines as $l) {
                $num = $l->numeroClient;
                if ($num === '') {
                    continue;
                }
                $d = $l->dateCommande?->toDateString();
                if (! isset($clients[$num])) {
                    $clients[$num] = [
                        'nom' => $l->nomClient, 'cat' => $l->clientCategoryCode,
                        'cat_source' => $l->clientCategorySource, 'cat_detail' => $l->clientCategoryDetail,
                        'ville' => $l->villeCommande, 'first' => $d, 'last' => $d,
                    ];

                    continue;
                }
                $c = &$clients[$num];
                if ($d !== null && ($c['last'] === null || $d > $c['last'])) {
                    $c['last'] = $d;
                    $c['nom'] = $l->nomClient;
                    $c['ville'] = $l->villeCommande;
                }
                if ($d !== null && ($c['first'] === null || $d < $c['first'])) {
                    $c['first'] = $d;
                }
                // meilleure source de catégorie vue
                if (($priorite[$l->clientCategorySource] ?? 0) > ($priorite[$c['cat_source']] ?? 0)) {
                    $c['cat'] = $l->clientCategoryCode;
                    $c['cat_source'] = $l->clientCategorySource;
                    $c['cat_detail'] = $l->clientCategoryDetail;
                }
                unset($c);
            }
        }

        $existants = collect();
        foreach (array_chunk(array_keys($clients), 5000) as $chunk) {
            $existants = $existants->merge(Client::whereIn('numero', $chunk)->get());
        }
        $existants = $existants->keyBy('numero');
        $new = 0;

        foreach ($clients as $num => $c) {
            $existant = $existants->get($num);
            $catId = $c['cat'] !== null ? ($ref->categoryIdByCode[$c['cat']] ?? null) : null;

            $attrs = [
                'nom' => $c['nom'],
                'derniere_ville_commande' => $c['ville'] ?: null,
                'last_seen_at' => $c['last'],
            ];
            // Règle 2.7 : classifié une seule fois — on ne rétrograde pas une catégorie
            // déjà déterminée par une source plus fiable.
            if (! $existant
                || $existant->client_category_id === null
                || ($priorite[$c['cat_source']] ?? 0) >= ($priorite[$existant->category_source] ?? 0)
            ) {
                $attrs['client_category_id'] = $catId;
                $attrs['category_source'] = $c['cat_source'];
                $attrs['category_detail'] = $c['cat_detail'];
            }
            if (! $existant) {
                $attrs['first_seen_at'] = $c['first'];
            }

            Client::updateOrCreate(['numero' => $num], $attrs);
            if (! $existant) {
                $new++;
            }
        }

        return [$this->idMap(Client::class, 'numero', array_keys($clients)), $new];
    }

    /**
     * @param  array<string, list<NormalizedLine>>  $grouped
     * @param  array<string, string>  $familleParArticle
     * @return array{0: array<string,int>, 1: int}
     */
    private function upsertProduits(array $grouped, ReferenceData $ref, array $familleParArticle): array
    {
        /** @var array<string, array<string,mixed>> $produits */
        $produits = [];
        foreach ($grouped as $lines) {
            foreach ($lines as $l) {
                if ($l->article === '') {
                    continue;
                }
                $d = $l->dateCommande?->toDateString();
                if (! isset($produits[$l->article])) {
                    $produits[$l->article] = ['designation' => $l->designation, 'unite' => $l->uniteVente, 'first' => $d, 'last' => $d];

                    continue;
                }
                $p = &$produits[$l->article];
                if ($d !== null && ($p['last'] === null || $d > $p['last'])) {
                    $p['last'] = $d;
                    $p['designation'] = $l->designation ?: $p['designation'];
                    $p['unite'] = $l->uniteVente ?: $p['unite'];
                }
                if ($d !== null && ($p['first'] === null || $d < $p['first'])) {
                    $p['first'] = $d;
                }
                unset($p);
            }
        }

        $existants = [];
        foreach (array_chunk(array_keys($produits), 5000) as $chunk) {
            foreach (Produit::whereIn('article', $chunk)->pluck('article') as $a) {
                $existants[$a] = true;
            }
        }
        $new = 0;

        foreach ($produits as $article => $p) {
            $code = $familleParArticle[$article] ?? null;
            $attrs = [
                'designation' => $p['designation'] ?: null,
                'unite_vente_frequente' => $p['unite'] ?: null,
                'last_seen_at' => $p['last'],
            ];
            if ($code !== null) {
                $attrs['famille_id'] = $ref->familleIdByCode[$code] ?? null;
                $attrs['famille_source'] = 'file';
            }
            if (! isset($existants[$article])) {
                $attrs['first_seen_at'] = $p['first'];
                $new++;
            }
            Produit::updateOrCreate(['article' => $article], $attrs);
        }

        return [$this->idMap(Produit::class, 'article', array_keys($produits)), $new];
    }

    /**
     * Cœur du dédoublonnage par remplacement (règle 4.7).
     *
     * @param  array<string, list<NormalizedLine>>  $grouped
     * @param  array<string,int>  $clientIdByNumero
     * @param  array<string,int>  $produitIdByArticle
     * @param  array<string,string>  $familleParArticle
     */
    private function persistCommandes(
        array $grouped, ImportResult $result, ReferenceData $ref,
        array $clientIdByNumero, array $produitIdByArticle, array $familleParArticle,
        AnomalyRecorder $recorder, ImportBatch $batch,
    ): void {
        $existantes = $this->idMap(Commande::class, 'numero', array_keys($grouped));

        $now = now();
        $lignesBuffer = [];

        foreach ($grouped as $numero => $lines) {
            $repr = $lines[0];
            $societeId = $repr->societeCode !== null ? ($ref->societeIdByCode[$repr->societeCode] ?? null) : null;

            $dateFacture = null;
            $numeroFacture = $numeroLivraison = null;
            $qcKg = $qlKg = $qfKg = 0.0;
            $lignesEligibles = $lignesSatisfaites = 0;
            $hasOutlier = false;
            $lifecycleInput = [];

            foreach ($lines as $l) {
                if ($l->dateFacture !== null && ($dateFacture === null || $l->dateFacture->gt($dateFacture))) {
                    $dateFacture = $l->dateFacture;
                }
                $numeroFacture ??= $l->numeroFacture;
                $numeroLivraison ??= $l->numeroLivraison;
                if ($l->compteVolume) {
                    $qcKg += $l->qteCommandeeKg ?? 0;
                    $qlKg += $l->qteLivreeKg ?? 0;
                    $qfKg += $l->qteFactureeKg ?? 0;
                }
                if ($l->isEligible) {
                    $lignesEligibles++;
                    if ($l->isSatisfied) {
                        $lignesSatisfaites++;
                    }
                }
                $hasOutlier = $hasOutlier || $l->isOutlier;
                $lifecycleInput[] = [
                    'etat_ligne' => $l->etatLigne,
                    'numero_livraison' => $l->numeroLivraison,
                    'numero_facture' => $l->numeroFacture,
                    'date_facture' => $l->dateFacture,
                ];

                if (! $this->scopeReconnu($l)) {
                    $recorder->add(
                        'numero_commande_non_reconnu', 'warning',
                        "Numéro de commande « {$numero} » non reconnu (préfixe CM/EXP attendu)",
                        "numero_non_reconnu:{$numero}",
                        ['numero' => $numero],
                    );
                }
            }

            $etat = $this->lifecycle->derive($lifecycleInput);
            $isExcluded = $lignesEligibles === 0;

            $commandeAttrs = [
                'scope' => $repr->scope->value,
                'societe_id' => $societeId,
                'client_id' => $clientIdByNumero[$repr->numeroClient] ?? null,
                'import_batch_id' => $batch->id,
                'date_commande' => $repr->dateCommande?->toDateString(),
                'site_vente' => $repr->siteVente ?: null,
                'ville_commande' => $repr->villeCommande ?: null,
                'adresse_livraison' => $repr->adresseLivraison ?: null,
                'etat' => $etat['etat'],
                'etat_incertain' => $etat['incertain'],
                'numero_livraison' => $numeroLivraison,
                'numero_facture' => $numeroFacture,
                'date_facture' => $dateFacture?->toDateString(),
                'qte_commandee_kg' => round($qcKg, 3),
                'qte_livree_kg' => round($qlKg, 3),
                'qte_facturee_kg' => round($qfKg, 3),
                'lignes_total' => count($lines),
                'lignes_eligibles' => $lignesEligibles,
                'lignes_satisfaites' => $lignesSatisfaites,
                'is_excluded' => $isExcluded,
                'has_outlier' => $hasOutlier,
            ];

            if (isset($existantes[$numero])) {
                $commandeId = $existantes[$numero];
                Commande::where('id', $commandeId)->update($commandeAttrs + ['updated_at' => $now]);
                CommandeLigne::where('commande_id', $commandeId)->delete(); // REMPLACEMENT (jamais additionné)
                $result->commandesReplaced++;
            } else {
                $commandeId = Commande::insertGetId($commandeAttrs + ['numero' => $numero, 'created_at' => $now, 'updated_at' => $now]);
                $result->commandesNew++;
            }

            foreach ($lines as $l) {
                $familleCode = $l->familleCode ?? ($familleParArticle[$l->article] ?? null);
                $familleSource = $l->familleCode !== null ? $l->familleSource
                    : ($familleCode !== null ? 'backfilled' : 'unknown');
                if ($familleCode === null) {
                    $result->famillesInconnuesCount++;
                }

                $lignesBuffer[] = [
                    'commande_id' => $commandeId,
                    'produit_id' => $produitIdByArticle[$l->article] ?? null,
                    'famille_id' => $familleCode !== null ? ($ref->familleIdByCode[$familleCode] ?? null) : null,
                    'article' => $l->article ?: null,
                    'designation' => $l->designation ?: null,
                    'unite_vente' => $l->uniteVente ?: null,
                    'famille_code_fichier' => $l->familleCodeFichier,
                    'famille_source' => $familleSource,
                    'etat_ligne' => $l->etatLigne ?: null,
                    'qte_commandee_raw' => $l->qteCommandeeRaw,
                    'qte_livree_raw' => $l->qteLivreeRaw,
                    'qte_facturee_raw' => $l->qteFactureeRaw,
                    'qte_commandee_kg' => $l->qteCommandeeKg,
                    'qte_livree_kg' => $l->qteLivreeKg,
                    'qte_facturee_kg' => $l->qteFactureeKg,
                    'poids_unitaire_kg' => $l->poidsUnitaireKg,
                    'poids_origine' => $l->poidsOrigine,
                    'compte_volume' => $l->compteVolume,
                    'volume_fiable' => $l->volumeFiable,
                    'is_eligible' => $l->isEligible,
                    'is_satisfied' => $l->isSatisfied,
                    'exclusion_reason' => $l->exclusionReason?->value,
                    'is_outlier' => $l->isOutlier,
                    'outlier_reason' => $l->outlierReason,
                    'line_hash' => $this->lineHash($numero, $l),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($lignesBuffer) >= 2000) {
                    CommandeLigne::insert($lignesBuffer);
                    $result->lignesCount += count($lignesBuffer);
                    $lignesBuffer = [];
                }
            }
        }

        if ($lignesBuffer !== []) {
            CommandeLigne::insert($lignesBuffer);
            $result->lignesCount += count($lignesBuffer);
        }
    }

    /** @param array<string, list<NormalizedLine>> $grouped */
    private function recordLineAnomalies(array $grouped, AnomalyRecorder $recorder): void
    {
        foreach ($grouped as $numero => $lines) {
            foreach ($lines as $l) {
                if ($l->isOutlier) {
                    $recorder->add(
                        'outlier_quantite', 'critical',
                        "Quantité aberrante sur {$numero} / {$l->article} : {$l->outlierReason}",
                        "outlier:{$numero}:{$l->article}:{$l->outlierReason}",
                        [
                            'numero' => $numero, 'article' => $l->article,
                            'qte_commandee' => $l->qteCommandeeRaw, 'qte_livree' => $l->qteLivreeRaw,
                            'unite' => $l->uniteVente, 'raison' => $l->outlierReason,
                        ],
                        $l->article,
                    );
                }
                foreach ($l->anomalies as $a) {
                    if (str_starts_with($a, 'categorie_client_inconnue:')) {
                        $code = substr($a, strlen('categorie_client_inconnue:'));
                        $recorder->add(
                            'categorie_client_inconnue', 'warning',
                            "Catégorie client « {$code} » inconnue pour le client {$l->numeroClient} (« {$l->nomClient} »)",
                            "categorie_client_inconnue:{$l->numeroClient}:{$code}",
                            ['client' => $l->numeroClient, 'nom' => $l->nomClient, 'code' => $code],
                        );
                    }
                }
                if ($l->familleInconnue()) {
                    $recorder->add(
                        'famille_inconnue', 'warning',
                        "Famille inconnue pour l'article {$l->article} (« {$l->designation} »)",
                        "famille_inconnue:{$l->article}",
                        ['article' => $l->article, 'designation' => $l->designation],
                        $l->article,
                    );
                }
            }
        }
    }

    private function scopeReconnu(NormalizedLine $l): bool
    {
        return ! in_array('numero_commande_non_reconnu', $l->anomalies, true);
    }

    /**
     * pluck('id', $keyCol) sur `$model` pour une longue liste de valeurs,
     * en découpant le WHERE IN (limites SQL / max_allowed_packet).
     *
     * @param  class-string  $model
     * @param  list<string>  $values
     * @return array<string,int>
     */
    private function idMap(string $model, string $keyCol, array $values): array
    {
        $out = [];
        foreach (array_chunk($values, 5000) as $chunk) {
            $out += $model::whereIn($keyCol, $chunk)->pluck('id', $keyCol)->all();
        }

        return $out;
    }

    private function lineHash(string $numero, NormalizedLine $l): string
    {
        return sha1(implode('|', [
            $numero, $l->article, $l->designation, $l->uniteVente,
            $l->qteCommandeeRaw, $l->qteLivreeRaw, $l->qteFactureeRaw,
            $l->numeroLivraison, $l->numeroFacture,
        ]));
    }
}
