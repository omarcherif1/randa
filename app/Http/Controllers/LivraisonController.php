<?php

namespace App\Http\Controllers;

use App\Domain\Delivery\DeliveryMetrics;
use App\Domain\Delivery\DeliveryTableQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page Livraisons (section 4.3) — commandes LOCALES, état de cycle de vie,
 * retards (règle 2.4, « donnée indisponible » tant que la date d'expédition
 * n'est pas fiable).
 */
class LivraisonController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Livraisons/Index', [
            'metrics' => (new DeliveryMetrics)->forUser($user),
            'commandes' => Inertia::defer(
                fn () => (new DeliveryTableQuery)->paginate($request, $user),
            ),
        ]);
    }
}
