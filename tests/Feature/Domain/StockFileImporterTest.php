<?php

namespace Tests\Feature\Domain;

use App\Domain\Import\ImportManager;
use App\Models\ImportBatch;
use App\Models\StockSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Étape 2 — 2e format d'import : fichier de stock journalier (règle 2.5 méthode A).
 */
class StockFileImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function import(): ImportBatch
    {
        return app(ImportManager::class)->import(
            base_path('tests/Fixtures/stock_sample.csv'),
            'stock_sample.csv',
            ImportManager::TYPE_STOCK,
        );
    }

    public function test_import_stock_cree_les_snapshots(): void
    {
        $batch = $this->import();

        $this->assertSame('completed', $batch->status);
        $this->assertSame(3, StockSnapshot::count());
        $this->assertSame(12000.0, StockSnapshot::where('article', '113231')->value('quantite_kg'));
    }

    public function test_reimport_remplace_la_meme_paire_article_date(): void
    {
        $this->import();
        $this->import();

        $this->assertSame(3, StockSnapshot::count(), 'pas de doublon (article, date)');
    }
}
