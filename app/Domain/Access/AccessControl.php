<?php

namespace App\Domain\Access;

use App\Models\User;

/**
 * Règle 3.1 — décide si une personne peut accéder à une page.
 *
 *   - compte verrouillé              => aucun accès (même en pleine session) ;
 *   - administrateur                 => toutes les pages (dont Administration / Paramètres) ;
 *   - page réservée aux admins       => refus si non-admin ;
 *   - sinon                          => autorisé seulement si `page_permissions`
 *                                       contient la ligne (user, page).
 */
final class AccessControl
{
    public function canAccessPage(?User $user, string $pageKey): bool
    {
        if ($user === null || ! Page::exists($pageKey)) {
            return false;
        }

        if ($user->isLocked()) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        if (Page::isAdminOnly($pageKey)) {
            return false;
        }

        return $user->pagePermissions()->where('page_key', $pageKey)->exists();
    }

    /** @return list<string> pages visibles par cette personne */
    public function accessiblePages(?User $user): array
    {
        if ($user === null || $user->isLocked()) {
            return [];
        }

        if ($user->is_admin) {
            return Page::keys();
        }

        $accordees = $user->pagePermissions()->pluck('page_key')->all();

        return array_values(array_filter(
            Page::assignableKeys(),
            fn ($key) => in_array($key, $accordees, true),
        ));
    }
}
