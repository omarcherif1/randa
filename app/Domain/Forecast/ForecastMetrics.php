<?php

namespace App\Domain\Forecast;

use App\Domain\Access\SocieteScope;
use App\Domain\Reporting\MetricsCache;
use App\Domain\Reporting\Sql;
use App\Domain\Scope\OrderScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Page Prévisions (section 4.5) — prévision de la demande par produit.
 *
 *   - apprend sur la quantité COMMANDÉE (jamais livrée) — règle 4.5 ;
 *   - LOCAL uniquement ; ne prévoit que les produits dont le poids en kg est
 *     déterminé (`compte_volume`) — les autres sont listés à part, sans prévision ;
 *   - chaque produit reçoit son palier de fiabilité + un backtest walk-forward ;
 *   - résultat mis en cache (mécanisme unique de l'app — `MetricsCache`).
 */
final class ForecastMetrics
{
    public function __construct(
        private readonly ForecastEngine $engine = new ForecastEngine,
        private readonly ForecastBacktest $backtest = new ForecastBacktest,
    ) {}

    public function forFilter(ForecastFilter $filter): array
    {
        return MetricsCache::remember('previsions', $filter->cacheKey(), function () use ($filter) {
            $series = $this->seriesParArticle($filter);
            $sansPoids = $this->articlesSansPoids($filter);
            $moisFin = CarbonImmutable::now()->format('Y').'-12';
            $ml = $this->predictionsMl($series, $moisFin);
            $methode = $ml !== null ? 'ml' : 'calcul';

            $produits = [];
            $globalAbs = [];
            $globalPct = [];
            $parMois = [];

            foreach ($series as $article => $info) {
                // PHP transforme silencieusement une clé de tableau qui
                // "ressemble" à un entier (ex. "112231") en vrai int — un
                // code article purement numérique en a besoin en chaîne
                // partout où il ressort de cette boucle (JSON envoyé au
                // service ML, notamment, qui le refuse sinon).
                $article = (string) $article;
                $prev = $this->engine->prevoir($info['serie']);
                $bt = $this->backtest->evaluer($info['serie']);

                $prevArray = $prev->toArray();
                $prevArray['methode'] = 'calcul';

                if ($ml !== null && ($ml['produits'][$article] ?? []) !== []) {
                    $premiere = $ml['produits'][$article][0];
                    $prevArray['mois_cible'] = $premiere['mois_cible'];
                    $prevArray['quantite_prevue_kg'] = $premiere['quantite_prevue_kg'];
                    $prevArray['methode'] = 'ml';
                    $previsionsAnnee = $ml['produits'][$article];
                } else {
                    $previsionsAnnee = array_map(
                        fn ($p) => $p->toArray(),
                        $this->engine->prevoirJusqua($info['serie'], $moisFin),
                    );
                }

                foreach ($previsionsAnnee as $p) {
                    $parMois[$p['mois_cible']] = ($parMois[$p['mois_cible']] ?? 0) + ($p['quantite_prevue_kg'] ?? 0);
                }

                $produits[] = [
                    'article' => $article,
                    'designation' => $info['designation'],
                    'historique' => $this->serieAffichable($info['serie']),
                    'prevision' => $prevArray,
                    'previsions_annee' => $previsionsAnnee,
                    'total_prevu_annee_kg' => round(array_sum(array_map(fn ($p) => $p['quantite_prevue_kg'] ?? 0, $previsionsAnnee)), 1),
                    'backtest' => [
                        'n_points' => $bt['n_points'],
                        'mae_kg' => $bt['mae_kg'],
                        'mape' => $bt['mape'],
                        'biais_kg' => $bt['biais_kg'],
                        // fiabilité RÉELLE observée (précision passée), distincte du palier (longueur d'historique)
                        'fiabilite_reelle' => $this->fiabiliteReelle($bt['mape']),
                        'points' => $bt['points'],
                    ],
                ];

                if ($bt['mae_kg'] !== null) {
                    $globalAbs[] = $bt['mae_kg'];
                }
                if ($bt['mape'] !== null) {
                    $globalPct[] = $bt['mape'];
                }
            }

            // tri : prévision décroissante (produits qui pèsent le plus d'abord)
            usort($produits, fn ($a, $b) => ($b['prevision']['quantite_prevue_kg'] ?? 0) <=> ($a['prevision']['quantite_prevue_kg'] ?? 0));

            ksort($parMois);

            return [
                'fraicheur' => MetricsCache::fraicheur(),
                'filtres' => $filter->toArray(),
                'methode' => $methode,
                'modele_ml' => $ml['modele'] ?? null,
                'produits' => $produits,
                'mois_fin_horizon' => $moisFin,
                'total_prevu_kg' => round(array_sum(array_map(fn ($p) => $p['prevision']['quantite_prevue_kg'] ?? 0, $produits)), 1),
                'total_prevu_annee_kg' => round(array_sum(array_map(fn ($p) => $p['total_prevu_annee_kg'], $produits)), 1),
                'total_prevu_par_mois' => array_map(
                    fn ($mois, $kg) => ['mois' => $mois, 'kg' => round($kg, 1)],
                    array_keys($parMois), array_values($parMois),
                ),
                'repartition_fiabilite' => $this->compterFiabilite($produits),
                'backtest_global' => [
                    'mae_moyen_kg' => $globalAbs !== [] ? round(array_sum($globalAbs) / count($globalAbs), 1) : null,
                    'mape_moyen' => $globalPct !== [] ? round(array_sum($globalPct) / count($globalPct), 4) : null,
                    'produits_testes' => count($globalPct),
                ],
                'sans_poids' => $sansPoids,
                'notes' => [
                    'base' => 'Le modèle apprend sur la quantité COMMANDÉE (le besoin exprimé), jamais sur la quantité livrée.',
                    'palier' => "Le palier (« faible / moyenne / haute ») dépend de la LONGUEUR d'historique disponible — ce n'est pas une garantie de précision. La précision réelle est le backtest ci-dessous.",
                    'saisonnalite' => "Ajustement saisonnier appliqué seulement quand un « même mois l'an dernier » existe (historique >= 12 mois).",
                    'horizon' => "La prévision détaillée porte sur le mois suivant ; la courbe et le total « jusqu'à décembre » projettent chaque mois restant de l'année.",
                    'methode' => $methode === 'ml'
                        ? 'Prévisions produites par le modèle ML (Random Forest global, entraîné sur tous les produits). Repli automatique sur le calcul si le service est indisponible.'
                        : 'Modèle ML indisponible ou pas encore entraîné — repli sur le calcul (moyenne/tendance/saisonnalité). Un administrateur peut entraîner le modèle depuis cette page.',
                    'v3' => "Page conçue pour recevoir en V3 l'estimation des quantités à produire et l'aide à la planification (lignes de production).",
                ],
            ];
        });
    }

    /**
     * Données d'entraînement pour le service ML (bouton Administration de la
     * page Prévisions) — même source que les prédictions ({@see seriesParArticle}),
     * aplatie en lignes (article, mois, quantité) : un seul endroit qui décide
     * ce qu'est "l'historique local" pour ce produit.
     *
     * @return list<array{article:string,designation:string,famille:?string,societe:?string,mois:string,qte_commandee_kg:float}>
     */
    public function lignesPourEntrainement(ForecastFilter $filter): array
    {
        $lignes = [];
        foreach ($this->seriesParArticle($filter) as $article => $info) {
            $article = (string) $article; // voir le commentaire équivalent dans forFilter()
            foreach ($info['serie'] as $mois => $kg) {
                $lignes[] = [
                    'article' => $article,
                    'designation' => $info['designation'],
                    'famille' => $info['famille'],
                    'societe' => $info['societe'],
                    'mois' => $mois,
                    'qte_commandee_kg' => $kg,
                ];
            }
        }

        return $lignes;
    }

    /**
     * Prévisions du CALCUL PHP uniquement, jamais le modèle ML — utilisé par
     * {@see ForecastSnapshotWriter} : une photo historique doit rester
     * comparable dans le temps (règle 4.5), donc toujours produite par la
     * même méthode déterministe, indépendamment de si le service ML est
     * disponible/entraîné au moment de l'import (ça changerait silencieusement
     * la définition d'une photo à l'autre sinon). Ne fait AUCUN appel HTTP.
     *
     * @return array<string, array{designation:string, prevision:ForecastResult}>
     */
    public function previsionsCalculSeules(ForecastFilter $filter): array
    {
        $out = [];
        foreach ($this->seriesParArticle($filter) as $article => $info) {
            $out[(string) $article] = [
                'designation' => $info['designation'],
                'prevision' => $this->engine->prevoir($info['serie']),
            ];
        }

        return $out;
    }

    /**
     * Interroge le service ML (voir {@see MlForecastClient}) — absorbe
     * toute indisponibilité en repli silencieux vers `null` (jamais
     * d'exception qui casserait la page : le calcul PHP prend alors le relais).
     *
     * @param  array<string, array{designation:string, famille:?string, societe:?string, serie:array<string,float>}>  $series
     */
    private function predictionsMl(array $series, string $moisFin): ?array
    {
        $client = MlForecastClient::fromConfig();
        $statut = $client->statut();
        if ($statut === null || ! ($statut['entraine'] ?? false)) {
            return null;
        }

        $lignes = [];
        foreach ($series as $article => $info) {
            $article = (string) $article; // voir le commentaire équivalent dans forFilter()
            foreach ($info['serie'] as $mois => $kg) {
                $lignes[] = [
                    'article' => $article,
                    'designation' => $info['designation'],
                    'famille' => $info['famille'],
                    'societe' => $info['societe'],
                    'mois' => $mois,
                    'qte_commandee_kg' => $kg,
                ];
            }
        }

        $resultat = $client->predire($lignes, $moisFin);
        if ($resultat === null) {
            return null;
        }

        $parArticle = [];
        foreach ($resultat['produits'] as $p) {
            $parArticle[$p['article']] = array_map(fn ($m) => [
                'mois_cible' => $m['mois'],
                'quantite_prevue_kg' => $m['quantite_prevue_kg'],
            ], $p['previsions']);
        }

        return [
            'produits' => $parArticle,
            'modele' => [
                'entraine_le' => $statut['entraine_le'],
                'n_echantillons' => $statut['n_echantillons'],
                'n_produits' => $statut['n_produits'],
                'mae_kg' => $statut['mae_kg'],
                'wape' => $statut['wape'],
                'r2' => $statut['r2'],
            ],
        ];
    }

    /** @return array<string, array{designation:string, famille:?string, societe:?string, serie:array<string,float>}> */
    private function seriesParArticle(ForecastFilter $filter): array
    {
        $rows = $this->base($filter)
            ->where('l.compte_volume', true)
            ->leftJoin('familles as fam', 'fam.id', '=', 'l.famille_id')
            ->leftJoin('societes as soc', 'soc.id', '=', 'c.societe_id')
            ->select(
                'l.article',
                DB::raw('MAX(l.designation) as designation'),
                DB::raw('MAX(fam.code) as famille'),
                DB::raw('MAX(soc.code) as societe'),
                DB::raw(Sql::monthExpr('c.date_commande').' as mois'),
                DB::raw('SUM(l.qte_commandee_kg) as kg'),
            )
            ->groupBy('l.article', DB::raw(Sql::monthExpr('c.date_commande')))
            ->orderBy('l.article')->orderBy('mois')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->article] ??= [
                'designation' => $r->designation,
                'famille' => $r->famille,
                'societe' => $r->societe,
                'serie' => [],
            ];
            $out[$r->article]['serie'][$r->mois] = (float) $r->kg;
        }

        return $out;
    }

    /** @return array<int, array{article:string, designation:string}> Produits sans poids kg déterminé (aucune prévision possible). */
    private function articlesSansPoids(ForecastFilter $filter): array
    {
        return $this->base($filter)
            ->select('l.article', DB::raw('MAX(l.designation) as designation'))
            ->groupBy('l.article')
            ->havingRaw('MAX(l.compte_volume) = 0')
            ->orderBy('l.article')->get()
            ->map(fn ($r) => ['article' => $r->article, 'designation' => $r->designation])->all();
    }

    private function base(ForecastFilter $filter)
    {
        $q = DB::table('commande_lignes as l')
            ->join('commandes as c', 'c.id', '=', 'l.commande_id')
            ->where('c.scope', OrderScope::Local->value);

        // Filtrage société : par le rattachement de l'utilisateur (web) ; en appel
        // système (writer de snapshots, CLI) il n'y a pas d'utilisateur — on se fie
        // alors au seul `societeCode` explicite.
        if ($filter->user !== null) {
            (new SocieteScope)->apply($q, $filter->user, 'c.societe_id');
        }

        if ($filter->societeCode !== null) {
            $q->whereIn('c.societe_id', fn ($s) => $s->select('id')->from('societes')->where('code', $filter->societeCode));
        }
        if ($filter->familles !== []) {
            $q->whereIn('l.famille_id', fn ($f) => $f->select('id')->from('familles')->whereIn('code', $filter->familles));
        }

        return $q;
    }

    /** @param array<string,float> $serie @return list<array{mois:string, kg:float}> */
    private function serieAffichable(array $serie): array
    {
        ksort($serie);
        $out = [];
        foreach ($serie as $mois => $kg) {
            $out[] = ['mois' => $mois, 'kg' => round($kg, 1)];
        }

        return $out;
    }

    private function compterFiabilite(array $produits): array
    {
        $c = ['haute' => 0, 'moyenne' => 0, 'faible' => 0, 'insuffisante' => 0];
        foreach ($produits as $p) {
            $c[$p['prevision']['fiabilite']]++;
        }

        return $c;
    }

    /** Fiabilité RÉELLE observée sur le backtest (erreur moyenne en %). */
    private function fiabiliteReelle(?float $mape): string
    {
        if ($mape === null) {
            return 'indeterminee';
        }

        return match (true) {
            $mape <= 0.15 => 'bonne',
            $mape <= 0.40 => 'moyenne',
            default => 'faible',
        };
    }
}
