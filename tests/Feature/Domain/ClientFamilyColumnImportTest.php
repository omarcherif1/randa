<?php

namespace Tests\Feature\Domain;

use App\Domain\Classification\ClientCategoryResolution;
use App\Domain\Import\ColumnMap;
use App\Domain\Import\CsvReader;
use App\Domain\Import\ImportManager;
use App\Models\Anomalie;
use App\Models\Client;
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
 * Règle 2.7 — extraction F1790079066766 : le fichier porte « Client » suivi d'une
 * « Famille statistique » CLIENT = code catégorie. La fixture reprend l'en-tête
 * réel (UTF-8, accents déjà remplacés par « � » à l'extraction).
 */
class ClientFamilyColumnImportTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE = 'tests/Fixtures/famille_client_utf8.csv';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([
            SocieteSeeder::class, FamilleSeeder::class, ClientCategorySeeder::class,
            SettingSeeder::class, ClassificationKeywordSeeder::class,
        ]);
    }

    public function test_en_tete_utf8_aux_accents_perdus_est_reconnu(): void
    {
        $reader = new CsvReader(base_path(self::FIXTURE));
        $columns = new ColumnMap($reader->header());

        $this->assertSame('UTF-8', $reader->sourceEncoding);
        $this->assertTrue($reader->accentsPerdus);
        $this->assertSame([], $columns->missingFields());
        $this->assertSame(25, $columns->clientFamilyColumn);
        $this->assertNotContains(25, $columns->familyColumnCandidates, 'la famille CLIENT n\'est pas une famille article');
    }

    public function test_ancien_fichier_windows_1252_sans_famille_client(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, mb_convert_encoding(
            "Site vente;Désignation 1;Quantité commandée;Famille statistique\nRAN01;Farine;10;106\n",
            'Windows-1252', 'UTF-8',
        ));
        $reader = new CsvReader($path);
        $header = $reader->header();
        $columns = new ColumnMap($header);
        unlink($path);

        $this->assertSame('Windows-1252', $reader->sourceEncoding);
        $this->assertSame('Désignation 1', $header[1] ?? null);
        $this->assertNull($columns->clientFamilyColumn);
        $this->assertSame([3], $columns->familyColumnCandidates);
    }

    public function test_la_famille_client_du_fichier_tranche_la_categorie(): void
    {
        $batch = app(ImportManager::class)->import(base_path(self::FIXTURE), 'famille_client.csv');
        $this->assertSame('completed', $batch->status, $batch->error_message ?? '');

        $cat = fn (string $numero) => Client::where('numero', $numero)->with('category')->firstOrFail();

        // le code du fichier prime sur le mot-clé « BOULANGERIE » (108)
        $a = $cat('CLI-A');
        $this->assertSame('107', $a->category->code);
        $this->assertSame(ClientCategoryResolution::SOURCE_REFERENCE, $a->category_source);
        $this->assertSame(ClientCategoryResolution::DETAIL_FICHIER, $a->category_detail);

        // case vide : on retombe sur les mots-clés du nom
        $b = $cat('CLI-B');
        $this->assertSame('107', $b->category->code);
        $this->assertSame(ClientCategoryResolution::SOURCE_KEYWORD, $b->category_source);

        // code inconnu : ignoré (jamais inventé) et signalé
        $c = $cat('CLI-C');
        $this->assertSame(ClientCategoryResolution::SOURCE_UNDEFINED, $c->category_source);
        $this->assertTrue(Anomalie::where('type', 'categorie_client_inconnue')
            ->where('signature', 'categorie_client_inconnue:CLI-C:555')->exists());

        // « Ste du Groupe » (110) : exclue des indicateurs (règle 2.1 hors périmètre)
        $this->assertSame('110', $cat('CLI-D')->category->code);
        $ligne = CommandeLigne::whereHas('commande', fn ($q) => $q->where('numero', 'CMRAN012690004'))->firstOrFail();
        $this->assertFalse((bool) $ligne->is_eligible);
        $this->assertNotNull($ligne->exclusion_reason);
    }

    public function test_un_client_classe_par_mot_cle_est_reclasse_par_le_fichier(): void
    {
        Client::create([
            'numero' => 'CLI-A', 'nom' => 'BOULANGERIE DU COIN',
            'client_category_id' => \App\Models\ClientCategory::where('code', '108')->value('id'),
            'category_source' => ClientCategoryResolution::SOURCE_KEYWORD,
        ]);

        app(ImportManager::class)->import(base_path(self::FIXTURE), 'famille_client.csv');

        $a = Client::where('numero', 'CLI-A')->with('category')->firstOrFail();
        $this->assertSame('107', $a->category->code);
        $this->assertSame(ClientCategoryResolution::SOURCE_REFERENCE, $a->category_source);
    }
}
