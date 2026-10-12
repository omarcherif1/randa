import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AideIndicateur from '@/Components/AideIndicateur';
import EtatVide from '@/Components/EtatVide';
import Kpi from '@/Components/Kpi';
import Filters from '@/Components/dashboard/Filters';
import { moisFr, nInt, pct, tonnes } from '@/Components/dashboard/format';
import { couleurSociete, useThemeSombre } from '@/Support/theme';
import { Head, Link } from '@inertiajs/react';
import {
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

export default function Dashboard({ metrics, options, filtres }) {
    const { kpi, satisfaction } = metrics;
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
                        <Link
                            href={route('dashboard.local')}
                            className="rounded border border-gray-300 px-2 py-1 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            Détails des commandes →
                        </Link>
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

                            <div className="mt-4 grid gap-4 sm:grid-cols-3">
                                {satisfaction.par_societe.map((s) => {
                                    const couleur = couleurSociete(s.couleur, sombre);
                                    return (
                                        <div
                                            key={s.code}
                                            className="flex min-h-[6.5rem] flex-col justify-between rounded-lg border-l-4 bg-gray-50 p-4 dark:bg-gray-800/60"
                                            style={{ borderColor: couleur }}
                                        >
                                            <div className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                                {s.nom}
                                            </div>
                                            <div
                                                className="mt-1 text-3xl font-bold tabular-nums"
                                                style={{ color: couleur }}
                                            >
                                                {pct(s.taux)}
                                            </div>
                                            <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">
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
                            <Kpi
                                label="Écart"
                                value={tonnes((kpi.qte_commandee_kg - kpi.qte_livree_kg) / 1000)}
                                hint="commandé − livré"
                                tone="rouge"
                                aide="tonnes"
                            />
                            <Kpi label="Facturé" value={tonnes(kpi.qte_facturee_kg / 1000)} />
                            <Kpi
                                label="Commandes en retard"
                                value={nInt(kpi.commandes_en_retard)}
                                hint={`seuil ${kpi.seuil_retard_jours} j (proxy date de facture) · ${nInt(kpi.commandes_retard_indisponible)} non calculables`}
                                tone={kpi.commandes_en_retard > 0 ? 'rouge' : 'normal'}
                                aide="retard"
                            />
                        </div>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
