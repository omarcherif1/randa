<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règle métier 2.6 : 32 familles officielles fournies par l'entreprise.
 * Table de référence — sert à détecter automatiquement la bonne colonne
 * "Famille statistique" dans le fichier source (position variable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('familles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('intitule', 120);
            $table->string('intitule_court', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('familles');
    }
};
