<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des imports (sections 4.7 / 4.8).
 * Chaque import est tracé : date, volumétrie, anomalies détectées, avertissements.
 * Consultable dans Administration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('unified');   // unified | stock
            $table->string('original_filename', 255);
            $table->string('stored_path', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 20)->default('pending');  // pending|running|completed|failed
            $table->text('error_message')->nullable();

            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->unsignedInteger('commandes_new')->default(0);
            $table->unsignedInteger('commandes_replaced')->default(0);
            $table->unsignedInteger('lignes_count')->default(0);
            $table->unsignedInteger('clients_new')->default(0);
            $table->unsignedInteger('produits_new')->default(0);
            $table->unsignedInteger('anomalies_count')->default(0);
            $table->unsignedInteger('familles_inconnues_count')->default(0);

            // Règle 2.6 : colonne "Famille statistique" retenue par l'auto-détection
            $table->unsignedSmallInteger('famille_colonne_index')->nullable();
            $table->json('famille_colonnes_candidates')->nullable();

            $table->json('warnings')->nullable();
            $table->json('stats')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
