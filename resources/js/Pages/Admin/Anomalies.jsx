import AdminNav from '@/Components/admin/AdminNav';
import { DataTable } from '@/Components/dashboard/ChartCard';
import { nInt } from '@/Components/dashboard/format';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const SEVERITE_STYLE = {
    critical: 'bg-violet-100 dark:bg-violet-900/40 text-violet-800 dark:text-violet-200',
    warning: 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200',
    info: 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200',
};
const STATUT_LABEL = { a_verifier: 'À vérifier', validee: 'Validée (réintégrée)', exclue: 'Exclue' };

function Resolution({ anomalie, onDone }) {
    const form = useForm({ decision: 'exclue', note: '' });
    const submit = (e) => {
        e.preventDefault();
        form.post(route('admin.anomalies.resolve', anomalie.id), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form onSubmit={submit} className="mt-2 space-y-2 rounded-md bg-gray-50 dark:bg-gray-800/60 p-3 text-sm">
            {anomalie.corrigeable_ailleurs && (
                <p className="rounded bg-blue-50 dark:bg-blue-950 px-2 py-1 text-xs text-blue-800 dark:text-blue-200">
                    Cette anomalie se corrige sur l'écran <strong>Corrections données</strong>.
                    Ici, vous ne faites qu'enregistrer votre revue.
                </p>
            )}
            <div className="flex flex-wrap gap-4">
                <label className="flex items-center gap-1">
                    <input
                        type="radio"
                        checked={form.data.decision === 'validee'}
                        onChange={() => form.setData('decision', 'validee')}
                    />
                    Valider
                    {anomalie.type === 'outlier_quantite' && (
                        <span className="text-xs text-gray-500 dark:text-gray-400">
                            (réintègre la/les ligne(s) dans les indicateurs)
                        </span>
                    )}
                </label>
                <label className="flex items-center gap-1">
                    <input
                        type="radio"
                        checked={form.data.decision === 'exclue'}
                        onChange={() => form.setData('decision', 'exclue')}
                    />
                    Exclure définitivement
                </label>
            </div>
            <textarea
                value={form.data.note}
                onChange={(e) => form.setData('note', e.target.value)}
                placeholder="Note (facultative)"
                className="w-full rounded-md border-gray-300 dark:border-gray-700 text-sm"
                rows={2}
            />
            <div className="flex gap-2">
                <button
                    disabled={form.processing}
                    className="rounded-md bg-gray-800 dark:bg-gray-600 px-3 py-1 text-white disabled:opacity-40"
                >
                    Enregistrer la décision
                </button>
                <button type="button" onClick={onDone} className="rounded-md border px-3 py-1">
                    Annuler
                </button>
            </div>
        </form>
    );
}

export default function Anomalies({ anomalies, filtres, types, compteurs }) {
    const flash = usePage().props.flash?.status;
    const [open, setOpen] = useState(null);

    const filtrer = (patch) =>
        router.get(route('admin.anomalies'), { ...filtres, ...patch }, { preserveScroll: true });

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Administration</h2>}
        >
            <Head title="Anomalies" />

            <div className="mx-auto max-w-7xl space-y-4 py-6 sm:px-6 lg:px-8">
                <AdminNav />
                {flash && (
                    <div className="banner-succes">{flash}</div>
                )}

                <div className="panel flex flex-wrap items-center gap-3 text-sm">
                    <div className="flex gap-1">
                        {['a_verifier', 'validee', 'exclue'].map((s) => (
                            <button
                                key={s}
                                onClick={() => filtrer({ statut: s })}
                                className={`rounded px-3 py-1 ${
                                    filtres.statut === s ? 'bg-gray-800 dark:bg-gray-600 text-white' : 'bg-gray-100 dark:bg-gray-800'
                                }`}
                            >
                                {STATUT_LABEL[s]} ({nInt(compteurs[s] ?? 0)})
                            </button>
                        ))}
                    </div>
                    <select
                        value={filtres.type ?? ''}
                        onChange={(e) => filtrer({ type: e.target.value || null })}
                        className="rounded-md border-gray-300 dark:border-gray-700 text-sm"
                    >
                        <option value="">Tous les types</option>
                        {types.map((t) => (
                            <option key={t} value={t}>
                                {t}
                            </option>
                        ))}
                    </select>
                </div>

                <section className="panel">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                            <thead>
                                <tr className="text-left">
                                    <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Type</th>
                                    <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Article</th>
                                    <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Description</th>
                                    <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Sévérité</th>
                                    <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Statut</th>
                                    <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {anomalies.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="px-3 py-4 text-gray-500 dark:text-gray-400">
                                            Aucune anomalie.
                                        </td>
                                    </tr>
                                )}
                                {anomalies.data.map((a) => (
                                    <tr key={a.id}>
                                        <td className="px-3 py-2 font-mono text-xs align-top">{a.type}</td>
                                        <td className="px-3 py-2 align-top">{a.article ?? '—'}</td>
                                        <td className="px-3 py-2 align-top">
                                            {a.resume}
                                            {a.note && (
                                                <div className="text-xs text-gray-500 dark:text-gray-400">Note : {a.note}</div>
                                            )}
                                            {a.resolu_par && (
                                                <div className="text-xs text-gray-400 dark:text-gray-500">
                                                    Résolu par {a.resolu_par} le {a.resolu_le}
                                                </div>
                                            )}
                                            {open === a.id && (
                                                <Resolution anomalie={a} onDone={() => setOpen(null)} />
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <span
                                                className={`rounded px-2 py-0.5 text-xs ${SEVERITE_STYLE[a.severity]}`}
                                            >
                                                {a.severity}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 align-top">{STATUT_LABEL[a.statut]}</td>
                                        <td className="px-3 py-2 align-top">
                                            {a.statut === 'a_verifier' && (
                                                <button
                                                    onClick={() => setOpen(open === a.id ? null : a.id)}
                                                    className="rounded border border-gray-300 dark:border-gray-700 px-2 py-1 text-xs hover:bg-gray-50 dark:hover:bg-gray-800"
                                                >
                                                    Trancher
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <Pagination links={anomalies.links} />
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function Pagination({ links }) {
    if (!links || links.length <= 3) return null;
    return (
        <div className="mt-3 flex flex-wrap gap-1 text-sm">
            {links.map((l, i) => (
                <button
                    key={i}
                    disabled={!l.url}
                    onClick={() => l.url && router.visit(l.url, { preserveScroll: true })}
                    className={`rounded border px-2 py-1 ${l.active ? 'bg-gray-800 dark:bg-gray-600 text-white' : ''} disabled:opacity-30`}
                    dangerouslySetInnerHTML={{ __html: l.label }}
                />
            ))}
        </div>
    );
}
