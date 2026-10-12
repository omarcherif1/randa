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
 * Scindé en deux pages : « Dashboard » (chiffres clés) et « Local »
 * (graphiques, classements, table interactive des commandes).
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
        ]);
    }

    public function local(Request $request): Response
    {
        $user = $request->user();
        $filter = DashboardFilter::fromRequest($request, $user);

        return Inertia::render('Dashboard/Local', [
            'metrics' => (new DashboardMetrics)->forFilter($filter),
            'options' => (new FilterOptions)->forUser($user),
            'filtres' => $filter->toArray(),
            'commandes' => Inertia::defer(
                fn () => (new CommandesTableQuery)->paginate($request, $filter),
            ),
        ]);
    }
}
