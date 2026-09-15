<?php

namespace App\Http\Controllers;

use App\Domain\Dashboard\CommandesTableQuery;
use App\Domain\Dashboard\DashboardFilter;
use App\Domain\Dashboard\DashboardMetrics;
use App\Domain\Dashboard\FilterOptions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tableau de bord principal (section 4.2) — EXCLUSIVEMENT local (règle 2.2).
 * Les KPI arrivent d'emblée ; la table interactive se charge en différé
 * (rechargement partiel Inertia) — exigence 7 (KPI avant le reste).
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filter = DashboardFilter::fromRequest($request, $user);

        return Inertia::render('Dashboard', [
            'metrics' => (new DashboardMetrics)->forFilter($filter),
            'options' => (new FilterOptions)->forUser($user),
            'filtres' => $filter->toArray(),
            'commandes' => Inertia::defer(
                fn () => (new CommandesTableQuery)->paginate($request, $filter),
            ),
        ]);
    }
}
