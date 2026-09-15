import AdminNav from '@/Components/admin/AdminNav';
import { n1, nInt } from '@/Components/dashboard/format';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

function PoidsRow({ w }) {
    const [edit, setEdit] = useState(false);
    const form = useForm({ article: w.article, poids_kg: w.poids_unitaire_kg ?? '' });
    const submit = (e) => {
        e.preventDefault();
        form.put(route('admin.corrections.poids'), { preserveScroll: true, onSuccess: () => setEdit(false) });
    };

    return (
        <tr className={w.en_conflit || w.origine === 'non_determine' ? 'bg-amber-50 dark:bg-amber-950' : ''}>
            <td className="px-3 py-2 font-medium">{w.article}</td>
            <td className="px-3 py-2">{w.designation}</td>
            <td className="px-3 py-2">{w.unite_vente}</td>
            <td className="px-3 py-2">
                {w.poids_unitaire_kg != null ? `${n1(w.poids_unitaire_kg)} kg` : '—'}
            </td>
            <td className="px-3 py-2 text-xs">
                {w.origine_libelle}
                {w.en_conflit && (
                    <span className="ml-1 rounded bg-violet-100 dark:bg-violet-900/40 px-1 text-violet-800 dark:text-violet-200">conflit</span>
                )}
                {w.corrige_le && <div className="text-gray-400 dark:text-gray-500">corrigé {w.corrige_le}</div>}
            </td>
            <td className="px-3 py-2">
                {edit ? (
                    <form onSubmit={submit} className="flex items-center gap-1">
                        <input
                            type="number"
                            step="0.0001"
                            min="0"
                            value={form.data.poids_kg}
                            onChange={(e) => form.setData('poids_kg', e.target.value)}
                            className="w-24 rounded border-gray-300 dark:border-gray-700 text-sm"
                        />
                        <span className="text-xs">kg</span>
                        <button className="rounded bg-gray-800 dark:bg-gray-600 px-2 py-1 text-xs text-white">OK</button>
                        <button type="button" onClick={() => setEdit(false)} className="text-xs">
                            ✕
                        </button>
                    </form>
                ) : (
                    <button
                        onClick={() => setEdit(true)}
                        className="rounded border border-gray-300 dark:border-gray-700 px-2 py-1 text-xs hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        Corriger
                    </button>
                )}
            </td>
        </tr>
    );
}

function FamilleRow({ f, familles }) {
    const [edit, setEdit] = useState(false);
    const form = useForm({ article: f.article, famille_code: f.famille_code ?? '' });
    const submit = (e) => {
        e.preventDefault();
        form.put(route('admin.corrections.famille'), { preserveScroll: true, onSuccess: () => setEdit(false) });
    };

    return (
        <tr className={f.statut === 'ambigu' || !f.famille_code ? 'bg-amber-50 dark:bg-amber-950' : ''}>
            <td className="px-3 py-2 font-medium">{f.article}</td>
            <td className="px-3 py-2">{f.designation}</td>
            <td className="px-3 py-2">
                {f.famille_code ? `${f.famille_code} — ${f.famille_intitule}` : <em>inconnue</em>}
            </td>
            <td className="px-3 py-2 text-xs">
                {f.statut}
                {f.historique?.length > 1 && (
                    <div className="text-gray-400 dark:text-gray-500">
                        {f.historique
                            .map((h) => `${h.code} (${h.from}…${h.to})`)
                            .join(' → ')}
                    </div>
                )}
            </td>
            <td className="px-3 py-2">
                {edit ? (
                    <form onSubmit={submit} className="flex items-center gap-1">
                        <select
                            value={form.data.famille_code}
                            onChange={(e) => form.setData('famille_code', e.target.value)}
                            className="rounded border-gray-300 dark:border-gray-700 text-sm"
                        >
                            <option value="">—</option>
                            {familles.map((fm) => (
                                <option key={fm.code} value={fm.code}>
                                    {fm.code} — {fm.intitule}
                                </option>
                            ))}
                        </select>
                        <button className="rounded bg-gray-800 dark:bg-gray-600 px-2 py-1 text-xs text-white">OK</button>
                        <button type="button" onClick={() => setEdit(false)} className="text-xs">
                            ✕
                        </button>
                    </form>
                ) : (
                    <button
                        onClick={() => setEdit(true)}
                        className="rounded border border-gray-300 dark:border-gray-700 px-2 py-1 text-xs hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        Corriger
                    </button>
                )}
            </td>
        </tr>
    );
}

