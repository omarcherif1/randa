<?php

namespace App\Http\Controllers;

use App\Domain\Stock\StockMetrics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page Stock (section 4.4) — statut par produit selon la méthode active (2.5).
 * Module « priorité basse » : version simple.
 */
class StockController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Stock/Index', [
            'metrics' => (new StockMetrics)->forUser($request->user()),
        ]);
    }
}
