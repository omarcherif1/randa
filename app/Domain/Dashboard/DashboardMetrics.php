<?php

namespace App\Domain\Dashboard;

use App\Domain\Access\SocieteScope;
use App\Domain\Metrics\DelayCalculator;
use App\Domain\Metrics\DelayVerdict;
use App\Domain\Reporting\MetricsCache;
use App\Domain\Scope\OrderScope;
use App\Models\Setting;
use App\Models\Societe;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Moteur d'agrégats du tableau de bord principal (section 4.2).
 *
 *   - EXCLUSIVEMENT local (règle 2.2 — aucune donnée export) ;
 *   - part UNIQUEMENT des lignes déjà normalisées (Étapes 1/2) : `is_eligible`,
 *     `is_satisfied`, `compte_volume`, `qte_*_kg` portent déjà toutes les règles
 *     de la section 2 — on ne recalcule rien ici ;
 *   - respecte le rattachement société de l'utilisateur (règle 3.1) ;
 *   - résultat mis en cache par (filtres × version des données) — jamais
 *     recalculé à chaque affichage (exigence 7).
 */
final class DashboardMetrics
{
    /** @var array<string, array> mémo des ruptures par empreinte de filtre (calcul lourd) */
    private array $rupturesMemo = [];

    public function forFilter(DashboardFilter $filter): array
    {
        return MetricsCache::remember('dashboard', $filter->cacheKey(), fn () => [
            'filtres' => $filter->toArray(),
            'fraicheur' => MetricsCache::fraicheur(),
            'kpi' => $this->kpi($filter),
            'satisfaction' => $this->satisfactionParSociete($filter),
            'evolution' => $this->evolution($filter),
            'repartitions' => $this->repartitions($filter),
            'classements' => $this->classements($filter),
            'commande_vs_livre' => $this->commandeVsLivre($filter),
            'ruptures' => $this->ruptures($filter),
            'causes_non_satisfaction' => $this->causesNonSatisfaction($filter),
            'indisponibles' => [
                'retards' => "Aucune date d'expédition fiable dans la source — « Commandes en retard » "
                    .'utilise donc un proxy (date de facture, cf. 2.4), comme la page Livraisons.',
                'carte_livraison' => "La ville affichée est celle de la COMMANDE, pas d'une vraie ville de livraison (cf. 4.3).",
            ],
        ]);
    }

