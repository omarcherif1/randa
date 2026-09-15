<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres réglables par un administrateur (page Paramètres, section 4.9).
 * Toute modification est journalisée dans le journal d'audit (section 3.2).
 *
 * Clés prévues :
 *  - seuil_retard_jours            (défaut 2)   — règle 2.4
 *  - seuil_rupture_pct             (défaut 0.5) — règle 2.5 méthode B
 *  - rupture_couverture_jours_bas  (défaut 2)   — règle 2.5 méthode A
 *  - rupture_couverture_jours_haut (défaut 7)   — règle 2.5 méthode A
 *  - outlier_qte_max_kg            (défaut 1000000) — règle 2.9
 *  - outlier_ratio_commande_livree_max (défaut 1000) — règle 2.9
 *  - outlier_ratio_mediane_max     (défaut 25)  — règle 2.9
 *  - famille_colonne_index         (auto-détectée, override admin) — règle 2.6
 *  - unite_reference               (défaut "KG") — règle 2.8
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->json('value');
            $table->string('type', 20)->default('string'); // string|int|float|bool|json
            $table->string('libelle', 160);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
