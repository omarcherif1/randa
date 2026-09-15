<?php

namespace App\Domain\Reporting;

use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Cache d'agrégats — mécanisme UNIQUE pour TOUTE l'application (exigence 7).
 *
 * Principe (le même partout : tableau de bord, Livraisons, Stock, Export,
 * Prévisions, et toute page à venir) :
 *
 *   - un résultat d'agrégat est mis en cache par (jeu de filtres) × (version des données) ;
 *   - il n'est JAMAIS recalculé à chaque affichage ;
 *   - il n'est invalidé que dans DEUX cas :
 *       1. un nouvel import de données  -> `bump()` change la version, toutes les
 *          entrées deviennent obsolètes d'un coup ;
 *       2. un changement de filtre par l'utilisateur -> la clé change, seul le
 *          sous-ensemble concerné est (re)calculé, jamais toute la page.
 *
 * Aucune page ne doit recréer son propre mécanisme : elles appellent toutes
 * `MetricsCache::remember()`.
 */
final class MetricsCache
{
    private const VERSION_KEY = 'metrics.data_version';

    private const TTL = 3600; // filet de sécurité (les vraies invalidations sont l'import + la clé)

    public static function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn () => 1);
    }

    /** Appelé après chaque import réussi — invalide TOUS les agrégats de l'app. */
    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    /**
     * @template T
     *
     * @param  string  $namespace  page/bloc concerné (ex. "dashboard", "livraisons.table")
     * @param  string  $scopeHash  empreinte du jeu de filtres / du périmètre utilisateur
     * @param  callable():T  $compute
     * @return T
     */
    public static function remember(string $namespace, string $scopeHash, callable $compute): mixed
    {
        return Cache::remember(
            'metrics.v'.self::version().'.'.$namespace.'.'.$scopeHash,
            self::TTL,
            $compute,
        );
    }

    /**
     * Empreinte du périmètre d'un utilisateur (règle 3.1 : le rattachement société
     * filtre les données, il fait donc partie de la clé de cache).
     */
    public static function userScope(?User $user): string
    {
        if ($user === null) {
            return 'anon';
        }

        return $user->is_admin ? 'admin' : 'soc:'.implode(',', $user->societeIds() ?: ['none']);
    }

    /** Date/heure de la donnée affichée = dernier import terminé (fraîcheur, exigence 7). */
    public static function fraicheur(): ?string
    {
        return ImportBatch::where('status', 'completed')
            ->where('type', 'unified')
            ->latest('finished_at')
            ->value('finished_at')?->toIso8601String();
    }
}
