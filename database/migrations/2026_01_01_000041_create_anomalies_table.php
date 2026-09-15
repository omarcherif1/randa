<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règle 2.9 : liste "anomalies à vérifier", consultable par un administrateur
 * (page Administration). Une anomalie n'entre JAMAIS dans les totaux affichés
 * à la direction tant qu'elle n'est pas validée.
 *
 * type   : outlier_quantite | famille_inconnue | unite_non_confirmee
 *          | client_non_classifie | ...
 * statut : a_verifier | validee (réintègre les indicateurs) | exclue (définitivement hors indicateurs)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commande_ligne_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('commande_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('type', 40)->index();
            $table->string('severity', 12)->default('warning'); // info | warning | critical
            $table->string('resume', 255);
            $table->string('article', 40)->nullable()->index();  // anomalies au grain article (famille, poids…)
            // empreinte stable d'une anomalie : si l'admin l'a déjà tranchée, un ré-import ne la recrée pas
            $table->string('signature', 191)->nullable()->index();
            $table->json('details')->nullable();

            $table->string('statut', 12)->default('a_verifier')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomalies');
    }
};
