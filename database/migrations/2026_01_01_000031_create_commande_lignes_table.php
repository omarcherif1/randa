<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une ligne = un produit dans une commande (grain du fichier source).
 *
 * Les quantités sont stockées DEUX fois :
 *  - `*_raw` : valeur brute du fichier, dans l'unité de vente d'origine (traçabilité) ;
 *  - `*_kg`  : valeur convertie en kg par App\Domain\Units\UnitConverter (règle 2.8) —
 *              SEULE forme utilisée pour toute agrégation.
 *
 * Règle 2.3 : `is_satisfied` = (qte_livree >= qte_commandee), calculé une fois ici.
 * Règle 2.1 : `exclusion_reason` non nul => la ligne (et sa commande) sortent des indicateurs.
 * Règle 2.9 : `is_outlier` => la ligne va dans "anomalies à vérifier", jamais dans les totaux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commande_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produit_id')->nullable()->constrained('produits')->nullOnDelete();
            $table->foreignId('famille_id')->nullable()->constrained('familles')->nullOnDelete();

            $table->string('article', 40)->nullable();
            $table->string('designation', 200)->nullable();
            $table->string('unite_vente', 12)->nullable();
            $table->string('famille_code_fichier', 10)->nullable();   // code brut lu dans le fichier
            $table->string('famille_source', 20)->default('unknown'); // file|backfilled|unknown
            $table->string('etat_ligne', 20)->nullable();             // "Soldé" | "Attente" ...

            // Quantités BRUTES — dans l'unité d'origine. Base du taux de satisfaction (2.3).
            $table->decimal('qte_commandee_raw', 18, 3)->default(0);
            $table->decimal('qte_livree_raw', 18, 3)->default(0);
            $table->decimal('qte_facturee_raw', 18, 3)->default(0);

            // Quantités CONVERTIES en kg — base des tonnages (2.8). NULL = poids indéterminé,
            // ligne exclue des totaux en kg/tonnes (jamais un 0 silencieux).
            $table->decimal('qte_commandee_kg', 18, 3)->nullable();
            $table->decimal('qte_livree_kg', 18, 3)->nullable();
            $table->decimal('qte_facturee_kg', 18, 3)->nullable();

            // Règle 2.8 : poids retenu pour la conversion + sa provenance.
            $table->decimal('poids_unitaire_kg', 12, 4)->nullable();
            $table->string('poids_origine', 24)->nullable(); // kg_neutre|extrait_designation|hypothese_quintal|non_determine|correction_manuelle
            $table->boolean('compte_volume')->default(false); // la ligne entre-t-elle dans les tonnages ?
            $table->boolean('volume_fiable')->default(false); // tonnage fiable, ou simple hypothèse (QX) ?

            $table->boolean('is_eligible')->default(true);   // entre dans le calcul de satisfaction
            $table->boolean('is_satisfied')->default(false);
            $table->string('exclusion_reason', 40)->nullable()->index();
            // mouvement_interne | hors_perimetre | ligne_vide | ouverte_sans_livraison

            $table->boolean('is_outlier')->default(false)->index();
            $table->string('outlier_reason', 60)->nullable();

            // dédoublonnage à l'import (règle 4.7) : empreinte stable d'une ligne
            $table->string('line_hash', 64)->index();

            $table->timestamps();

            $table->index(['commande_id', 'article']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commande_lignes');
    }
};
