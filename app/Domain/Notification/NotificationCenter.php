<?php

namespace App\Domain\Notification;

use App\Domain\Access\AccessControl;
use App\Domain\Reporting\MetricsCache;
use App\Models\User;

/**
 * Section 6 — centre de notifications.
 *
 *   - agrège les alertes de toutes les règles déclarées (`config/notifications.php`) ;
 *   - ne montre à une personne que les alertes des règles dont elle a l'accès
 *     (règle 3.1) ET dans son périmètre société ;
 *   - passe par {@see MetricsCache} comme toute autre page : le résultat est mis
 *     en cache par (périmètre + pages accessibles) × (version des données), et
 *     n'est recalculé qu'à un nouvel import ou à un changement d'accès.
 */
final class NotificationCenter
{
    /** @param list<AlertRule> $regles */
    public function __construct(
        private readonly array $regles,
        private readonly AccessControl $access = new AccessControl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(array_map(
            static fn (string $classe): AlertRule => app($classe),
            config('notifications.regles', []),
        ));
    }

    /**
     * @return array{count:int, alertes:list<array<string,mixed>>, fraicheur:?string}
     */
    public function pour(?User $user): array
    {
        if ($user === null || $user->isLocked()) {
            return ['count' => 0, 'alertes' => [], 'fraicheur' => null];
        }

        return MetricsCache::remember('notifications', $this->empreinte($user), function () use ($user) {
            $alertes = [];

            foreach ($this->regles as $regle) {
                $page = $regle->pageRequise();
                if ($page !== null && ! $this->access->canAccessPage($user, $page)) {
                    continue;
                }

                foreach ($regle->evaluer($user) as $alerte) {
                    $alertes[] = $alerte->toArray();
                }
            }

            $rang = [Alert::CRITIQUE => 0, Alert::ALERTE => 1, Alert::INFO => 2];
            usort($alertes, fn ($a, $b) => ($rang[$a['severity']] ?? 9) <=> ($rang[$b['severity']] ?? 9));

            return [
                'count' => count($alertes),
                'alertes' => $alertes,
                'fraicheur' => MetricsCache::fraicheur(),
            ];
        });
    }

    /**
     * Le rattachement société filtre les données ET les pages accessibles
     * déterminent quelles règles s'appliquent : les deux entrent dans la clé.
     */
    private function empreinte(User $user): string
    {
        $pages = $this->access->accessiblePages($user);
        sort($pages);

        return MetricsCache::userScope($user).'|p:'.substr(md5(implode(',', $pages)), 0, 12);
    }
}
