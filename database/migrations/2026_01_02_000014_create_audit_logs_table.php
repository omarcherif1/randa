<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règle 3.2 — journal d'audit des actions sensibles : QUI, QUOI, QUAND.
 * Changement de droits, réinitialisation de mot de passe, verrouillage de compte,
 * modification d'un seuil, suppression de données, résolution d'anomalie…
 * Consultable par un administrateur (page Administration, 4.8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // l'auteur de l'action
            $table->string('acteur_libelle', 120)->nullable(); // nom figé (si le compte est supprimé ensuite)
            $table->string('action', 60)->index();
            $table->string('cible_type', 60)->nullable();
            $table->string('cible_id', 60)->nullable();
            $table->string('description', 255);
            $table->json('metadata')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
