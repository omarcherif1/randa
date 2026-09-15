import { Deferred, router } from '@inertiajs/react';
import { useState } from 'react';
import { dateFr, ETATS, n1, nInt } from '@/Components/dashboard/format';

/**
 * Table des commandes — recherche, tri, pagination CÔTÉ SERVEUR (exigence 7 :
 * aucune liste chargée en intégralité). Chargée en différé (après les KPI) puis
 * rechargée via un rechargement partiel Inertia (`only: ['commandes']`).
 */
export default function CommandesTable({ commandes }) {
    return (
        <Deferred
            data="commandes"
            fallback={
                <section className="panel text-sm text-gray-500 dark:text-gray-400">
                    Chargement de la liste des commandes…
                </section>
            }
        >
            {commandes ? <Table commandes={commandes} /> : null}
        </Deferred>
    );
}

function Table({ commandes }) {
    const [q, setQ] = useState(commandes?.meta?.q ?? '');

    const reload = (params) => {
        router.reload({
            only: ['commandes'],
            data: { ...currentParams(), ...params },
        });
    };

    const currentParams = () => {
        const m = commandes?.meta ?? {};
        return { tri: m.tri, sens: m.sens, q, page: m.page };
    };

    const sortBy = (col) => {
        const m = commandes.meta;
        const sens = m.tri === col && m.sens === 'desc' ? 'asc' : 'desc';
        reload({ tri: col, sens, page: 1 });
    };

    const submitSearch = (e) => {
        e.preventDefault();
        reload({ q, page: 1 });
    };

    const { lignes, meta } = commandes;
    const Th = ({ col, children }) => (
        <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">
            <button type="button" onClick={() => sortBy(col)} className="inline-flex items-center gap-1 hover:underline">
                {children}
                {meta.tri === col && <span>{meta.sens === 'desc' ? '▼' : '▲'}</span>}
            </button>
        </th>
    );

    return (
        <section className="panel">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 className="font-semibold text-gray-800 dark:text-gray-100">Commandes ({nInt(meta.total)})</h3>
                <form onSubmit={submitSearch} className="flex gap-2">
                    <input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="N° commande, client, ville…"
                        className="rounded-md border-gray-300 dark:border-gray-700 text-sm"
                    />
                    <button className="rounded-md bg-gray-800 dark:bg-gray-600 px-3 py-1 text-sm text-white">Rechercher</button>
                </form>
            </div>

            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                    <thead>
                        <tr className="text-left">
                            <Th col="numero">N°</Th>
                            <Th col="date_commande">Date</Th>
                            <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Société</th>
                            <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Client</th>
                            <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Ville</th>
                            <Th col="etat">État</Th>
                            <Th col="qte_livree_kg">Livré (t)</Th>
                            <Th col="non_livre">Non livré (t)</Th>
                            <th className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">Satisf.</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                        {lignes.data.length === 0 && (
                            <tr>
                                <td colSpan={9} className="px-3 py-4 text-gray-500 dark:text-gray-400">
                                    Aucune commande pour ces filtres.
                                </td>
                            </tr>
                        )}
                        {lignes.data.map((c) => (
                            <tr key={c.id} className={c.is_excluded ? 'text-gray-400 dark:text-gray-500' : ''}>
                                <td className="px-3 py-2 font-medium">{c.numero}</td>
                                <td className="px-3 py-2">{dateFr(c.date_commande)}</td>
                                <td className="px-3 py-2">{c.societe}</td>
                                <td className="px-3 py-2">{c.client}</td>
                                <td className="px-3 py-2">{c.ville}</td>
                                <td className="px-3 py-2">
                                    {ETATS[c.etat] ?? c.etat}
                                    {c.etat_incertain && (
                                        <span className="ml-1 text-xs text-amber-600 dark:text-amber-400" title="Réservé ou expédié, indéterminable">
                                            (incertain)
                                        </span>
                                    )}
                                </td>
                                <td className="px-3 py-2">{n1(c.tonnes_livrees)}</td>
                                <td className="px-3 py-2">
                                    {c.tonnes_non_livrees > 0 ? (
                                        <span className="text-violet-600 dark:text-violet-400">{n1(c.tonnes_non_livrees)}</span>
                                    ) : (
                                        '—'
                                    )}
                                </td>
                                <td className="px-3 py-2">
                                    {c.has_outlier ? '⚠' : c.satisfaite ? '✓' : '✗'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="mt-3 flex items-center justify-between text-sm">
                <span className="text-gray-500 dark:text-gray-400">
                    Page {meta.page} / {meta.dernier}
                </span>
                <div className="flex gap-1">
                    <button
                        disabled={meta.page <= 1}
                        onClick={() => reload({ page: meta.page - 1 })}
                        className="rounded border border-gray-300 dark:border-gray-700 px-2 py-1 disabled:opacity-40"
                    >
                        Précédent
                    </button>
                    <button
                        disabled={meta.page >= meta.dernier}
                        onClick={() => reload({ page: meta.page + 1 })}
                        className="rounded border border-gray-300 dark:border-gray-700 px-2 py-1 disabled:opacity-40"
                    >
                        Suivant
                    </button>
                </div>
            </div>
        </section>
    );
}
