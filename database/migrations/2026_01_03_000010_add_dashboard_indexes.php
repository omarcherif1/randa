<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exigence 7 — index de support des agrégats du tableau de bord.
 * (les index scope/société/date et etat existent déjà sur `commandes`.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commande_lignes', function (Blueprint $table) {
            $table->index('famille_id', 'cl_famille_idx');
            $table->index(['is_eligible', 'is_satisfied'], 'cl_satisfaction_idx');
            $table->index('compte_volume', 'cl_volume_idx');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->index('client_category_id', 'client_categorie_idx');
        });
    }

    public function down(): void
    {
        Schema::table('commande_lignes', function (Blueprint $table) {
            $table->dropIndex('cl_famille_idx');
            $table->dropIndex('cl_satisfaction_idx');
            $table->dropIndex('cl_volume_idx');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('client_categorie_idx');
        });
    }
};