export default function Corrections({ onglet, filtres, familles, poids, articleFamilles }) {
    const flash = usePage().props.flash?.status;
    const [q, setQ] = useState(filtres.q ?? '');

    const nav = (patch) =>
        router.get(route('admin.corrections'), { onglet, ...filtres, ...patch }, { preserveScroll: true });

    const page = onglet === 'poids' ? poids : articleFamilles;

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Administration</h2>}
        >
            <Head title="Corrections données" />

            <div className="mx-auto max-w-7xl space-y-4 py-6 sm:px-6 lg:px-8">
                <AdminNav />
                {flash && (
                    <div className="banner-succes">{flash}</div>
                )}
                <p className="banner-info">
                    Preuve de fiabilité : rien n'est deviné silencieusement. Toute correction est
                    répercutée sur les données déjà importées et journalisée.
                </p>

                <div className="panel flex flex-wrap items-center gap-3 text-sm">
                    <div className="flex gap-1">
                        <button
                            onClick={() => router.get(route('admin.corrections'), { onglet: 'poids' })}
                            className={`rounded px-3 py-1 ${onglet === 'poids' ? 'bg-gray-800 dark:bg-gray-600 text-white' : 'bg-gray-100 dark:bg-gray-800'}`}
                        >
                            Poids unitaires (2.8)
                        </button>
                        <button
                            onClick={() => router.get(route('admin.corrections'), { onglet: 'familles' })}
                            className={`rounded px-3 py-1 ${onglet === 'familles' ? 'bg-gray-800 dark:bg-gray-600 text-white' : 'bg-gray-100 dark:bg-gray-800'}`}
                        >
                            Familles (2.6)
                        </button>
                    </div>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            nav({ q });
                        }}
                        className="flex gap-1"
                    >
                        <input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Article, désignation…"
                            className="rounded-md border-gray-300 dark:border-gray-700 text-sm"
                        />
                        <button className="rounded-md bg-gray-800 dark:bg-gray-600 px-3 py-1 text-white">OK</button>
                    </form>
                    <label className="flex items-center gap-1">
                        <input
                            type="checkbox"
                            checked={!!filtres.a_confirmer}
                            onChange={(e) => nav({ a_confirmer: e.target.checked ? 1 : null })}
                        />
                        À confirmer seulement
                    </label>
                </div>

                <section className="panel">
                    <div className="mb-2 text-sm text-gray-500 dark:text-gray-400">{nInt(page?.total ?? 0)} article(s)</div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                            <thead>
                                <tr className="text-left">
                                    <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Article</th>
                                    <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Désignation</th>
                                    {onglet === 'poids' ? (
                                        <>
                                            <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Unité</th>
                                            <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Poids retenu</th>
                                            <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Origine</th>
                                        </>
                                    ) : (
                                        <>
                                            <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Famille</th>
                                            <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Statut</th>
                                        </>
                                    )}
                                    <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {(page?.data ?? []).map((row) =>
                                    onglet === 'poids' ? (
                                        <PoidsRow key={row.article} w={row} />
                                    ) : (
                                        <FamilleRow key={row.article} f={row} familles={familles} />
                                    ),
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-1 text-sm">
                        {(page?.links ?? []).map((l, i) => (
                            <button
                                key={i}
                                disabled={!l.url}
                                onClick={() => l.url && router.visit(l.url, { preserveScroll: true })}
                                className={`rounded border px-2 py-1 ${l.active ? 'bg-gray-800 dark:bg-gray-600 text-white' : ''} disabled:opacity-30`}
                                dangerouslySetInnerHTML={{ __html: l.label }}
                            />
                        ))}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