    /** Expression SQL « mois » portable (MySQL / SQLite pour les tests). */
    private function moisExpr(string $colonne = 'c.date_commande'): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$colonne})"
            : "DATE_FORMAT({$colonne}, '%Y-%m')";
    }

    /** Base : lignes locales, filtrées, visibles par l'utilisateur. */
    private function lignes(DashboardFilter $filter): QueryBuilder
    {
        $q = DB::table('commande_lignes as l')
            ->join('commandes as c', 'c.id', '=', 'l.commande_id')
            ->where('c.scope', OrderScope::Local->value);

        (new SocieteScope)->apply($q, $filter->user, 'c.societe_id');

        if ($filter->societes !== []) {
            $q->whereIn('c.societe_id', fn ($sub) => $sub->select('id')->from('societes')->whereIn('code', $filter->societes));
        }
        if ($filter->articles !== []) {
            $q->whereIn('l.article', $filter->articles);
        }
        if ($filter->familles !== []) {
            $q->whereIn('l.famille_id', fn ($sub) => $sub->select('id')->from('familles')->whereIn('code', $filter->familles));
        }
        if ($filter->clientCategories !== []) {
            $q->whereIn('c.client_id', fn ($sub) => $sub->select('cl.id')->from('clients as cl')
                ->join('client_categories as cc', 'cc.id', '=', 'cl.client_category_id')
                ->whereIn('cc.code', $filter->clientCategories));
        }
        if ($filter->from !== null) {
            $q->whereDate('c.date_commande', '>=', $filter->from->toDateString());
        }
        if ($filter->to !== null) {
            $q->whereDate('c.date_commande', '<=', $filter->to->toDateString());
        }

        return $q;
    }

    /**
     * Règle 2.3 (méthode redéfinie le 2026-09-11, v2 — décision produit) —
     * remplace le pooled ratio ET la moyenne des taux de ligne par commande
     * (v1, même jour) par une règle plus simple, au niveau de la COMMANDE :
     *   - commande NON satisfaite si sa quantité commandée (kg) > sa quantité
     *     livrée (kg) ;
     *   - commande satisfaite sinon (livrée >= commandée, tolérance 0,0001 kg
     *     pour l'arrondi flottant — la livraison peut aussi dépasser la
     *     commande, ça reste satisfait, jamais pénalisé) ;
     *   - taux = nombre de commandes satisfaites / nombre total de commandes
     *     (tous filtres appliqués tels quels, y compris société — à la
     *     différence de `satisfactionParSociete()` qui l'ignore volontairement).
     * Une commande sans aucune quantité commandée mesurable (compte_volume)
     * n'entre ni au numérateur ni au dénominateur — jamais de faux 0/100 %.
     */
    private function tauxSatisfactionCommandes(DashboardFilter $filter): ?float
    {
        $rows = (clone $this->lignes($filter))
            ->select('c.id as commande_id',
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_commandee_kg ELSE 0 END) as cmd'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as liv'))
            ->groupBy('c.id')
            ->having('cmd', '>', 0)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $satisfaites = $rows->filter(fn ($r) => $this->commandeSatisfaite((float) $r->cmd, (float) $r->liv))->count();

        return round($satisfaites / $rows->count(), 4);
    }

    /** Voir {@see self::tauxSatisfactionCommandes()} — décision unitaire, un seul endroit. */
    private function commandeSatisfaite(float $commandeeKg, float $livreeKg): bool
    {
        return $livreeKg + 0.0001 >= $commandeeKg;
    }

    private function kpi(DashboardFilter $filter): array
    {
        $l = (clone $this->lignes($filter))->selectRaw('
            COUNT(*) as lignes,
            COUNT(DISTINCT l.commande_id) as commandes,
            COUNT(DISTINCT c.client_id) as clients_actifs,
            COUNT(DISTINCT NULLIF(c.ville_commande, \'\')) as villes,
            SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_commandee_kg ELSE 0 END) as qte_commandee_kg,
            SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as qte_livree_kg,
            SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_facturee_kg ELSE 0 END) as qte_facturee_kg,
            SUM(CASE WHEN l.compte_volume = 1 AND l.qte_commandee_kg > l.qte_livree_kg
                THEN l.qte_commandee_kg - l.qte_livree_kg ELSE 0 END) as qte_non_livree_kg,
            SUM(CASE WHEN l.compte_volume = 0 THEN 1 ELSE 0 END) as lignes_hors_tonnage
        ')->first();

        $etats = (clone $this->lignes($filter))
            ->select('c.etat', DB::raw('COUNT(DISTINCT c.id) as n'))
            ->groupBy('c.etat')->pluck('n', 'etat');

        $livrees = (clone $this->lignes($filter))->whereNotNull('c.numero_livraison')->distinct()->count('c.id');
        $facturees = (clone $this->lignes($filter))->whereNotNull('c.numero_facture')->distinct()->count('c.id');

        $rupt = $this->ruptures($filter);
        $retard = $this->commandesEnRetard($filter);

        return [
            'taux_satisfaction_volume' => $this->tauxSatisfactionCommandes($filter),
            'commandes' => [
                'total' => (int) $l->commandes,
                'ouvertes' => (int) (($etats['saisie'] ?? 0) + ($etats['reservee'] ?? 0)),
                'livrees' => $livrees,
                'facturees' => $facturees,
                'cloturees' => (int) ($etats['cloturee'] ?? 0),
            ],
            'clients_actifs' => (int) $l->clients_actifs,
            'villes' => (int) $l->villes,
            'tonnes_distribuees' => round(((float) $l->qte_livree_kg) / 1000, 1),
            'qte_commandee_kg' => round((float) $l->qte_commandee_kg, 1),
            'qte_livree_kg' => round((float) $l->qte_livree_kg, 1),
            'qte_facturee_kg' => round((float) $l->qte_facturee_kg, 1),
            'qte_non_livree_kg' => round((float) $l->qte_non_livree_kg, 1),
            'lignes_hors_tonnage' => (int) $l->lignes_hors_tonnage,
            'produits_en_rupture' => $rupt['produits_en_rupture'],
            'taux_rupture' => $rupt['taux_rupture'],
            'commandes_en_retard' => $retard['en_retard'],
            'commandes_retard_indisponible' => $retard['donnee_indisponible'],
            'seuil_retard_jours' => $retard['seuil_jours'],
        ];
    }

    /**
     * Règle 2.4 — même définition de retard que la page Livraisons (proxy
     * date de facture, cf. {@see DelayCalculator}), mais
     * appliquée ici au périmètre FILTRÉ du tableau de bord (client, produit,
     * famille, société, période) plutôt qu'à la totalité des commandes locales.
     * Une commande pas encore facturée reste « donnée indisponible », jamais
     * un faux 0.
     */
    private function commandesEnRetard(DashboardFilter $filter): array
    {
        $delay = DelayCalculator::fromSettings();

        $verdicts = (clone $this->lignes($filter))
            ->select('c.id', 'c.date_commande', 'c.date_facture')
            ->distinct()
            ->get()
            ->map(fn ($c) => $delay->evaluate(
                OrderScope::Local,
                $c->date_commande ? Carbon::parse($c->date_commande) : null,
                $c->date_facture ? Carbon::parse($c->date_facture) : null,
            ));

        return [
            'en_retard' => $verdicts->filter(fn ($v) => $v->isLate())->count(),
            'donnee_indisponible' => $verdicts->filter(fn ($v) => $v->status === DelayVerdict::DONNEE_INDISPONIBLE)->count(),
            'seuil_jours' => $delay->seuilJours(),
        ];
    }

    /**
     * Règle 2.3 (méthode redéfinie le 2026-09-11, v2 — décision produit) —
     * toujours les 3 sociétés, respecte tous les filtres SAUF société. Le
     * taux d'une société = nombre de commandes satisfaites / nombre total de
     * commandes de cette société. Voir {@see self::tauxSatisfactionCommandes()}
     * pour la définition d'une commande satisfaite — même règle, un seul endroit.
     */
    private function satisfactionParSociete(DashboardFilter $filter): array
    {
        $base = $filter->sansSociete();

        $parCommande = (clone $this->lignes($base))
            ->select('c.societe_id', 'c.id as commande_id',
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_commandee_kg ELSE 0 END) as cmd'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as liv'))
            ->groupBy('c.societe_id', 'c.id')
            ->having('cmd', '>', 0)
            ->get();

        $parCommandeMois = (clone $this->lignes($base))
            ->select('c.societe_id', 'c.id as commande_id', DB::raw($this->moisExpr().' as mois'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_commandee_kg ELSE 0 END) as cmd'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as liv'))
            ->groupBy('c.societe_id', 'c.id', DB::raw($this->moisExpr()))
            ->having('cmd', '>', 0)
            ->orderBy('mois')
            ->get();

        $societes = Societe::orderBy('id')->get(['id', 'code', 'nom', 'couleur']);
        $bySoc = $parCommande->groupBy('societe_id');

        $parSociete = [];
        foreach ($societes as $soc) {
            $rows = $bySoc->get($soc->id, collect());
            $satisfaites = $rows->filter(fn ($r) => $this->commandeSatisfaite((float) $r->cmd, (float) $r->liv))->count();
            $parSociete[] = [
                'code' => $soc->code,
                'nom' => $soc->nom,
                'couleur' => $soc->couleur,
                'commandes' => $rows->count(),
                'satisfaites' => $satisfaites,
                'taux' => $rows->count() > 0 ? round($satisfaites / $rows->count(), 4) : null,
            ];
        }

        $parMoisSoc = [];
        foreach ($parCommandeMois as $row) {
            $parMoisSoc[$row->mois][$row->societe_id][] = $this->commandeSatisfaite((float) $row->cmd, (float) $row->liv);
        }
        $courbe = [];
        foreach ($parMoisSoc as $mois => $parSoc) {
            $courbe[$mois] = ['mois' => $mois];
            foreach ($societes as $soc) {
                $verdicts = collect($parSoc[$soc->id] ?? []);
                $courbe[$mois][$soc->code] = $verdicts->count() > 0
                    ? round($verdicts->filter()->count() / $verdicts->count(), 4)
                    : null;
            }
        }
        ksort($courbe);

        return [
            'par_societe' => $parSociete,
            'courbe' => array_values($courbe),
        ];
    }

    private function evolution(DashboardFilter $filter): array
    {
        $rows = (clone $this->lignes($filter))
            ->select(
                DB::raw($this->moisExpr().' as mois'),
                DB::raw('COUNT(DISTINCT l.commande_id) as commandes'),
                DB::raw('COUNT(DISTINCT CASE WHEN c.numero_facture IS NOT NULL THEN c.id END) as facturees'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as livre_kg'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_commandee_kg ELSE 0 END) as commande_kg'),
            )
            ->groupBy(DB::raw($this->moisExpr()))->orderBy('mois')->get();

        return $rows->map(fn ($r) => [
            'mois' => $r->mois,
            'commandes' => (int) $r->commandes,
            'facturees' => (int) $r->facturees,
            'tonnes_livrees' => round($r->livre_kg / 1000, 1),
            'tonnes_commandees' => round($r->commande_kg / 1000, 1),
        ])->all();
    }

    private function repartitions(DashboardFilter $filter): array
    {
        $parSociete = (clone $this->lignes($filter))
            ->join('societes as s', 's.id', '=', 'c.societe_id')
            ->select('s.code', 's.nom', 's.couleur',
                DB::raw('COUNT(DISTINCT l.commande_id) as commandes'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as livre_kg'))
            ->groupBy('s.code', 's.nom', 's.couleur')->get()
            ->map(fn ($r) => ['label' => $r->nom, 'code' => $r->code, 'couleur' => $r->couleur,
                'commandes' => (int) $r->commandes, 'tonnes' => round($r->livre_kg / 1000, 1)])->all();

        $parTypeClient = (clone $this->lignes($filter))
            ->leftJoin('clients as cl', 'cl.id', '=', 'c.client_id')
            ->leftJoin('client_categories as cc', 'cc.id', '=', 'cl.client_category_id')
            ->select(DB::raw("COALESCE(cc.intitule, 'Indéfini') as label"),
                DB::raw('COUNT(DISTINCT l.commande_id) as commandes'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as livre_kg'))
            ->groupBy('label')->orderByDesc('livre_kg')->get()
            ->map(fn ($r) => ['label' => $r->label, 'commandes' => (int) $r->commandes,
                'tonnes' => round($r->livre_kg / 1000, 1)])->all();

        $parVille = (clone $this->lignes($filter))
            ->select(DB::raw("COALESCE(NULLIF(c.ville_commande, ''), 'Inconnue') as label"),
                DB::raw('COUNT(DISTINCT l.commande_id) as commandes'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as livre_kg'))
            ->groupBy('label')->orderByDesc('livre_kg')->limit(15)->get()
            ->map(fn ($r) => ['label' => $r->label, 'commandes' => (int) $r->commandes,
                'tonnes' => round($r->livre_kg / 1000, 1)])->all();

        return ['par_societe' => $parSociete, 'par_type_client' => $parTypeClient, 'par_ville_commande' => $parVille];
    }

    private function classements(DashboardFilter $filter): array
    {
        $topProduits = (clone $this->lignes($filter))
            ->select('l.article', DB::raw('MAX(l.designation) as designation'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_commandee_kg ELSE 0 END) as kg'))
            ->groupBy('l.article')->orderByDesc('kg')->limit(10)->get()
            ->map(fn ($r) => ['article' => $r->article, 'designation' => $r->designation, 'tonnes' => round($r->kg / 1000, 1)])->all();

        $topRupture = (clone $this->lignes($filter))
            ->select('l.article', DB::raw('MAX(l.designation) as designation'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 AND l.qte_commandee_kg > l.qte_livree_kg
                    THEN l.qte_commandee_kg - l.qte_livree_kg ELSE 0 END) as manque_kg'))
            ->groupBy('l.article')->having('manque_kg', '>', 0)->orderByDesc('manque_kg')->limit(10)->get()
            ->map(fn ($r) => ['article' => $r->article, 'designation' => $r->designation, 'tonnes_manquantes' => round($r->manque_kg / 1000, 1)])->all();

        $topClients = (clone $this->lignes($filter))
            ->leftJoin('clients as cl', 'cl.id', '=', 'c.client_id')
            ->select(DB::raw("COALESCE(cl.numero, 'inconnu') as client_numero"), DB::raw('MAX(cl.nom) as client_nom'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as kg'))
            ->groupBy(DB::raw("COALESCE(cl.numero, 'inconnu')"))->orderByDesc('kg')->limit(10)->get()
            ->map(fn ($r) => ['numero' => $r->client_numero, 'nom' => $r->client_nom, 'tonnes' => round($r->kg / 1000, 1)])->all();

        $topVilles = (clone $this->lignes($filter))
            ->select(DB::raw("COALESCE(NULLIF(c.ville_commande, ''), 'Inconnue') as label"),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as kg'))
            ->groupBy('label')->orderByDesc('kg')->limit(10)->get()
            ->map(fn ($r) => ['label' => $r->label, 'tonnes' => round($r->kg / 1000, 1)])->all();

        return [
            'top_produits_commandes' => $topProduits,
            'top_produits_rupture' => $topRupture,
            'top_clients' => $topClients,
            'top_regions' => $topVilles,
        ];
    }

    private function commandeVsLivre(DashboardFilter $filter): array
    {
        $rows = (clone $this->lignes($filter))
            ->join('familles as f', 'f.id', '=', 'l.famille_id')
            ->select('f.intitule_court as label',
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_commandee_kg ELSE 0 END) as cmd_kg'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as liv_kg'))
            ->groupBy('f.intitule_court')->orderByDesc('cmd_kg')->limit(12)->get();

        return $rows->map(fn ($r) => [
            'label' => $r->label,
            'commande' => round($r->cmd_kg / 1000, 1),
            'livre' => round($r->liv_kg / 1000, 1),
        ])->all();
    }

    /** Règle 2.5 méthode B — déduction depuis les commandes ouvertes (pas de fichier stock). */
    private function ruptures(DashboardFilter $filter): array
    {
        $cle = $filter->cacheKey();
        if (isset($this->rupturesMemo[$cle])) {
            return $this->rupturesMemo[$cle];
        }

        $seuil = (float) Setting::get('seuil_rupture_pct', 0.5);

        $rows = (clone $this->lignes($filter))
            ->where('c.etat', '!=', 'cloturee')
            ->select('l.article', DB::raw('MAX(l.designation) as designation'),
                DB::raw('SUM(l.qte_commandee_raw) as cmd'), DB::raw('SUM(l.qte_livree_raw) as liv'))
            ->groupBy('l.article')->having('cmd', '>', 0)->get();

        $enRupture = [];
        foreach ($rows as $r) {
            $taux = $r->cmd > 0 ? $r->liv / $r->cmd : 1;
            if ($taux < $seuil) {
                $enRupture[] = ['article' => $r->article, 'designation' => $r->designation, 'taux_couverture' => round($taux, 3)];
            }
        }

        $total = $rows->count();

        return $this->rupturesMemo[$cle] = [
            'methode' => 'B — déduction depuis les commandes ouvertes (aucun fichier de stock)',
            'seuil' => $seuil,
            'produits_ouverts' => $total,
            'produits_en_rupture' => count($enRupture),
            'taux_rupture' => $total > 0 ? round(count($enRupture) / $total, 4) : null,
            'liste' => array_slice($enRupture, 0, 20),
        ];
    }

    private function causesNonSatisfaction(DashboardFilter $filter): array
    {
        $row = (clone $this->lignes($filter))
            ->where('l.is_eligible', 1)->where('l.is_satisfied', 0)
            ->selectRaw('
                SUM(CASE WHEN l.qte_livree_raw = 0 THEN 1 ELSE 0 END) as non_livree,
                SUM(CASE WHEN l.qte_livree_raw > 0 THEN 1 ELSE 0 END) as partielle,
                COUNT(*) as total
            ')->first();

        return [
            'note' => "Diagramme de Pareto complet dès qu'une 2e cause que la rupture sera intégrée (retard, panne…). Ici : ventilation des lignes non satisfaites.",
            'total' => (int) ($row->total ?? 0),
            'causes' => [
                ['cause' => 'Rien livré', 'lignes' => (int) ($row->non_livree ?? 0)],
                ['cause' => 'Livraison partielle', 'lignes' => (int) ($row->partielle ?? 0)],
            ],
        ];
    }
}
