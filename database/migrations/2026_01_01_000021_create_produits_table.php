<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règle métier 2.6 : chaque produit appartient à une famille officielle.
 * Si la famille est absente de la ligne source, on tente de la compléter par
 * recoupement avec le code article déjà vu ailleurs dans l'historique
 * (famille_source = 'backfilled') ; sinon la famille reste NULL et le produit
 * est rangé dans la catégorie explicite "Famille inconnue" (jamais masqué).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('article', 40)->unique();
            $table->string('designation', 200)->nullable();
            $table->foreignId('famille_id')->nullable()->constrained('familles')->nullOnDelete();
            // file | backfilled | unknown
            $table->string('famille_source', 20)->default('unknown');
            $table->string('unite_vente_frequente', 12)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
