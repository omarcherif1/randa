<?php

namespace App\Domain\Export;

use App\Domain\Access\SocieteScope;
use App\Domain\Reporting\MetricsCache;
use App\Domain\Scope\OrderScope;
use App\Models\Societe;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Page Export (section 4.6) — TOTALEMENT séparée du reste (règle 2.2).
 *
 *   - ne lit QUE les commandes dont le n° commence par EXP ;
 *   - taux de satisfaction propre à l'export (même définition qu'en 2.3,
 *     jamais mélangé avec le local) ;
 *   - AUCUNE règle locale (retard 48 h…) ne s'y applique ;
 *   - respecte le rattachement société (EXPRAN → Randa, EXPSMT → SMT).
 */
final class ExportMetrics
{
    public function forUser(?User $user): array
    {
        return MetricsCache::remember('export', MetricsCache::userScope($user), function () use ($user) {
            $agg = (clone $this->lignes($user))->selectRaw('
                COUNT(DISTINCT l.commande_id) as commandes,
                COUNT(DISTINCT c.client_id) as clients,
                SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as livre_kg,
                SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_commandee_kg ELSE 0 END) as commande_kg
            ')->first();

            return [
                'fraicheur' => MetricsCache::fraicheur(),
                'perimetre' => 'Commandes EXPORT uniquement (préfixe EXP). Aucune règle locale (retard 48 h…) ne s\'applique.',
                'kpi' => [
                    'commandes' => (int) $agg->commandes,
                    'clients' => (int) $agg->clients,
                    'tonnes_exportees' => round(((float) $agg->livre_kg) / 1000, 1),
                    'tonnes_commandees' => round(((float) $agg->commande_kg) / 1000, 1),
                    'taux_satisfaction' => $this->tauxSatisfactionCommandes($user),
                ],
                'satisfaction_par_societe' => $this->satisfactionParSociete($user),
                'top_destinations' => $this->top($user, "COALESCE(NULLIF(c.ville_commande, ''), 'Inconnue')"),
                'top_clients' => $this->topClients($user),
                'top_produits' => $this->topProduits($user),
            ];
        });
    }

    private function lignes(?User $user)
    {
        $q = DB::table('commande_lignes as l')
            ->join('commandes as c', 'c.id', '=', 'l.commande_id')
            ->where('c.scope', OrderScope::Export->value);

        (new SocieteScope)->apply($q, $user, 'c.societe_id');

        return $q;
    }

    /**
     * Règle 2.3 (méthode redéfinie le 2026-09-11, v2) — le taux d'une société
     * est le nombre de commandes satisfaites / le nombre total de commandes.
     * Voir `App\Domain\Dashboard\DashboardMetrics::satisfactionParSociete()`
     * pour la définition, même règle, appliquée ici au périmètre EXPORT.
     */
    private function satisfactionParSociete(?User $user): array
    {
        $rows = (clone $this->lignes($user))
            ->select('c.societe_id', 'c.id as commande_id',
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_commandee_kg ELSE 0 END) as cmd'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as liv'))
            ->groupBy('c.societe_id', 'c.id')
            ->having('cmd', '>', 0)
            ->get()
            ->groupBy('societe_id');

        return Societe::orderBy('id')->get(['id', 'code', 'nom', 'couleur'])->map(function ($soc) use ($rows) {
            $commandes = $rows->get($soc->id, collect());
            $satisfaites = $commandes->filter(fn ($r) => $this->commandeSatisfaite((float) $r->cmd, (float) $r->liv))->count();

            return [
                'code' => $soc->code, 'nom' => $soc->nom, 'couleur' => $soc->couleur,
                'commandes' => $commandes->count(),
                'satisfaites' => $satisfaites,
                'taux' => $commandes->count() > 0 ? round($satisfaites / $commandes->count(), 4) : null,
            ];
        })->all();
    }

    /** Voir `App\Domain\Dashboard\DashboardMetrics::tauxSatisfactionCommandes()`. */
    private function tauxSatisfactionCommandes(?User $user): ?float
    {
        $rows = (clone $this->lignes($user))
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

    private function top(?User $user, string $labelExpr): array
    {
        return (clone $this->lignes($user))
            ->select(
                DB::raw($labelExpr.' as label'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as kg'),
            )
            ->groupBy('label')->orderByDesc('kg')->limit(10)->get()
            ->map(fn ($r) => ['label' => $r->label, 'tonnes' => round($r->kg / 1000, 1)])->all();
    }

    private function topProduits(?User $user): array
    {
        return (clone $this->lignes($user))
            ->select('l.article', DB::raw('MAX(l.designation) as designation'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as kg'))
            ->groupBy('l.article')->orderByDesc('kg')->limit(10)->get()
            ->map(fn ($r) => ['label' => $r->designation ?: $r->article, 'tonnes' => round($r->kg / 1000, 1)])->all();
    }

    private function topClients(?User $user): array
    {
        return (clone $this->lignes($user))
            ->leftJoin('clients as cl', 'cl.id', '=', 'c.client_id')
            ->select(DB::raw("COALESCE(cl.numero, 'inconnu') as num"), DB::raw('MAX(cl.nom) as nom'),
                DB::raw('SUM(CASE WHEN l.compte_volume = 1 THEN l.qte_livree_kg ELSE 0 END) as kg'))
            ->groupBy('num')->orderByDesc('kg')->limit(10)->get()
            ->map(fn ($r) => ['numero' => $r->num, 'nom' => $r->nom, 'tonnes' => round($r->kg / 1000, 1)])->all();
    }
}
