<?php

namespace App\Domain\Import;

use App\Domain\Classification\ClientCategoryClassifier;
use App\Domain\Classification\FamilyConsolidation;
use App\Domain\Classification\FamilyResolver;
use App\Domain\Units\ArticleUnitResolver;
use App\Models\ArticleFamily;
use App\Models\ArticleUnitWeight;
use App\Models\ClassificationKeyword;
use App\Models\ClientCategory;
use App\Models\Famille;
use App\Models\Setting;
use App\Models\Societe;

/**
 * Charge une seule fois toutes les données de référence nécessaires à la
 * normalisation d'une ligne (règles section 2), et fabrique les services
 * de classification correctement câblés.
 */
final class ReferenceData
{
    /** @var array<string, int> code société => id */
    public array $societeIdByCode;

    /** @var array<string, int> code famille => id */
    public array $familleIdByCode;

    /** @var array<string, int> code catégorie => id */
    public array $categoryIdByCode;

    /** @var list<string> */
    public array $familleCodes;

    public string $exportCategoryCode;

    public string $fallbackCategoryCode;

    /** @var array<string, string> code catégorie "société du groupe" */
    public array $groupCategoryCodes;

    private function __construct() {}

    public static function load(): self
    {
        $self = new self;

        $self->societeIdByCode = Societe::pluck('id', 'code')->all();
        $self->familleIdByCode = Famille::pluck('id', 'code')->all();
        $self->categoryIdByCode = ClientCategory::pluck('id', 'code')->all();
        $self->familleCodes = array_map('strval', array_keys($self->familleIdByCode));

        $export = ClientCategory::where('is_export', true)->first();
        $fallback = ClientCategory::where('is_fallback', true)->first();
        $self->exportCategoryCode = $export?->code ?? '109';
        $self->fallbackCategoryCode = $fallback?->code ?? '199';

        // "Ste du Groupe" — utilisée pour l'exclusion "hors périmètre" (règle 2.1)
        $self->groupCategoryCodes = ClientCategory::where('intitule', 'like', '%groupe%')
            ->orWhere('intitule_court', 'like', '%Grp%')
            ->pluck('code', 'code')->all();

        return $self;
    }

    public function articleUnitResolver(): ArticleUnitResolver
    {
        // amorce la table article => poids unitaire avec ce qui est déjà connu/validé
        $map = ArticleUnitWeight::all()->mapWithKeys(fn (ArticleUnitWeight $w) => [
            $w->article => [
                'poids' => $w->poids_unitaire_kg,
                'origine' => $w->origine,
                'confirme' => $w->poidsFiable(),
                'designation_ref' => $w->designation_reference,
            ],
        ])->all();

        return new ArticleUnitResolver(
            map: $map,
            quintalHypotheseKg: (float) Setting::get('qx_poids_kg_hypothese', 100),
        );
    }

    public function familyResolver(): FamilyResolver
    {
        // Mémoire = correspondances code article COMPLET => famille déjà consolidées
        // (imports précédents / corrections admin). Les articles "ambigus" sans
        // valeur tranchée ne sont pas amorcés : ils resteront "inconnus" tant
        // qu'un admin n'a pas confirmé.
        $memoire = ArticleFamily::whereNotNull('famille_code')
            ->where('statut', '!=', FamilyConsolidation::STATUT_AMBIGU)
            ->pluck('famille_code', 'article')
            ->all();

        return new FamilyResolver($this->familleCodes, $memoire);
    }

    public function clientClassifier(array $referenceMap = []): ClientCategoryClassifier
    {
        $keywords = ClassificationKeyword::query()
            ->where('actif', true)
            ->with('category:id,code')
            ->get()
            ->map(fn (ClassificationKeyword $k) => [
                'code' => $k->category?->code,
                'keyword' => $k->keyword,
                'priorite' => (int) $k->priorite,
            ])
            ->filter(fn ($e) => $e['code'] !== null)
            ->values()
            ->all();

        return new ClientCategoryClassifier(
            referenceMap: $referenceMap,
            keywords: $keywords,
            exportCategoryCode: $this->exportCategoryCode,
            fallbackCategoryCode: $this->fallbackCategoryCode,
        );
    }

    public function familleColonneForcee(): ?int
    {
        $v = Setting::get('famille_colonne_index');

        return $v === null ? null : (int) $v;
    }

    public function isGroupCategory(?string $code): bool
    {
        return $code !== null && isset($this->groupCategoryCodes[$code]);
    }
}
