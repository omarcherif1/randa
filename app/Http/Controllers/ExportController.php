<?php

namespace App\Http\Controllers;

use App\Domain\Export\ExportMetrics;
use App\Domain\Export\ExportTableQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page Export (section 4.6) — TOTALEMENT séparée du local (règle 2.2).
 * Indicateurs propres à l'export, aucune règle locale.
 */
class ExportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Export/Index', [
            'metrics' => (new ExportMetrics)->forUser($user),
            'commandes' => Inertia::defer(
                fn () => (new ExportTableQuery)->paginate($request, $user),
            ),
        ]);
    }
}
