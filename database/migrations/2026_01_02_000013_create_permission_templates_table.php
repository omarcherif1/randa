<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règle 3.1 — "modèles de permissions prêts à l'emploi" (Direction, Commercial,
 * Responsable Transport, Planificatrice, Magasin…). Un administrateur applique
 * un modèle à une personne pour pré-remplir ses accès, puis affine page par page.
 * Le modèle n'est PAS un rôle : aucune vérification d'accès ne le consulte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('nom', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('accorde_admin')->default(false); // le modèle "Administrateur"
            $table->json('pages')->nullable();                 // liste de page_key pré-cochées
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_templates');
    }
};
