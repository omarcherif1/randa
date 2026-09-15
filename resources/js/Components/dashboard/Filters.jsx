import MultiSelectDropdown from '@/Components/dashboard/MultiSelectDropdown';
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

/**
 * Filtres du tableau de bord (règle 2.10) — les 4 filtres à choix (société,
 * produit, type de client, type famille) gardent l'apparence d'un `<select>`
 * simple (une ligne, même style) mais acceptent plusieurs valeurs à la fois
 * via `MultiSelectDropdown` (panneau de cases à cocher, pas un `<select
 * multiple>` natif — toujours ouvert en liste haute, look différent).
 * Les changements sont REGROUPÉS : rien n'est recalculé tant qu'on ne clique
 * pas sur « Appliquer » (amélioration V2 — pas de recalcul lourd à chaque clic).
 */
export default function Filters({ options, filtres }) {
    const initial = useMemo(
        () => ({
            societes: filtres.societes || [],
            articles: filtres.articles || [],
            client_categories: filtres.client_categories || [],
            familles: filtres.familles || [],
            from: filtres.from || '',
            to: filtres.to || '',
        }),
        [filtres],
    );

    const [draft, setDraft] = useState(initial);
    const dirty = JSON.stringify(draft) !== JSON.stringify(initial);

    const definir = (key) => (valeurs) => setDraft((d) => ({ ...d, [key]: valeurs }));

    const apply = () => {
        const params = {};
        if (draft.societes.length) params.societes = draft.societes;
        if (draft.articles.length) params.articles = draft.articles;
        if (draft.client_categories.length) params.client_categories = draft.client_categories;
        if (draft.familles.length) params.familles = draft.familles;
        if (draft.from) params.from = draft.from;
        if (draft.to) params.to = draft.to;
        router.get(route('dashboard'), params, { preserveState: true, preserveScroll: true });
    };

    const reset = () => router.get(route('dashboard'), {}, { preserveScroll: true });

    return (
        <div className="panel">
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <MultiSelectDropdown
                    label="Société"
                    value={draft.societes}
                    onChange={definir('societes')}
                    options={options.societes}
                    getKey={(s) => s.code}
                    getValue={(s) => s.code}
                    getLabel={(s) => s.nom}
                    placeholder="Toutes"
                />

                <MultiSelectDropdown
                    label="Produit"
                    value={draft.articles}
                    onChange={definir('articles')}
                    options={options.articles}
                    getKey={(a) => a.article}
                    getValue={(a) => a.article}
                    getLabel={(a) => `${a.article} — ${a.designation}`}
                    placeholder="Tous"
                />

                <MultiSelectDropdown
                    label="Type de client"
                    value={draft.client_categories}
                    onChange={definir('client_categories')}
                    options={options.client_categories}
                    getKey={(c) => c.code}
                    getValue={(c) => c.code}
                    getLabel={(c) => c.intitule}
                    placeholder="Tous"
                />

                <MultiSelectDropdown
                    label="Type famille"
                    value={draft.familles}
                    onChange={definir('familles')}
                    options={options.familles}
                    getKey={(f) => f.code}
                    getValue={(f) => f.code}
                    getLabel={(f) => f.intitule}
                    placeholder="Toutes"
                />
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <label className="text-sm">
                    <span className="font-medium text-gray-700 dark:text-gray-300">Du</span>
                    <input
                        type="date"
                        className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm"
                        value={draft.from}
                        onChange={(e) => setDraft((d) => ({ ...d, from: e.target.value }))}
                    />
                </label>
                <label className="text-sm">
                    <span className="font-medium text-gray-700 dark:text-gray-300">Au</span>
                    <input
                        type="date"
                        className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm"
                        value={draft.to}
                        onChange={(e) => setDraft((d) => ({ ...d, to: e.target.value }))}
                    />
                </label>
            </div>

            <div className="mt-4 flex items-center gap-2">
                <button
                    type="button"
                    onClick={apply}
                    disabled={!dirty}
                    className="rounded-md bg-gray-800 dark:bg-gray-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-40"
                >
                    Appliquer
                </button>
                <button
                    type="button"
                    onClick={reset}
                    className="rounded-md border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800"
                >
                    Réinitialiser
                </button>
                {dirty && (
                    <span className="text-xs text-amber-600 dark:text-amber-400">
                        Filtres modifiés, pas encore appliqués.
                    </span>
                )}
            </div>
        </div>
    );
}
