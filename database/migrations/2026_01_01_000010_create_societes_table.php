<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sociétés du groupe (Randa, SMT, Tunisie Farine).
 * Règle métier 2.3 / 8 : une couleur unique par société, réutilisée partout.
 * Le taux de satisfaction n'est JAMAIS moyenné entre sociétés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('societes', function (Blueprint $table) {
            $table->id();
            // Préfixe métier présent dans le numéro de commande (CM<code> / EXP<code>)
            // ex. CMRAN..., EXPRAN..., CMSMT... => code = RAN / SMT / TF
            $table->string('code', 8)->unique();
            $table->string('nom', 100);
            // Couleur dédiée (hex) — cohérente sur tous les graphiques de l'app
            $table->string('couleur', 7);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('societes');
    }
};
