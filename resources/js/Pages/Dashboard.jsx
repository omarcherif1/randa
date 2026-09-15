import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AideIndicateur from '@/Components/AideIndicateur';
import EtatVide from '@/Components/EtatVide';
import Kpi from '@/Components/Kpi';
import ChartCard, { DataTable } from '@/Components/dashboard/ChartCard';
import Filters from '@/Components/dashboard/Filters';
import CommandesTable from '@/Components/dashboard/CommandesTable';
import { moisFr, nInt, pct, tonnes } from '@/Components/dashboard/format';
import { couleurSociete, useThemeSombre } from '@/Support/theme';
import { Head } from '@inertiajs/react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

export default function Dashboard({ metrics, options, filtres, commandes }) {
    const { kpi, satisfaction, evolution, repartitions, classements } = metrics;
    const cvl = metrics.commande_vs_livre;
    const sombre = useThemeSombre();

    const exportUrl = (name) => {
        const p = new URLSearchParams();
        Object.entries(filtres).forEach(([k, v]) => {
            if (Array.isArray(v)) v.forEach((x) => p.append(`${k}[]`, x));
            else if (v) p.append(k, v);
        });
        return `${route(name)}?${p.toString()}`;
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Tableau de bord</h2>}
        >
            <Head title="Tableau de bord" />

            <div className="mx-auto max-w-7xl space-y-6 py-6 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="banner-info">
                        Tableau de bord <strong>local uniquement</strong>. Les commandes export
                        ont leur propre page.
                    </p>
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <a
                            href={exportUrl('dashboard.rapport')}
                            className="rounded border border-gray-300 px-2 py-1 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            Rapport PDF
                        </a>
                        <a
                            href={route('dashboard.rapport.hebdo')}
                            className="rounded border border-gray-300 px-2 py-1 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            Rapport hebdo
                        </a>
                    </div>
                </div>

                {!metrics.fraicheur ? (
                    <EtatVide
                        titre="Aucune donnée importée"
                        message="Importez un fichier unifié pour voir apparaître les indicateurs. Tous les chiffres reflètent le dernier import terminé."
                    />
                ) : (
                    <>
                        <Filters options={options} filtres={filtres} />

                        {/* Règle 2.3 — les 3 sociétés toujours affichées côte à côte */}
                        <section className="panel">
                            <h3 className="flex items-center gap-1 font-semibold text-gray-800 dark:text-gray-100">
                                Taux de satisfaction par société
                                <AideIndicateur cle="satisfaction" />
                            </h3>
                           
                            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                {satisfaction.par_societe.map((s) => {
                                    const couleur = couleurSociete(s.couleur, sombre);
                                    return (
                                        <div
                                            key={s.code}
                                            className="rounded-md border-l-4 bg-gray-50 p-3 dark:bg-gray-800/60"
                                            style={{ borderColor: couleur }}
                                        >
                                            <div className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                {s.nom}
                                            </div>
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
                            <div className="mt-4 h-56">
                                <ResponsiveContainer width="100%" height="100%">
                                    <LineChart data={satisfaction.courbe}>
                                        <CartesianGrid strokeDasharray="3 3" />
                                        <XAxis dataKey="mois" tickFormatter={moisFr} fontSize={11} />
                                        <YAxis
                                            domain={[0, 1]}
                                            tickFormatter={(v) => `${Math.round(v * 100)}%`}
                                            fontSize={11}
                                        />
                                        <Tooltip formatter={(v) => pct(v)} labelFormatter={moisFr} />
                                        <Legend />
                                        {satisfaction.par_societe.map((s) => (
                                            <Line
                                                key={s.code}
                                                type="monotone"
                                                dataKey={s.code}
                                                name={s.nom}
                                                stroke={couleurSociete(s.couleur, sombre)}
                                                strokeWidth={2}
                                                connectNulls
                                                dot={false}
                                            />
                                        ))}
                                    </LineChart>
                                </ResponsiveContainer>
                            </div>
                        </section>

                        {/* KPI avant les graphiques (exigence 7) */}
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Kpi
                                label="Commandes"
                                value={nInt(kpi.commandes.total)}
                                hint={`${nInt(kpi.commandes.ouvertes)} ouvertes, ${nInt(kpi.commandes.cloturees)} clôturées`}
                                aide="etat_cycle"
                            />
                            <Kpi label="Clients actifs" value={nInt(kpi.clients_actifs)} />
                            <Kpi label="Villes" value={nInt(kpi.villes)} hint="nombre de villes de commande distinctes" />
                            <Kpi label="Commandé" value={tonnes(kpi.qte_commandee_kg / 1000)} aide="tonnes" />
                            <Kpi label="Livré" value={tonnes(kpi.qte_livree_kg / 1000)} />
                            <Kpi label="Facturé" value={tonnes(kpi.qte_facturee_kg / 1000)} />
                            <Kpi
                                label="Commandes en retard"
                                value={nInt(kpi.commandes_en_retard)}
                                hint={`seuil ${kpi.seuil_retard_jours} j (proxy date de facture) · ${nInt(kpi.commandes_retard_indisponible)} non calculables`}
                                tone={kpi.commandes_en_retard > 0 ? 'critique' : 'normal'}
                                aide="retard"
                            />
                        </div>

                        {/* Graphiques d'évolution / répartition */}
                        <div className="grid gap-4 lg:grid-cols-2">
                            <ChartCard
                                title="Évolution des commandes et des tonnes livrées"
                                subtitle="par mois"
                                table={
                                    <DataTable
                                        columns={[
                                            { key: 'mois', label: 'Mois', render: (r) => moisFr(r.mois) },
                                            { key: 'commandes', label: 'Commandes', render: (r) => nInt(r.commandes) },
                                            { key: 'facturees', label: 'Facturées', render: (r) => nInt(r.facturees) },
                                            { key: 'tonnes_livrees', label: 'Tonnes livrées', render: (r) => tonnes(r.tonnes_livrees) },
                                        ]}
                                        rows={evolution}
                                    />
                                }
                            >
                                <ResponsiveContainer width="100%" height="100%">
                                    <LineChart data={evolution}>
                                        <CartesianGrid strokeDasharray="3 3" />
                                        <XAxis dataKey="mois" tickFormatter={moisFr} fontSize={11} />
                                        <YAxis yAxisId="l" fontSize={11} />
                                        <YAxis yAxisId="r" orientation="right" fontSize={11} />
                                        <Tooltip labelFormatter={moisFr} />
                                        <Legend />
                                        <Line yAxisId="l" dataKey="commandes" name="Commandes" stroke="#374151" strokeWidth={2} dot={false} />
                                        <Line yAxisId="r" dataKey="tonnes_livrees" name="Tonnes livrées" stroke="#047857" strokeWidth={2} dot={false} />
                                    </LineChart>
                                </ResponsiveContainer>
                            </ChartCard>

                            <ChartCard
                                title="Répartition par type de client"
                                subtitle="tonnes livrées"
                                table={
                                    <DataTable
                                        columns={[
                                            { key: 'label', label: 'Type de client' },
                                            { key: 'commandes', label: 'Commandes', render: (r) => nInt(r.commandes) },
                                            { key: 'tonnes', label: 'Tonnes', render: (r) => tonnes(r.tonnes) },
                                        ]}
                                        rows={repartitions.par_type_client}
                                    />
                                }
                            >
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={repartitions.par_type_client} layout="vertical" margin={{ left: 40 }}>
                                        <CartesianGrid strokeDasharray="3 3" />
                                        <XAxis type="number" fontSize={11} />
                                        <YAxis type="category" dataKey="label" width={110} fontSize={10} />
                                        <Tooltip formatter={(v) => tonnes(v)} />
                                        <Bar dataKey="tonnes" name="Tonnes" fill="#1D4ED8" />
                                    </BarChart>
                                </ResponsiveContainer>
                            </ChartCard>

                            <ChartCard
                                title="Commandé vs livré par famille"
                                subtitle="tonnes"
                                className="lg:col-span-2"
                                table={
                                    <DataTable
                                        columns={[
                                            { key: 'label', label: 'Famille' },
                                            { key: 'commande', label: 'Commandé', render: (r) => tonnes(r.commande) },
                                            { key: 'livre', label: 'Livré', render: (r) => tonnes(r.livre) },
                                        ]}
                                        rows={cvl}
                                    />
                                }
                            >
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={cvl}>
                                        <CartesianGrid strokeDasharray="3 3" />
                                        <XAxis dataKey="label" fontSize={9} angle={-25} textAnchor="end" height={60} />
                                        <YAxis fontSize={11} />
                                        <Tooltip formatter={(v) => tonnes(v)} />
                                        <Legend />
                                        <Bar dataKey="commande" name="Commandé" fill="#9CA3AF" />
                                        <Bar dataKey="livre" name="Livré" fill="#047857" />
                                    </BarChart>
                                </ResponsiveContainer>
                            </ChartCard>
                        </div>

                        {/* Classements */}
                        <div className="grid gap-4 lg:grid-cols-2">
                            <Classement
                                title="Top produits commandés"
                                rows={classements.top_produits_commandes}
                                cols={[
                                    { key: 'article', label: 'Article' },
                                    { key: 'designation', label: 'Désignation' },
                                    { key: 'tonnes', label: 'Tonnes', render: (r) => tonnes(r.tonnes) },
                                ]}
                            />
                            <Classement
                                title="Top clients"
                                rows={classements.top_clients}
                                cols={[
                                    { key: 'nom', label: 'Client' },
                                    { key: 'numero', label: 'N°' },
                                    { key: 'tonnes', label: 'Tonnes', render: (r) => tonnes(r.tonnes) },
                                ]}
                            />
                            <Classement
                                title="Top régions (ville de commande)"
                                rows={classements.top_regions}
                                cols={[
                                    { key: 'label', label: 'Ville' },
                                    { key: 'tonnes', label: 'Tonnes', render: (r) => tonnes(r.tonnes) },
                                ]}
                                className="lg:col-span-2"
                            />
                        </div>

                        {/* Table interactive — pagination / tri / recherche CÔTÉ SERVEUR */}
                        <CommandesTable commandes={commandes} />
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function Classement({ title, rows, cols, className = '' }) {
    return (
        <section className={`panel ${className}`}>
            <h3 className="mb-2 font-semibold text-gray-800 dark:text-gray-100">{title}</h3>
            <div className="overflow-x-auto">
                <DataTable columns={cols} rows={rows} />
            </div>
        </section>
    );
}
