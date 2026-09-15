<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règle métier 2.7 point 3 : reconnaissance automatique du type de client
 * par mots-clés dans le nom du client (ex. "MONOPRIX", "CARREFOUR" => Grande Surface).
 *
 * Liste maintenue et modifiable depuis la page Paramètres — SANS toucher au code.
 * `priorite` : si plusieurs mots-clés matchent, le plus prioritaire (valeur haute) gagne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classification_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_category_id')->constrained()->cascadeOnDelete();
            // stocké normalisé (majuscules, sans accents) pour un matching robuste
            $table->string('keyword', 80);
            $table->unsignedSmallInteger('priorite')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['client_category_id', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classification_keywords');
    }
};
