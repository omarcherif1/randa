<?php

namespace App\Domain\Admin;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Import\ReferenceData;
use App\Domain\Reporting\MetricsCache;
use App\Models\ClassificationKeyword;
use App\Models\Client;
use App\Models\ClientCategory;
use App\Models\User;
use App\Support\Text;
use Illuminate\Support\Facades\DB;

/**
 * Règle 2.7 point 3 / section 4.9 — gestion des mots-clés de reconnaissance du
 * type de client, SANS intervention technique.
 *
 * Un mot-clé est stocké normalisé (majuscules, sans accents). Modifier la liste
 * ne reclasse PAS automatiquement les clients (règle 2.7 : un client n'est
 * classifié qu'une fois) — un bouton dédié permet de reclasser les clients
 * encore « Indéfini » ou classés par mot-clé.
 */
final class KeywordService
{
    public function __construct(private readonly AuditLogger $audit = new AuditLogger) {}

    public function ajouter(string $categoryCode, string $keyword, int $priorite, User $admin): ClassificationKeyword
    {
        $categorie = ClientCategory::where('code', $categoryCode)->firstOrFail();
        $normalise = Text::normalize($keyword);
        if ($normalise === '') {
            throw new \InvalidArgumentException('Mot-clé vide.');
        }

        $kw = ClassificationKeyword::updateOrCreate(
            ['client_category_id' => $categorie->id, 'keyword' => $normalise],
            ['priorite' => $priorite, 'actif' => true],
        );

        $this->journaliser($admin, "Mot-clé « {$normalise} » → {$categorie->intitule} (priorité {$priorite})");

        return $kw;
    }

    public function modifier(ClassificationKeyword $kw, int $priorite, bool $actif, User $admin): void
    {
        $kw->forceFill(['priorite' => $priorite, 'actif' => $actif])->save();
        $this->journaliser($admin, "Mot-clé « {$kw->keyword} » : priorité {$priorite}, ".($actif ? 'actif' : 'inactif'));
    }

    public function supprimer(ClassificationKeyword $kw, User $admin): void
    {
        $mot = $kw->keyword;
        $kw->delete();
        $this->journaliser($admin, "Mot-clé « {$mot} » supprimé");
    }

    /**
     * Reclasse les clients dont la catégorie n'a PAS été déterminée par une
     * source fiable (uniquement « Indéfini » ou « mot-clé »). Ne touche jamais
     * `reference` / `deduced`.
     */
    public function reclasserNonTranches(User $admin): int
    {
        $ref = ReferenceData::load();
        $classifier = $ref->clientClassifier();
        $modifies = 0;

        DB::transaction(function () use ($classifier, $ref, &$modifies) {
            Client::query()
                ->whereIn('category_source', ['undefined', 'keyword'])
                ->chunkById(500, function ($clients) use ($classifier, $ref, &$modifies) {
                    foreach ($clients as $client) {
                        $r = $classifier->classify($client->numero, $client->nom);
                        $catId = $r->categoryCode !== null ? ($ref->categoryIdByCode[$r->categoryCode] ?? null) : null;

                        if ($client->client_category_id !== $catId || $client->category_source !== $r->source) {
                            $client->forceFill([
                                'client_category_id' => $catId,
                                'category_source' => $r->source,
                                'category_detail' => $r->detail,
                            ])->save();
                            $modifies++;
                        }
                    }
                });
        });

        MetricsCache::bump();

        $this->audit->record(
            AuditAction::CLIENTS_RECLASSES,
            "Reclassement des clients non tranchés — {$modifies} client(s) modifié(s)",
            null, ['modifies' => $modifies], $admin,
        );

        return $modifies;
    }

    private function journaliser(User $admin, string $description): void
    {
        $this->audit->record(AuditAction::MOTS_CLES_MODIFIES, $description, null, [], $admin);
    }
}
