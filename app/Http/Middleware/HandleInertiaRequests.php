<?php

namespace App\Http\Middleware;

use App\Domain\Access\AccessControl;
use App\Domain\Access\Page;
use App\Domain\Notification\NotificationCenter;
use App\Domain\Reporting\IndicatorGlossary;
use App\Domain\Reporting\MetricsCache;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                // Règle 3.1 — navigation construite à partir des accès réels de la personne.
                'isAdmin' => (bool) $user?->is_admin,
                'mustChangePassword' => (bool) $user?->must_change_password,
                'pages' => $user ? (new AccessControl)->accessiblePages($user) : [],
            ],
            'pageCatalogue' => Page::all(),
            // Section 6 — alertes actives de la personne (déjà filtrées selon ses
            // droits). Passe par MetricsCache : quasi instantané en cache.
            'notifications' => fn () => $user
                ? NotificationCenter::fromConfig()->pour($user)
                : ['count' => 0, 'alertes' => [], 'fraicheur' => null],
            // Exigence 7 — fraîcheur affichée partout de façon cohérente + aide
            // contextuelle par indicateur (définition unique, cf. IndicatorGlossary).
            'fraicheur' => fn () => $user ? MetricsCache::fraicheur() : null,
            'glossaire' => fn () => $user ? IndicatorGlossary::all() : [],
            'flash' => [
                'status' => $request->session()->get('status'),
                'erreur' => $request->session()->get('erreur'),
            ],
        ];
    }
}
