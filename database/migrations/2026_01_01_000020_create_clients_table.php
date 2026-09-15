<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règle métier 2.7 : l'identifiant STABLE du client est son NUMÉRO (ex. "1189934M"),
 * jamais son nom (qui varie d'une commande à l'autre).
 *
 * Chaque client est classifié UNE SEULE FOIS (par numéro) ; la catégorie est
 * ensuite réutilisée pour toutes ses commandes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->string('nom', 200);                 // dernier nom vu
            $table->foreignId('client_category_id')->nullable()->constrained()->nullOnDelete();
            // priorité de la source ayant déterminé la catégorie (règle 2.7) :
            // reference | deduced | keyword | undefined
            $table->string('category_source', 20)->default('undefined');
            $table->string('category_detail', 160)->nullable(); // ex. mot-clé ayant matché
            // NB : "ville" vient de la commande, PAS d'une vraie ville de livraison (cf. 4.3)
            $table->string('derniere_ville_commande', 120)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
