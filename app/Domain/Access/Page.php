<?php

namespace App\Domain\Access;

/**
 * Catalogue des pages protégeables de l'application (section 4).
 * SOURCE UNIQUE : toute vérification d'accès, tout écran d'attribution de droits
 * et toute navigation part d'ici.
 *
 * `adminOnly` = page réservée aux administrateurs (Administration, Paramètres) :
 * elle ne dépend QUE de `users.is_admin`, jamais de la table `page_permissions`.
 */
final class Page
{
    /**
     * @var array<string, array{label:string, groupe:string, adminOnly:bool}>
     */
    public const CATALOGUE = [
        'dashboard' => ['label' => 'Tableau de bord', 'groupe' => 'Pilotage', 'adminOnly' => false],
        'livraisons' => ['label' => 'Livraisons', 'groupe' => 'Pilotage', 'adminOnly' => false],
        'stock' => ['label' => 'Stock', 'groupe' => 'Pilotage', 'adminOnly' => false],
        'previsions' => ['label' => 'Prévisions', 'groupe' => 'Pilotage', 'adminOnly' => false],
        'export' => ['label' => 'Export', 'groupe' => 'Pilotage', 'adminOnly' => false],
        'notifications' => ['label' => 'Notifications', 'groupe' => 'Pilotage', 'adminOnly' => false],
        'import' => ['label' => 'Import', 'groupe' => 'Données', 'adminOnly' => false],
        'aide' => ['label' => 'Comment lire ces chiffres', 'groupe' => 'Données', 'adminOnly' => false],
        'administration' => ['label' => 'Administration', 'groupe' => 'Admin', 'adminOnly' => true],
        'parametres' => ['label' => 'Paramètres', 'groupe' => 'Admin', 'adminOnly' => true],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CATALOGUE);
    }

    /** @return list<string> pages attribuables individuellement (hors admin) */
    public static function assignableKeys(): array
    {
        return array_keys(array_filter(self::CATALOGUE, fn ($p) => ! $p['adminOnly']));
    }

    public static function exists(string $key): bool
    {
        return isset(self::CATALOGUE[$key]);
    }

    public static function isAdminOnly(string $key): bool
    {
        return self::CATALOGUE[$key]['adminOnly'] ?? false;
    }

    public static function label(string $key): string
    {
        return self::CATALOGUE[$key]['label'] ?? $key;
    }

    /** @return array<int, array{key:string, label:string, groupe:string, adminOnly:bool}> */
    public static function all(): array
    {
        $out = [];
        foreach (self::CATALOGUE as $key => $meta) {
            $out[] = ['key' => $key] + $meta;
        }

        return $out;
    }
}
