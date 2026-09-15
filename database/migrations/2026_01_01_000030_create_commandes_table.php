<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une commande = un No commande unique (ex. "CMRAN012605170").
 *
 * Règle 2.2 : le SCOPE (local / export) est déterminé UNIQUEMENT par le préfixe
 * du numéro de commande (CM => local, EXP => export). Jamais par le site de vente.
 *
 * Section 1 : le cycle réel d'une commande (saisie → réservée → expédiée →
 * facturée → clôturée) ne peut pas toujours être reconstitué finement depuis
 * le fichier. `etat` est le meilleur état déductible ; `etat_incertain` signale
 * quand la donnée ne permet pas de trancher entre "réservé" et "expédié".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->string('scope', 8)->index();            // 'local' | 'export'
            $table->foreignId('societe_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();

            $table->date('date_commande')->nullable()->index();
            $table->string('site_vente', 20)->nullable();
            $table->string('ville_commande', 120)->nullable();   // ville de la COMMANDE (pas de livraison)
            $table->string('adresse_livraison', 60)->nullable();

            // Signaux de cycle de vie (section 1)
            $table->string('etat', 20)->default('saisie')->index(); // saisie|reservee|expediee|facturee|cloturee
            $table->boolean('etat_incertain')->default(false);
            $table->string('numero_livraison', 40)->nullable();
            $table->string('numero_facture', 40)->nullable();
            $table->date('date_facture')->nullable();            // seule 2e date dispo — proxy possible d'expédition (cf. 2.4 / section 10)

            // Agrégats de ligne (recalculés à l'import, jamais à l'affichage — exigence 7 perf)
            $table->decimal('qte_commandee_kg', 18, 3)->default(0);
            $table->decimal('qte_livree_kg', 18, 3)->default(0);
            $table->decimal('qte_facturee_kg', 18, 3)->default(0);
            $table->unsignedInteger('lignes_total')->default(0);
            $table->unsignedInteger('lignes_eligibles')->default(0);   // entrant dans le calcul de satisfaction
            $table->unsignedInteger('lignes_satisfaites')->default(0);

            // Règle 2.1 : exclusion calculée (au moins une raison => commande hors indicateurs)
            $table->boolean('is_excluded')->default(false)->index();
            $table->boolean('has_outlier')->default(false)->index();

            $table->timestamps();

            $table->index(['scope', 'societe_id', 'date_commande']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};
