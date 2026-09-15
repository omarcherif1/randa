import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AideIndicateur from '@/Components/AideIndicateur';
import Kpi from '@/Components/Kpi';
import SecondaryButton from '@/Components/SecondaryButton';
import ChartCard, { DataTable } from '@/Components/dashboard/ChartCard';
import { dateHeureFr, moisFr, n1, nInt, pct, tonnes } from '@/Components/dashboard/format';
import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
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

const FIABILITE_STYLE = {
    haute: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200',
    bonne: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200',
    moyenne: 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200',
    faible: 'bg-violet-100 dark:bg-violet-900/40 text-violet-800 dark:text-violet-200',
    insuffisante: 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
    indeterminee: 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
};
const TENDANCE_ICON = { hausse: '↗', baisse: '↘', stable: '→', indeterminee: '·' };

// Décision design — le rouge Randa ne doit jamais servir à une autre fin :
// le point de prévision utilisait #C8102E (littéralement la couleur société).
const COULEUR_PREVISION = '#7C3AED'; // violet — statut « critique/à retenir », jamais une société

function Badge({ value }) {
    return (
        <span className={`rounded px-2 py-0.5 text-xs font-medium ${FIABILITE_STYLE[value] ?? ''}`}>
            {value}
        </span>
    );
}

export default function Previsions({ metrics, options, filtres }) {
    const { auth, flash } = usePage().props;
    const [selection, setSelection] = useState(metrics.produits[0]?.article ?? null);
    const [reentrainement, setReentrainement] = useState(false);
    const produit = metrics.produits.find((p) => p.article === selection);

    const reentrainer = () => {
        setReentrainement(true);
        router.post(route('previsions.reentrainer'), {}, {
            preserveScroll: true,
            onFinish: () => setReentrainement(false),
        });
    };

    const chartData = useMemo(() => {
        if (!produit) return [];
        const rows = produit.historique.map((h) => ({
            mois: h.mois,
            reel: h.kg / 1000,
        }));
        const bt = {};
        produit.backtest.points.forEach((p) => {
            bt[p.mois] = p.prevu_kg / 1000;
        });
        rows.forEach((r) => {
            if (bt[r.mois] != null) r.backtest = bt[r.mois];
        });
        // Relie le dernier mois réel au premier mois projeté, pour que la
        // ligne de prévision (en pointillé) parte visuellement de l'historique
        // plutôt que de flotter, déconnectée, au-dessus du graphique.
        if (rows.length > 0 && produit.previsions_annee.length > 0) {
            rows[rows.length - 1].prevision = rows[rows.length - 1].reel;
        }
        produit.previsions_annee.forEach((p) => {
            rows.push({ mois: p.mois_cible, prevision: (p.quantite_prevue_kg ?? 0) / 1000 });
        });
        return rows;
    }, [produit]);

    const applyFiltre = (patch) => {
        const p = { ...filtres, ...patch };
        const params = {};
        if (p.societe) params.societe = p.societe;
        if (p.familles?.length) params.familles = p.familles;
        router.get(route('previsions'), params, { preserveState: true, preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Prévisions</h2>}
        >
            <Head title="Prévisions" />

            <div className="mx-auto max-w-7xl space-y-6 py-6 sm:px-6 lg:px-8">
                <div className="banner-info space-y-1">
                    <div><AideIndicateur cle="previsions" />{metrics.notes.palier}</div>
                </div>

                {flash?.status && <div className="banner-succes">{flash.status}</div>}
                {flash?.erreur && <div className="banner-critique">{flash.erreur}</div>}

                <div className="panel">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="text-sm">
                            <span className="font-medium text-gray-700 dark:text-gray-300">Méthode active : </span>
                            {metrics.methode === 'ml' ? (
                                <span className="font-semibold text-green-700 dark:text-green-300">
                                    Modèle ML (Random Forest)
                                </span>
                            ) : (
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    Calcul (repli — ML indisponible ou pas entraîné)
                                </span>
                            )}
                            {metrics.methode === 'ml' && metrics.modele_ml && (
                                <div className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    Entraîné le {dateHeureFr(metrics.modele_ml.entraine_le)} sur{' '}
                                    {nInt(metrics.modele_ml.n_echantillons)} lignes ({nInt(metrics.modele_ml.n_produits)}{' '}
                                    produits) — erreur pondérée par le volume :{' '}
                                    {metrics.modele_ml.wape != null ? pct(metrics.modele_ml.wape) : 'indéterminée'}
                                </div>
                            )}
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{metrics.notes.methode}</p>
                        </div>
                        {auth.isAdmin && (
                            <SecondaryButton onClick={reentrainer} disabled={reentrainement}>
                                {reentrainement ? 'Entraînement…' : 'Réentraîner le modèle'}
                            </SecondaryButton>
                        )}
                    </div>
                </div>

                <div className="panel">
                    <div className="flex flex-wrap items-end gap-4">
                        <label className="text-sm">
                            <span className="font-medium text-gray-700 dark:text-gray-300">Société</span>
                            <select
                                className="mt-1 block rounded-md border-gray-300 text-sm dark:border-gray-700"
                                value={filtres.societe ?? ''}
                                onChange={(e) => applyFiltre({ societe: e.target.value })}
                            >
                                <option value="">Toutes</option>
                                {options.societes.map((s) => (
                                    <option key={s.code} value={s.code}>
                                        {s.nom}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            Total prévu (mois prochain) : <strong>{tonnes(metrics.total_prevu_kg / 1000)}</strong>
                        </span>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Kpi
                        label={`Prévu jusqu'à ${moisFr(metrics.mois_fin_horizon)}`}
                        value={tonnes(metrics.total_prevu_annee_kg / 1000)}
                        hint="tous produits, mois par mois"
                    />
                    <Kpi label="Fiabilité haute (historique)" value={nInt(metrics.repartition_fiabilite.haute)} />
                    <Kpi label="Fiabilité moyenne" value={nInt(metrics.repartition_fiabilite.moyenne)} />
                    <Kpi label="Fiabilité faible" value={nInt(metrics.repartition_fiabilite.faible)} tone="attention" />
                    <Kpi
                        label="Erreur moyenne backtest"
                        value={
                            metrics.backtest_global.mape_moyen != null
                                ? pct(metrics.backtest_global.mape_moyen)
                                : '—'
                        }
                        hint={`${nInt(metrics.backtest_global.produits_testes)} produits testés`}
                    />
                </div>

                

                {/* Total prévu, tous produits, mois par mois jusqu'à décembre */}
                {metrics.total_prevu_par_mois.length > 0 && (
                    <ChartCard
                        title={`Total prévu par mois, jusqu'à ${moisFr(metrics.mois_fin_horizon)}`}
                        subtitle="tous produits confondus"
                        table={
                            <DataTable
                                columns={[
                                    { key: 'mois', label: 'Mois', render: (r) => moisFr(r.mois) },
                                    { key: 'kg', label: 'Prévu', render: (r) => tonnes(r.kg / 1000) },
                                ]}
                                rows={metrics.total_prevu_par_mois}
                            />
                        }
                    >
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={metrics.total_prevu_par_mois}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="mois" tickFormatter={moisFr} fontSize={11} />
                                <YAxis fontSize={11} tickFormatter={(v) => n1(v / 1000)} unit=" t" />
                                <Tooltip labelFormatter={moisFr} formatter={(v) => `${n1(v / 1000)} t`} />
                                <Bar dataKey="kg" name="Prévu" fill={COULEUR_PREVISION} />
                            </BarChart>
                        </ResponsiveContainer>
                    </ChartCard>
                )}
                {/* Graphique du produit sélectionné */}
                {produit && (
                    <section className="panel">
                        <div className="mb-2 flex flex-wrap items-center gap-2">
                            <h3 className="font-semibold text-gray-800 dark:text-gray-100">
                                {produit.article} — {produit.designation}
                            </h3>
                            <select
                                className="rounded-md border-gray-300 text-sm dark:border-gray-700"
                                value={selection}
                                onChange={(e) => setSelection(e.target.value)}
                            >
                                {metrics.produits.map((p) => (
                                    <option key={p.article} value={p.article}>
                                        {p.article} — {p.designation}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={chartData}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="mois" tickFormatter={moisFr} fontSize={11} />
                                    <YAxis fontSize={11} unit=" t" />
                                    <Tooltip
                                        labelFormatter={moisFr}
                                        formatter={(v) => `${n1(v)} t`}
                                    />
                                    <Legend />
                                    <Line dataKey="reel" name="Commandé (réel)" stroke="#374151" strokeWidth={2} dot={false} connectNulls />
                                    <Line dataKey="backtest" name="Prévu (backtest)" stroke="#9CA3AF" strokeDasharray="4 4" strokeWidth={2} dot connectNulls />
                                    <Line
                                        dataKey="prevision"
                                        name={`Prévision jusqu'à ${moisFr(metrics.mois_fin_horizon)}`}
                                        stroke={COULEUR_PREVISION}
                                        strokeDasharray="4 4"
                                        strokeWidth={2}
                                        dot={{ r: 3 }}
                                        connectNulls
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        </div>
                        <div className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            <span className="text-violet-600 dark:text-violet-400">●</span> Prévision{' '}
                            {moisFr(produit.prevision.mois_cible)} :{' '}
                            <strong>{tonnes((produit.prevision.quantite_prevue_kg ?? 0) / 1000)}</strong>{' '}
                            <span className="text-xs">
                                ({produit.prevision.methode === 'ml' ? 'modèle ML' : 'calcul'})
                            </span>{' '}
                            (palier {produit.prevision.palier}, <Badge value={produit.prevision.fiabilite} />, tendance{' '}
                            {TENDANCE_ICON[produit.prevision.tendance]} {produit.prevision.tendance})
                            <br />
                            Total prévu jusqu'à {moisFr(metrics.mois_fin_horizon)} :{' '}
                            <strong>{tonnes(produit.total_prevu_annee_kg / 1000)}</strong>
                            <br />
                            <span className="text-xs text-gray-500 dark:text-gray-400">{produit.prevision.note}</span>
                        </div>
                    </section>
                )}

                {/* Table complète */}
                <section className="panel">
                    <h3 className="mb-2 font-semibold text-gray-800 dark:text-gray-100">
                        Tous les produits ({nInt(metrics.produits.length)})
                    </h3>
                    <div className="overflow-x-auto">
                        <DataTable
                            columns={[
                                { key: 'article', label: 'Article' },
                                { key: 'designation', label: 'Désignation' },
                                {
                                    key: 'hist',
                                    label: 'Historique',
                                    render: (r) => `${r.prevision.mois_historique} mois`,
                                },
                                {
                                    key: 'prev',
                                    label: 'Prévision (mois prochain)',
                                    render: (r) =>
                                        r.prevision.quantite_prevue_kg != null
                                            ? tonnes(r.prevision.quantite_prevue_kg / 1000)
                                            : '—',
                                },
                                {
                                    key: 'prev_annee',
                                    label: `Jusqu'à ${moisFr(metrics.mois_fin_horizon)}`,
                                    render: (r) => tonnes(r.total_prevu_annee_kg / 1000),
                                },
                                {
                                    key: 'tend',
                                    label: 'Tendance',
                                    render: (r) => `${TENDANCE_ICON[r.prevision.tendance]} ${r.prevision.tendance}`,
                                },
                                {
                                    key: 'palier',
                                    label: 'Fiabilité (historique)',
                                    render: (r) => <Badge value={r.prevision.fiabilite} />,
                                },
                                {
                                    key: 'reelle',
                                    label: 'Fiabilité réelle (backtest)',
                                    render: (r) => (
                                        <>
                                            <Badge value={r.backtest.fiabilite_reelle} />{' '}
                                            {r.backtest.mape != null && (
                                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                                    ± {pct(r.backtest.mape)}
                                                </span>
                                            )}
                                        </>
                                    ),
                                },
                                {
                                    key: 'action',
                                    label: '',
                                    render: (r) => (
                                        <button
                                            onClick={() => setSelection(r.article)}
                                            className="text-xs text-blue-600 hover:underline dark:text-blue-400"
                                        >
                                            voir
                                        </button>
                                    ),
                                },
                            ]}
                            rows={metrics.produits}
                        />
                    </div>
                </section>

                {metrics.sans_poids.length > 0 && (
                    <section className="banner-attention">
                        <h3 className="font-semibold">{nInt(metrics.sans_poids.length)} produit(s) sans prévision</h3>
                        <p className="text-xs">
                            Poids d'unité non déterminé : impossible de prévoir en kg. À traiter dans
                            Administration (règle 2.8).
                        </p>
                        <p className="mt-2 text-sm">
                            {metrics.sans_poids.map((p) => `${p.article} (${p.designation})`).join(', ')}
                        </p>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
