<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\ArticleFamilyCorrection;
use App\Domain\Admin\ArticleWeightCorrection;
use App\Domain\Units\UnitWeightOrigin;
use App\Http\Controllers\Controller;
use App\Models\ArticleFamily;
use App\Models\ArticleUnitWeight;
use App\Models\Famille;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Section 4 (règles 2.8 / 2.6) — écran de correction des données par article :
 * poids d'une unité de vente, famille. « Preuve de fiabilité » : rien n'est
 * deviné silencieusement, tout est traçable et corrigeable.
 */
class DataCorrectionController extends Controller
{
    public function index(Request $request): Response
    {
        $onglet = $request->input('onglet') === 'familles' ? 'familles' : 'poids';
        $recherche = trim((string) $request->input('q', ''));
        $aConfirmer = $request->boolean('a_confirmer');

        return Inertia::render('Admin/Corrections', [
            'onglet' => $onglet,
            'filtres' => ['q' => $recherche, 'a_confirmer' => $aConfirmer],
            'familles' => Famille::orderBy('code')->get(['code', 'intitule']),
            'poids' => $onglet === 'poids' ? $this->poids($recherche, $aConfirmer) : null,
            'articleFamilles' => $onglet === 'familles' ? $this->familles($recherche, $aConfirmer) : null,
        ]);
    }

    private function poids(string $q, bool $aConfirmer)
    {
        return ArticleUnitWeight::query()
            ->when($q !== '', fn ($b) => $b->where('article', 'like', "%{$q}%")->orWhere('designation', 'like', "%{$q}%"))
            ->when($aConfirmer, fn ($b) => $b->aConfirmer())
            ->orderBy('article')
            ->paginate(30)->withQueryString()
            ->through(fn (ArticleUnitWeight $w) => [
                'article' => $w->article,
                'designation' => $w->designation,
                'unite_vente' => $w->unite_vente,
                'poids_unitaire_kg' => $w->poids_unitaire_kg,
                'origine' => $w->origine,
                'origine_libelle' => UnitWeightOrigin::libelle($w->origine),
                'en_conflit' => $w->en_conflit,
                'conflit_details' => $w->conflit_details,
                'corrige_le' => $w->corrige_le?->toDateTimeString(),
            ]);
    }

    private function familles(string $q, bool $aConfirmer)
    {
        return ArticleFamily::query()
            ->with('famille:id,code,intitule')
            ->when($q !== '', fn ($b) => $b->where('article', 'like', "%{$q}%")->orWhere('designation', 'like', "%{$q}%"))
            ->when($aConfirmer, fn ($b) => $b->aConfirmer())
            ->orderBy('article')
            ->paginate(30)->withQueryString()
            ->through(fn (ArticleFamily $f) => [
                'article' => $f->article,
                'designation' => $f->designation,
                'famille_code' => $f->famille_code,
                'famille_intitule' => $f->famille?->intitule,
                'statut' => $f->statut,
                'historique' => $f->historique,
                'corrige_le' => $f->corrige_le?->toDateTimeString(),
            ]);
    }

    public function updateWeight(Request $request, ArticleWeightCorrection $correction): RedirectResponse
    {
        $data = $request->validate([
            'article' => ['required', 'string', Rule::exists('article_unit_weights', 'article')],
            'poids_kg' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ]);

        $correction->corriger($data['article'], (float) $data['poids_kg'], $request->user());

        return back()->with('status', 'Poids corrigé, tonnages recalculés.');
    }

    public function updateFamily(Request $request, ArticleFamilyCorrection $correction): RedirectResponse
    {
        $data = $request->validate([
            'article' => ['required', 'string'],
            'famille_code' => ['required', Rule::exists('familles', 'code')],
        ]);

        $correction->corriger($data['article'], $data['famille_code'], $request->user());

        return back()->with('status', 'Famille corrigée.');
    }
}
