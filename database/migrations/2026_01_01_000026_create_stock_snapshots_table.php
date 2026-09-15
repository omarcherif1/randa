<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règle 2.5 méthode A — photo du stock disponible par article à une date.
 * Alimenté par le fichier de stock journalier (2e format d'import, section 4.7),
 * si le site le fournit. Module signalé "priorité basse" : version simple.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('article', 40)->index();
            $table->foreignId('produit_id')->nullable()->constrained('produits')->nullOnDelete();
            $table->date('date_stock')->index();
            $table->decimal('quantite_kg', 18, 3);
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['article', 'date_stock']); // ré-import => remplacement
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_snapshots');
    }
};
