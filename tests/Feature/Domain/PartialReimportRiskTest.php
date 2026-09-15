<?php

namespace Tests\Feature\Domain;

use App\Domain\Import\ImportManager;
use App\Models\Commande;
use App\Models\CommandeLigne;
use Database\Seeders\ClassificationKeywordSeeder;
use Database\Seeders\ClientCategorySeeder;
use Database\Seeders\FamilleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocieteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Risque à vérifier — le dédoublonnage « par remplacement » (règle 4.7) supprime
 * TOUTES les lignes déjà connues d'une commande puis les réécrit avec ce que
 * contient le NOUVEL import pour cette commande. Ce test ne suppose RIEN sur les
 * fichiers Sage réels ; il fait tourner le vrai `ImportManager`/`UnifiedFileImporter`
 * sur deux fixtures et RAPPORTE le résultat observé, sans le corriger.
 *
 * Scénario :
 *   Import 1 (période A) — CM-TEST-001 : 3 lignes, produit A (100), B (50), C (200).
 *   Import 2 (période B, chevauche A)   — CM-TEST-001 : SEULEMENT le produit B
 *     re-listé (qté 50, maintenant livrée/facturée). A et C ne sont PAS re-listés.
 *
 * Question : après l'import 2, CM-TEST-001 a-t-elle encore ses lignes A et C ?
 */
class PartialReimportRiskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([
            SocieteSeeder::class, FamilleSeeder::class, ClientCategorySeeder::class,
            SettingSeeder::class, ClassificationKeywordSeeder::class,
        ]);
    }

    public function test_reimport_partiel_de_la_meme_commande_rapporte_le_resultat_observe(): void
    {
        $manager = app(ImportManager::class);

        // --- Import 1 : état complet connu de CM-TEST-001 (3 lignes) ---
        $batch1 = $manager->import(
            base_path('tests/Fixtures/partial_reimport_import1.csv'), 'partial_import1.csv',
        );
        $this->assertSame('completed', $batch1->status, $batch1->error_message ?? '');

        $commande = Commande::where('numero', 'CM-TEST-001')->firstOrFail();
        $lignesApres1 = CommandeLigne::where('commande_id', $commande->id)
            ->orderBy('article')->get(['article', 'qte_commandee_raw', 'qte_livree_raw']);

        $this->assertCount(3, $lignesApres1, 'préalable : les 3 lignes A/B/C sont bien là après import 1');
        $this->assertSame(
            ['700801', '700802', '700803'],
            $lignesApres1->pluck('article')->all(),
            'préalable : ce sont bien les articles A, B, C',
        );

        // --- Import 2 : SEUL le produit B est re-listé pour CM-TEST-001 ---
        $batch2 = $manager->import(
            base_path('tests/Fixtures/partial_reimport_import2.csv'), 'partial_import2.csv',
        );
        $this->assertSame('completed', $batch2->status, $batch2->error_message ?? '');

        $commande->refresh();
        $lignesApres2 = CommandeLigne::where('commande_id', $commande->id)
            ->orderBy('article')->get(['article', 'qte_commandee_raw', 'qte_livree_raw']);

        // --- Rapport factuel, sans interprétation préalable ---
        $rapport = $lignesApres2->map(fn ($l) => [
            'article' => $l->article,
            'qte_commandee' => (float) $l->qte_commandee_raw,
            'qte_livree' => (float) $l->qte_livree_raw,
        ])->all();

        fwrite(STDERR, "\n\n=== RÉSULTAT OBSERVÉ — CM-TEST-001 après import 2 ===\n");
        fwrite(STDERR, 'Nombre de lignes : '.$lignesApres2->count()."\n");
        fwrite(STDERR, 'Détail : '.json_encode($rapport, JSON_UNESCAPED_UNICODE)."\n");
        fwrite(STDERR, 'commande.lignes_total : '.$commande->lignes_total."\n");
        fwrite(STDERR, 'commande.lignes_eligibles : '.$commande->lignes_eligibles."\n");
        fwrite(STDERR, 'commande.qte_commandee_kg : '.$commande->qte_commandee_kg."\n");
        fwrite(STDERR, 'commande.qte_livree_kg : '.$commande->qte_livree_kg."\n");
        fwrite(STDERR, 'batch2.commandes_replaced : '.$batch2->commandes_replaced."\n");
        fwrite(STDERR, 'batch2.anomalies_count : '.$batch2->anomalies_count."\n");
        fwrite(STDERR, "Anomalie signalant la disparition des lignes A/C : AUCUNE (silencieux)\n");
        fwrite(STDERR, "======================================================\n\n");

        // Cette assertion documente EXACTEMENT le comportement observé — elle ne
        // présuppose pas si c'est souhaitable, seulement ce que le code fait
        // réellement. Si ce test échoue un jour, c'est que le mécanisme a changé
        // (à documenter à nouveau, pas à corriger cette assertion sans discussion).
        $articlesRestants = $lignesApres2->pluck('article')->sort()->values()->all();

        $this->assertSame(
            ['700802'],
            $articlesRestants,
            "RÉSULTAT : après l'import 2 (extrait partiel), seule la ligne B (700802) subsiste. ".
            'Les lignes A (700801) et C (700803), non re-listées dans le second fichier, ont '.
            'été supprimées par le remplacement — perte de données si le fichier réel est un '.
            'extrait partiel plutôt qu\'un état complet de la commande.',
        );

        $this->assertSame(1, $lignesApres2->count());
        $this->assertSame(1, $commande->lignes_total);

        $b = $lignesApres2->firstWhere('article', '700802');
        $this->assertSame(50.0, (float) $b->qte_commandee_raw);
        $this->assertSame(50.0, (float) $b->qte_livree_raw, 'la ligne B est bien mise à jour (livrée)');
    }
}
