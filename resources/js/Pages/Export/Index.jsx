import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AideIndicateur from '@/Components/AideIndicateur';
import Kpi from '@/Components/Kpi';
import { DataTable } from '@/Components/dashboard/ChartCard';
import { dateFr, nInt, pct, tonnes } from '@/Components/dashboard/format';
import { couleurSociete, useThemeSombre } from '@/Support/theme';
import { Deferred, Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Export({ metrics, commandes }) {
    const { kpi } = metrics;
    const sombre = useThemeSombre();

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Export</h2>}
        >
            <Head title="Export" />

            <div className="mx-auto max-w-7xl space-y-6 py-6 sm:px-6 lg:px-8">
                <p className="banner-info flex items-start gap-1">
                    <span>{metrics.perimetre}</span>
                    <AideIndicateur cle="export" />
                </p>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Kpi label="Commandes export" value={nInt(kpi.commandes)} />
                    <Kpi label="Clients export" value={nInt(kpi.clients)} />
                    <Kpi label="Tonnes exportées" value={tonnes(kpi.tonnes_exportees)} />
                    <Kpi label="Satisfaction export" value={pct(kpi.taux_satisfaction)} />
                </div>

                <section className="panel">
                    <h3 className="font-semibold text-gray-800 dark:text-gray-100">
                        Taux de satisfaction export par société
                    </h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        Même définition qu'en local (commandes satisfaites / commandes), jamais
                        mélangé avec le local.
                    </p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-3">
                        {metrics.satisfaction_par_societe.map((s) => {
                            const couleur = couleurSociete(s.couleur, sombre);
                            return (
                                <div
                                    key={s.code}
                                    className="rounded-md border-l-4 bg-gray-50 p-3 dark:bg-gray-800/60"
                                    style={{ borderColor: couleur }}
                                >
                                    <div className="text-sm font-medium text-gray-600 dark:text-gray-400">{s.nom}</div>
                                    <div className="text-2xl font-bold tabular-nums" style={{ color: couleur }}>
                                        {pct(s.taux)}
                                    </div>
                                    <div className="text-xs text-gray-400 dark:text-gray-500">
                                        {nInt(s.satisfaites)} / {nInt(s.commandes)} commandes satisfaites
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </section>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Panel title="Top destinations">
                        <DataTable
                            columns={[
                                { key: 'label', label: 'Destination' },
                                { key: 'tonnes', label: 'Tonnes', render: (r) => tonnes(r.tonnes) },
                            ]}
                            rows={metrics.top_destinations}
                        />
                    </Panel>
                    <Panel title="Top clients export">
                        <DataTable
                            columns={[
                                { key: 'nom', label: 'Client' },
                                { key: 'numero', label: 'N°' },
                                { key: 'tonnes', label: 'Tonnes', render: (r) => tonnes(r.tonnes) },
                            ]}
                            rows={metrics.top_clients}
                        />
                    </Panel>
                    <Panel title="Top produits export">
                        <DataTable
                            columns={[
                                { key: 'label', label: 'Produit' },
                                { key: 'tonnes', label: 'Tonnes', render: (r) => tonnes(r.tonnes) },
                            ]}
                            rows={metrics.top_produits}
                        />
                    </Panel>
                </div>

                <Deferred
                    data="commandes"
                    fallback={<section className="panel text-sm text-gray-500 dark:text-gray-400">Chargement des commandes export…</section>}
                >
                    {commandes ? <Table commandes={commandes} /> : null}
                </Deferred>
            </div>
        </AuthenticatedLayout>
    );
}

function Panel({ title, children }) {
    return (
        <section className="panel">
            <h3 className="mb-2 font-semibold text-gray-800 dark:text-gray-100">{title}</h3>
            <div className="overflow-x-auto">{children}</div>
        </section>
    );
}

function Table({ commandes }) {
    const { lignes, meta } = commandes;
    const [q, setQ] = useState(meta.q ?? '');

    const reload = (params) =>
        router.reload({
            only: ['commandes'],
            data: { tri: meta.tri, sens: meta.sens, q, page: meta.page, ...params },
        });

    return (
        <section className="panel">
            <div className="mb-3 flex items-center justify-between gap-2">
                <h3 className="font-semibold text-gray-800 dark:text-gray-100">
                    Commandes export ({nInt(meta.total)})
                </h3>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        reload({ q, page: 1 });
                    }}
                    className="flex gap-1"
                >
                    <input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="N°, client, destination…"
                        className="rounded-md border-gray-300 text-sm dark:border-gray-700"
                    />
                    <button className="rounded-md bg-gray-800 px-3 py-1 text-sm text-white dark:bg-gray-600">
                        Rechercher
                    </button>
                </form>
            </div>

            <div className="overflow-x-auto">
                <DataTable
                    columns={[
                        { key: 'numero', label: 'N°' },
                        { key: 'societe', label: 'Société' },
                        { key: 'client', label: 'Client' },
                        { key: 'destination', label: 'Destination' },
                        { key: 'date_commande', label: 'Date', render: (r) => dateFr(r.date_commande) },
                        { key: 'etat', label: 'État' },
                        { key: 'tonnes_livrees', label: 'Tonnes livrées', render: (r) => tonnes(r.tonnes_livrees) },
                        { key: 'satisfaite', label: 'Satisf.', render: (r) => (r.satisfaite ? '✓' : '✗') },
                    ]}
                    rows={lignes.data}
                />
            </div>

            <div className="mt-3 flex items-center justify-between text-sm">
                <span className="text-gray-500 dark:text-gray-400">
                    Page {meta.page} / {meta.dernier}
                </span>
                <div className="flex gap-1">
                    <button
                        disabled={meta.page <= 1}
                        onClick={() => reload({ page: meta.page - 1 })}
                        className="rounded border border-gray-300 px-2 py-1 disabled:opacity-40 dark:border-gray-700"
                    >
                        Précédent
                    </button>
                    <button
                        disabled={meta.page >= meta.dernier}
                        onClick={() => reload({ page: meta.page + 1 })}
                        className="rounded border border-gray-300 px-2 py-1 disabled:opacity-40 dark:border-gray-700"
                    >
                        Suivant
                    </button>
                </div>
            </div>
        </section>
    );
}
