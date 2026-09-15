<?php

namespace Database\Seeders;

use App\Models\PermissionTemplate;
use Illuminate\Database\Seeder;

/**
 * Règle 3.1 — modèles de permissions "prêts à l'emploi".
 * Ce ne sont PAS des rôles : un administrateur les applique pour pré-remplir
 * les accès d'une personne, puis affine page par page.
 */
class PermissionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $modeles = [
            ['administrateur', 'Administrateur', 'Accès total + Administration et Paramètres', true, []],
            ['direction', 'Direction', 'Vue d\'ensemble du pilotage', false,
                ['dashboard', 'livraisons', 'stock', 'previsions', 'export', 'notifications', 'aide']],
            ['commercial', 'Commercial', 'Commandes et livraisons', false,
                ['dashboard', 'livraisons', 'notifications', 'aide']],
            ['responsable_transport', 'Responsable Transport', 'Livraisons et retards', false,
                ['livraisons', 'notifications', 'aide']],
            ['planificatrice', 'Planificatrice', 'Prévisions et stock', false,
                ['previsions', 'stock', 'dashboard', 'aide']],
            ['magasin', 'Magasin', 'Stock et import', false,
                ['stock', 'import', 'notifications', 'aide']],
        ];

        foreach ($modeles as [$code, $nom, $description, $admin, $pages]) {
            PermissionTemplate::updateOrCreate(
                ['code' => $code],
                ['nom' => $nom, 'description' => $description, 'accorde_admin' => $admin, 'pages' => $pages],
            );
        }
    }
}
