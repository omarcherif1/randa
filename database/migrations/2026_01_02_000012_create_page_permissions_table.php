<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règle 3.1 — l'accès est accordé PAGE PAR PAGE et PERSONNE PAR PERSONNE.
 * Une ligne = « cette personne peut voir cette page ». Pas de rôle générique figé
 * (les "modèles de permissions" ne servent qu'à pré-remplir cette table vite).
 *
 * Les pages réservées aux administrateurs (Administration, Paramètres) ne
 * passent JAMAIS par cette table : elles dépendent uniquement de `users.is_admin`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('page_key', 40);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'page_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_permissions');
    }
};
