<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règle métier 2.7 : 21 catégories officielles de type de client.
 * La catégorie "Indéfini" (code 199 / Divers) est explicitement affichée et filtrable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('intitule', 120);
            $table->string('intitule_court', 40)->nullable();
            // 2.7 point 2 : un client livré à l'étranger => Exportation
            $table->boolean('is_export')->default(false);
            // catégorie "fourre-tout" quand rien ne matche (jamais masquée)
            $table->boolean('is_fallback')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_categories');
    }
};
