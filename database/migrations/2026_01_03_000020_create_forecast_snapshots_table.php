<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règle 4.5 — trace des prévisions produites, à chaque import.
 *
 * Deux usages :
 *   1. constituer un vrai historique « ce qu'on avait prévu » vs « ce qui s'est
 *      passé » qui s'enrichit dans le temps ;
 *   2. NE PAS fermer le modèle à la seule prévision de vente : la colonne
 *      `qte_a_produire_kg` est réservée pour la V3 (estimation des quantités à
 *      produire + aide à la planification par ligne de production).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('article', 40);
            $table->string('designation', 200)->nullable();
            $table->foreignId('societe_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mois_cible', 7);                 // AAAA-MM prévu

            $table->decimal('qte_commandee_prevue_kg', 18, 3)->nullable();
            $table->unsignedTinyInteger('palier')->default(0);
            $table->string('fiabilite', 16);
            $table->string('tendance', 16);
            $table->unsignedSmallInteger('mois_historique')->default(0);
            $table->boolean('ajustement_saisonnier')->default(false);

            // Réservé V3 — aide à la production :
            $table->decimal('qte_a_produire_kg', 18, 3)->nullable();

            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('genere_le');
            $table->timestamps();

            $table->unique(['article', 'societe_id', 'mois_cible']); // re-snapshot => remplacement
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_snapshots');
    }
};
