<?php

namespace App\Http\Controllers;

use App\Domain\Dashboard\FilterOptions;
use App\Domain\Forecast\ForecastFilter;
use App\Domain\Forecast\ForecastMetrics;
use App\Domain\Forecast\MlForecastClient;
use App\Domain\Forecast\MlForecastException;
use App\Domain\Reporting\MetricsCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page Prévisions (section 4.5) — prévision de la demande par produit,
 * paliers de fiabilité, backtest. LOCAL uniquement.
 */
class ForecastController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filter = ForecastFilter::fromRequest($request, $user);

        return Inertia::render('Previsions/Index', [
            'metrics' => (new ForecastMetrics)->forFilter($filter),
            'options' => (new FilterOptions)->forUser($user),
            'filtres' => $filter->toArray(),
        ]);
    }

    /**
     * Réentraîne le modèle ML sur tout l'historique local disponible
     * (jamais limité aux filtres actifs à l'écran — le modèle doit
     * apprendre de tout ce qu'on a, pas d'un sous-ensemble affiché).
     * Réservé aux administrateurs — vérifié ici, pas seulement dans
     * l'affichage du bouton côté page.
     */
    public function reentrainerModele(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $metrics = new ForecastMetrics;
        $lignes = $metrics->lignesPourEntrainement(new ForecastFilter(user: $request->user()));

        try {
            $resultat = MlForecastClient::fromConfig()->entrainer($lignes);
        } catch (MlForecastException $e) {
            return back()->with('erreur', "Entraînement échoué : {$e->getMessage()}");
        }

        // Le modèle a changé : tout ce qui dépend de la page Prévisions doit
        // être recalculé, pas servi depuis un cache qui prédate l'entraînement.
        MetricsCache::bump();

        $wape = $resultat['wape'] !== null ? round($resultat['wape'] * 100, 1).' %' : 'indéterminée';

        return back()->with(
            'status',
            "Modèle entraîné sur {$resultat['n_echantillons']} lignes ({$resultat['n_produits']} produits) — "
                ."erreur pondérée par le volume : {$wape}.",
        );
    }
}
