<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Décision de l'encadreur (section 10) — le seuil « date_livraison_fiable »
 * n'a plus de sens : la date de facture sert désormais systématiquement de
 * proxy pour le retard, décidé commande par commande (présence d'une date de
 * facture), plus par un interrupteur global. Voir DelayCalculator (règle 2.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'date_livraison_fiable')->delete();
    }

    public function down(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'date_livraison_fiable'],
            [
                'value' => json_encode(['v' => false]),
                'type' => 'bool',
                'libelle' => 'Date de livraison fiable disponible',
                'description' => 'Règle 2.4 — obsolète, conservé pour compatibilité descendante.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
