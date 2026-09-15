<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règle 2.6 (complément) — table de mémorisation CODE ARTICLE COMPLET -> famille.
 *
 * La clé est TOUJOURS le code article dans son intégralité, jamais un préfixe
 * (les tests sur données réelles montrent que des articles au même préfixe à
 * 3 chiffres ont des familles différentes).
 *
 * Construite/enrichie de façon incrémentale à chaque import (jamais recalculée
 * en relisant tout l'historique). Consultable et corrigeable par un admin.
 *
 * `statut` :
 *   - confirme            : une seule famille jamais observée pour cet article ;
 *   - reclassification    : familles disjointes dans le temps (avant/après une date) —
 *                           on garde la plus récente et on journalise l'historique ;
 *   - ambigu              : familles incohérentes sans logique de date —
 *                           AUCUN choix automatique, confirmation manuelle requise ;
 *   - correction_manuelle : valeur fixée par un administrateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_families', function (Blueprint $table) {
            $table->id();
            $table->string('article', 40)->unique();
            $table->string('designation', 200)->nullable();
            $table->foreignId('famille_id')->nullable()->constrained('familles')->nullOnDelete();
            $table->string('famille_code', 10)->nullable();

            $table->string('statut', 24)->index(); // confirme|reclassification|ambigu|correction_manuelle

            // historique des familles observées : [{code, from, to, count}]
            $table->json('historique')->nullable();

            $table->foreignId('corrige_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrige_le')->nullable();

            $table->timestamps(); // updated_at = trace de la dernière mise à jour
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_families');
    }
};
