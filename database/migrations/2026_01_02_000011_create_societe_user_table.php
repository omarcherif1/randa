<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règle 3.1 — chaque personne est rattachée à une ou plusieurs sociétés.
 * Défaut V1 corrigé : ce rattachement filtre RÉELLEMENT les données visibles
 * (voir Commande::scopeVisiblePour / App\Domain\Access\SocieteScope).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('societe_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('societe_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'societe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('societe_user');
    }
};
