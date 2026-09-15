<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\KeywordService;
use App\Http\Controllers\Controller;
use App\Models\ClassificationKeyword;
use App\Models\ClientCategory;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page Paramètres (section 4.9) — seuils réglables + mots-clés de classification
 * client (règle 2.7). Toute modification est journalisée dans l'audit.
 */
class SettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings', [
            'settings' => Setting::orderBy('key')->get(['key', 'value', 'type', 'libelle', 'description'])
                ->map(fn (Setting $s) => [
                    'key' => $s->key,
                    'type' => $s->type,
                    'libelle' => $s->libelle,
                    'description' => $s->description,
                    'value' => $s->typedValue(),
                ]),
            'categories' => ClientCategory::orderBy('intitule')->get(['id', 'code', 'intitule']),
            'motsCles' => ClassificationKeyword::with('category:id,code,intitule')
                ->orderBy('client_category_id')->orderByDesc('priorite')->orderBy('keyword')
                ->get()
                ->map(fn (ClassificationKeyword $k) => [
                    'id' => $k->id,
                    'categorie_code' => $k->category?->code,
                    'categorie' => $k->category?->intitule,
                    'keyword' => $k->keyword,
                    'priorite' => $k->priorite,
                    'actif' => $k->actif,
                ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['key' => ['required', 'string'], 'value' => ['present']]);

        $setting = Setting::where('key', $request->string('key'))->firstOrFail();
        $value = $this->cast($request->input('value'), $setting->type);

        Setting::put($setting->key, $value); // journalisé

        return back()->with('status', 'Paramètre enregistré.');
    }

    public function storeKeyword(Request $request, KeywordService $service): RedirectResponse
    {
        $data = $request->validate([
            'categorie_code' => ['required', Rule::exists('client_categories', 'code')],
            'keyword' => ['required', 'string', 'max:80'],
            'priorite' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $service->ajouter($data['categorie_code'], $data['keyword'], $data['priorite'], $request->user());

        return back()->with('status', 'Mot-clé ajouté.');
    }

    public function updateKeyword(Request $request, ClassificationKeyword $keyword, KeywordService $service): RedirectResponse
    {
        $data = $request->validate([
            'priorite' => ['required', 'integer', 'min:0', 'max:100'],
            'actif' => ['boolean'],
        ]);

        $service->modifier($keyword, $data['priorite'], (bool) ($data['actif'] ?? false), $request->user());

        return back()->with('status', 'Mot-clé mis à jour.');
    }

    public function destroyKeyword(Request $request, ClassificationKeyword $keyword, KeywordService $service): RedirectResponse
    {
        $service->supprimer($keyword, $request->user());

        return back()->with('status', 'Mot-clé supprimé.');
    }

    public function reclassifyClients(Request $request, KeywordService $service): RedirectResponse
    {
        $n = $service->reclasserNonTranches($request->user());

        return back()->with('status', "{$n} client(s) reclassé(s).");
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => $value === null || $value === '' ? null : (int) $value,
            'float' => $value === null || $value === '' ? null : (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }
}
