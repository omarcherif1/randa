import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EtatVide from '@/Components/EtatVide';
import ChartCard, { DataTable } from '@/Components/dashboard/ChartCard';
import Filters from '@/Components/dashboard/Filters';
import CommandesTable from '@/Components/dashboard/CommandesTable';
import { moisFr, nInt, tonnes } from '@/Components/dashboard/format';
import { Head, Link } from '@inertiajs/react';
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

export default function Local({ metrics, options, filtres, commandes }) {
    const { evolution, repartitions, classements } = metrics;
    const cvl = metrics.commande_vs_livre;

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Commandes locales — détails</h2>}
        >
            <Head title="Commandes locales — détails" />

            <div className="mx-auto max-w-7xl space-y-6 py-6 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="banner-info">
                        Détails des commandes <strong>locales uniquement</strong>.
                    </p>
                    <Link
                        href={route('dashboard')}
                        className="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        ← Retour au tableau de bord
                    </Link>
                </div>

                {!metrics.fraicheur ? (
                    <EtatVide
                        titre="Aucune donnée importée"
                        message="Importez un fichier unifié pour voir apparaître les indicateurs. Tous les chiffres reflètent le dernier import terminé."
                    />
                ) : (
                    <>
                        <Filters options={options} filtres={filtres} />

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
