<?php

namespace App\Http\Middleware;

use App\Domain\Access\AccessControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Règle 3.1 — contrôle d'accès page par page, personne par personne.
 * Usage : ->middleware('page:dashboard')
 */
class EnsureCanAccessPage
{
    public function __construct(private readonly AccessControl $access) {}

    public function handle(Request $request, Closure $next, string $pageKey): Response
    {
        if (! $this->access->canAccessPage($request->user(), $pageKey)) {
            abort(403, "Vous n'avez pas accès à cette page.");
        }

        return $next($request);
    }
}
