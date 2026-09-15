<?php

namespace App\Domain\Stock;

use App\Domain\Access\SocieteScope;
use App\Domain\Metrics\StockCoverageCalculator;
use App\Domain\Reporting\MetricsCache;
use App\Domain\Scope\OrderScope;
use App\Models\Setting;
use App\Models\StockSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Page Stock (section 4.4) — statut par produit (rupture / alerte / normal)
 * selon la MÉTHODE ACTIVE (règle 2.5), clairement affichée.
 *
 *   - Méthode B (défaut) : déduction depuis les commandes ouvertes ;
 *   - Méthode A : depuis le fichier de stock journalier (`stock_snapshots`),
 *     si un tel fichier est importé et que `stock_methode_active` = 'A'.
 *
 * Module « priorité basse » (2.5) : implémentation volontairement simple,
 * toute la règle passe par `StockCoverageCalculator` (un seul endroit).
 */
final class StockMetrics
{
    public function forUser(?User $user): array
    {
        return MetricsCache::remember('stock', MetricsCache::userScope($user), function () use ($user) {
            $methode = Setting::get('stock_methode_active', 'B') === 'A' && StockSnapshot::exists() ? 'A' : 'B';
            $calc = StockCoverageCalculator::fromSettings();

            $produits = $methode === 'A'
                ? $this->methodeA($calc)
                : $this->methodeB($calc, $user);

            $compte = ['rupture' => 0, 'alerte' => 0, 'normal' => 0, 'indetermine' => 0];
            foreach ($produits as $p) {
                $compte[$p['statut']]++;
            }

            return [
                'fraicheur' => MetricsCache::fraicheur(),
                'methode' => $methode,
                'methode_libelle' => $methode === 'A'
                    ? 'A — fichier de stock journalier réel'
                    : 'B — déduction depuis les commandes ouvertes (aucun fichier de stock importé)',
                'seuils' => [
                    'rupture_pct' => (float) Setting::get('seuil_rupture_pct', 0.5),
                    'couverture_jours_bas' => (int) Setting::get('rupture_couverture_jours_bas', 2),
                    'couverture_jours_haut' => (int) Setting::get('rupture_couverture_jours_haut', 7),
                ],
                'compte' => $compte,
                'total_produits' => count($produits),
                'produits' => $produits,
                'note' => $methode === 'B'
                    ? "Pas d'historique de couverture en méthode B — il faudrait un fichier de stock journalier (méthode A)."
                    : null,
            ];
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function methodeB(StockCoverageCalculator $calc, ?User $user): array
    {
        $q = DB::table('commande_lignes as l')
            ->join('commandes as c', 'c.id', '=', 'l.commande_id')
            ->where('c.scope', OrderScope::Local->value)
            ->where('c.etat', '!=', 'cloturee');

        (new SocieteScope)->apply($q, $user, 'c.societe_id');

        $rows = $q->select('l.article', DB::raw('MAX(l.designation) as designation'),
            DB::raw('SUM(l.qte_commandee_raw) as cmd'), DB::raw('SUM(l.qte_livree_raw) as liv'))
            ->groupBy('l.article')->having('cmd', '>', 0)->get();

        return $rows->map(function ($r) use ($calc) {
            $res = $calc->fromOpenOrders((float) $r->cmd, (float) $r->liv);

            return [
                'article' => $r->article,
                'designation' => $r->designation,
                'statut' => $res->status->value,
                'statut_libelle' => $res->status->label(),
                'taux_couverture' => $res->tauxCouverture,
                'jours_couverture' => null,
                'historique' => [],
            ];
        })->sortBy(fn ($p) => array_search($p['statut'], ['rupture', 'alerte', 'normal', 'indetermine']))
            ->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function methodeA(StockCoverageCalculator $calc): array
    {
        // dernier stock connu par article + rythme de vente (7 derniers jours de livraisons)
        $dernier = StockSnapshot::query()
            ->select('article', 'date_stock', 'quantite_kg')
            ->orderByDesc('date_stock')->get()->unique('article');

        $ventes7j = DB::table('commande_lignes as l')
            ->join('commandes as c', 'c.id', '=', 'l.commande_id')
            ->where('c.scope', OrderScope::Local->value)
            ->where('l.compte_volume', true)
            ->whereDate('c.date_commande', '>=', now()->subDays(7)->toDateString())
            ->select('l.article', DB::raw('SUM(l.qte_livree_kg) / 7 as vj'))
            ->groupBy('l.article')->pluck('vj', 'article');

        $histo = StockSnapshot::query()->orderBy('date_stock')
            ->get(['article', 'date_stock', 'quantite_kg'])
            ->groupBy('article');

        return $dernier->map(function ($s) use ($calc, $ventes7j, $histo) {
            $res = $calc->fromDailyStock((float) $s->quantite_kg, (float) ($ventes7j[$s->article] ?? 0));

            return [
                'article' => $s->article,
                'designation' => $s->article,
                'statut' => $res->status->value,
                'statut_libelle' => $res->status->label(),
                'taux_couverture' => null,
                'jours_couverture' => $res->joursCouverture,
                'historique' => $histo->get($s->article, collect())
                    ->map(fn ($h) => ['date' => $h->date_stock->toDateString(), 'stock_kg' => (float) $h->quantite_kg])
                    ->all(),
            ];
        })->sortBy(fn ($p) => array_search($p['statut'], ['rupture', 'alerte', 'normal', 'indetermine']))
            ->values()->all();
    }
}
