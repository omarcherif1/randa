<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règle 2.8 — table de correspondance ARTICLE -> poids d'une unité de vente (kg).
 *
 * Construite et enrichie automatiquement à chaque import (jamais recalculée à
 * l'affichage). Sert de "preuve de fiabilité" : un administrateur peut vérifier
 * d'où vient chaque facteur et le corriger sans intervention technique
 * (page Administration / Paramètres, section 4).
 *
 * `poids_unitaire_kg` NULL = poids non déterminé => les lignes de cet article
 * sont exclues des tonnages (comptées à part), jamais converties avec un
 * facteur inventé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_unit_weights', function (Blueprint $table) {
            $table->id();
            $table->string('article', 40)->unique();
            $table->string('designation', 200)->nullable();          // dernière désignation vue
            $table->string('unite_vente', 12);                       // KG | SAC | QX | T
            $table->decimal('poids_unitaire_kg', 12, 4)->nullable();

            // kg_neutre | extrait_designation | hypothese_quintal | non_determine | correction_manuelle
            $table->string('origine', 24)->index();

            // désignation à partir de laquelle le poids a été extrait / validé
            $table->string('designation_reference', 200)->nullable();

            // contradiction détectée avec la valeur validée (on garde l'ancienne)
            $table->boolean('en_conflit')->default(false)->index();
            $table->json('conflit_details')->nullable();

            $table->foreignId('corrige_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrige_le')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_unit_weights');
    }
};
