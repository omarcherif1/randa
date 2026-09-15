<?php

namespace App\Http\Controllers;

use App\Domain\Dashboard\DashboardFilter;
use App\Domain\Dashboard\DashboardMetrics;
use App\Domain\Reporting\WeeklyReport;
use App\Models\Commande;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports du tableau de bord (section 4.2 / exigence 7).
 *   - CSV (ouvrable dans Excel, séparateur « ; », UTF-8 avec BOM) ;
 *   - rapport imprimable (HTML @media print → « Enregistrer en PDF » du navigateur).
 */
class DashboardExportController extends Controller
{
    public function commandesCsv(Request $request): StreamedResponse
    {
        $filter = DashboardFilter::fromRequest($request, $request->user());

        $q = Commande::query()->local()->visiblePour($filter->user)
            ->with('societe:id,code', 'client:id,nom');
        $this->appliquerFiltres($q, $filter);

        $nom = 'commandes_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
            fputcsv($out, [
                'Numero', 'Date commande', 'Societe', 'Client', 'Ville commande', 'Etat',
                'Tonnes commandees', 'Tonnes livrees', 'Tonnes non livrees',
                'Lignes', 'Lignes satisfaites', 'Exclue indicateurs', 'Anomalie',
            ], ';');

            $q->orderBy('date_commande')->chunk(1000, function ($commandes) use ($out) {
                foreach ($commandes as $c) {
                    fputcsv($out, [
                        $c->numero,
                        $c->date_commande?->toDateString(),
                        $c->societe?->code,
                        $c->client?->nom,
                        $c->ville_commande,
                        $c->etat.($c->etat_incertain ? ' (incertain)' : ''),
                        $this->t($c->qte_commandee_kg),
                        $this->t($c->qte_livree_kg),
                        $this->t(max(0, $c->qte_commandee_kg - $c->qte_livree_kg)),
                        $c->lignes_total,
                        $c->lignes_satisfaites,
                        $c->is_excluded ? 'oui' : 'non',
                        $c->has_outlier ? 'oui' : 'non',
                    ], ';');
                }
            });

            fclose($out);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function rapport(Request $request)
    {
        $user = $request->user();
        $filter = DashboardFilter::fromRequest($request, $user);

        $data = [
            'metrics' => (new DashboardMetrics)->forFilter($filter),
            'filtres' => $filter->toArray(),
            'genere_le' => now(),
            'genere_par' => $user?->name,
        ];

        // ?html=1 pour prévisualiser, sinon PDF téléchargeable (exigence 7).
        if ($request->boolean('html')) {
            return view('rapports.dashboard', $data);
        }

        return Pdf::loadView('rapports.dashboard', $data)
            ->setPaper('a4')
            ->download('rapport_tableau_de_bord_'.now()->format('Ymd').'.pdf');
    }

    /**
     * Exigence 7 — rapport hebdomadaire pour la direction : semaine écoulée vs
     * semaine précédente. `?html=1` pour prévisualiser.
     */
    public function rapportHebdomadaire(Request $request)
    {
        $data = ['r' => (new WeeklyReport)->pour($request->user())];

        if ($request->boolean('html')) {
            return view('rapports.hebdomadaire', $data);
        }

        return Pdf::loadView('rapports.hebdomadaire', $data)
            ->setPaper('a4')
            ->download('rapport_hebdomadaire_'.now()->format('Ymd').'.pdf');
    }

    private function appliquerFiltres($q, DashboardFilter $filter): void
    {
        if ($filter->societes !== []) {
            $q->whereHas('societe', fn ($s) => $s->whereIn('code', $filter->societes));
        }
        if ($filter->from !== null) {
            $q->whereDate('date_commande', '>=', $filter->from->toDateString());
        }
        if ($filter->to !== null) {
            $q->whereDate('date_commande', '<=', $filter->to->toDateString());
        }
        if ($filter->articles !== []) {
            $q->whereHas('lignes', fn ($l) => $l->whereIn('article', $filter->articles));
        }
        if ($filter->familles !== []) {
            $q->whereHas('lignes.famille', fn ($f) => $f->whereIn('code', $filter->familles));
        }
        if ($filter->clientCategories !== []) {
            $q->whereHas('client.category', fn ($c) => $c->whereIn('code', $filter->clientCategories));
        }
    }

    private function t(mixed $kg): string
    {
        return number_format(((float) $kg) / 1000, 2, ',', ' ');
    }
}
