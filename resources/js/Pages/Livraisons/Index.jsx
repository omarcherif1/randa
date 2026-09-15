import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AideIndicateur from '@/Components/AideIndicateur';
import Kpi from '@/Components/Kpi';
import { DataTable } from '@/Components/dashboard/ChartCard';
import { dateFr, nInt } from '@/Components/dashboard/format';
import { Deferred, Head, router } from '@inertiajs/react';
import { useState } from 'react';

const ETATS_ORDRE = ['saisie', 'reservee', 'expediee', 'facturee', 'cloturee'];
// KPI affichés en tête de page : uniquement les états atteints par les
// commandes locales dans les faits (saisie/réservée/expédiée toujours à 0
// ici) — la sélection « Tous les états » du filtre ci-dessous reste complète.
const ETATS_KPI = ['facturee', 'cloturee'];
const ETAT_LABEL = {
    saisie: 'Saisie',
    reservee: 'Réservée',
    expediee: 'Expédiée',
    facturee: 'Facturée',
    cloturee: 'Clôturée',
};
const RETARD_LABEL = {
    a_temps: 'À temps',
    en_retard: 'En retard',
    donnee_indisponible: 'Donnée indisponible',
    non_applicable: 'N/A',
};

export default function Livraisons({ metrics, commandes }) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Livraisons</h2>}
        >
            <Head title="Livraisons" />

            <div className="mx-auto max-w-7xl space-y-6 py-6 sm:px-6 lg:px-8">
                <p className="banner-info">
                    Commandes <strong>locales</strong> et leur état réel dans le cycle .
                </p>

                {/* Retards — mis en avant, avec l'état de la donnée */}
                <section className={metrics.retard_exploitable ? 'panel' : 'banner-attention'}>
                    <h3 className="flex items-center gap-1 font-semibold text-gray-800 dark:text-gray-100">
                        Retards de livraison 
                        <AideIndicateur cle="retard" />
                    </h3>
                    {metrics.retard_exploitable ? (
                        <>
                            {/* <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{metrics.retard_message}</p> */}
                            <div className="mt-2 grid gap-3 sm:grid-cols-3">
                                <Kpi label="En retard" value={nInt(metrics.retards.en_retard)} tone="critique" />
                                <Kpi label="À temps" value={nInt(metrics.retards.a_temps)} />
                                <Kpi label="Donnée indisponible" value={nInt(metrics.retards.donnee_indisponible)} />
                            </div>
                        </>
                    ) : (
                        <p className="mt-2">
                            <strong>Module non exploitable en l'état.</strong> {metrics.retard_message} Aucun
                            « 0 retard » trompeur n'est affiché.
                        </p>
                    )}
                </section>

                {/* KPI par état */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <Kpi label="Total commandes" value={nInt(metrics.total)} />
                    {ETATS_KPI.map((e) => (
                        <Kpi key={e} label={ETAT_LABEL[e]} value={nInt(metrics.par_etat[e])} />
                    ))}
                </div>
                {metrics.incertaines > 0 && (
                    <p className="banner-attention">
                        {nInt(metrics.incertaines)} commande(s) à l'état <strong>incertain</strong> : la donnée ne
                        permet pas de trancher entre « réservé » et « expédié ».
                    </p>
                )}

                

                <Deferred
                    data="commandes"
                    fallback={<section className="panel text-sm text-gray-500 dark:text-gray-400">Chargement des livraisons…</section>}
                >
                    {commandes ? <Table commandes={commandes} /> : null}
                </Deferred> 
            </div>
        </AuthenticatedLayout>
    );
}

function Table({ commandes }) {
    const { lignes, meta } = commandes;
    const [q, setQ] = useState(meta.q ?? '');

    const reload = (params) =>
        router.reload({
            only: ['commandes'],
            data: { tri: meta.tri, sens: meta.sens, q, etat: meta.etat, page: meta.page, ...params },
        });

    return (
        <section className="panel">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 className="font-semibold text-gray-800 dark:text-gray-100">
                    Commandes locales ({nInt(meta.total)})
                </h3>
                <div className="flex gap-2 text-sm">
                    <select
                        value={meta.etat ?? ''}
                        onChange={(e) => reload({ etat: e.target.value || null, page: 1 })}
                        className="rounded-md border-gray-300 text-sm dark:border-gray-700"
                    >
                        <option value="">Tous les états</option>
                        {ETATS_ORDRE.map((e) => (
                            <option key={e} value={e}>
                                {ETAT_LABEL[e]}
                            </option>
                        ))}
                    </select>
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
                            placeholder="N°, client, ville…"
                            className="rounded-md border-gray-300 text-sm dark:border-gray-700"
                        />
                        <button className="rounded-md bg-gray-800 px-3 py-1 text-white dark:bg-gray-600">
                            Rechercher
                        </button>
                    </form>
                </div>
            </div>

            <div className="overflow-x-auto">
                <DataTable
                    columns={[
                        { key: 'numero', label: 'N°' },
                        { key: 'societe', label: 'Société' },
                        { key: 'client', label: 'Client' },
                        { key: 'ville_commande', label: 'Ville (commande)' },
                        { key: 'date_commande', label: 'Date commande', render: (r) => dateFr(r.date_commande) },
                        { key: 'date_facture', label: 'Date facture', render: (r) => dateFr(r.date_facture) },
                        {
                            key: 'etat',
                            label: 'État',
                            render: (r) => (
                                <>
                                    {r.etat_libelle}
                                    {r.etat_incertain && (
                                        <span className="ml-1 text-xs text-amber-600 dark:text-amber-400">(incertain)</span>
                                    )}
                                </>
                            ),
                        },
                        {
                            key: 'retard_statut',
                            label: 'Retard',
                            render: (r) => (
                                <span
                                    className={
                                        r.retard_statut === 'en_retard'
                                            ? 'text-violet-600 dark:text-violet-400'
                                            : r.retard_statut === 'donnee_indisponible'
                                              ? 'text-gray-400 dark:text-gray-500'
                                              : ''
                                    }
                                >
                                    {RETARD_LABEL[r.retard_statut]}
                                    {r.retard_jours != null && ` (${r.retard_jours} j)`}
                                </span>
                            ),
                        },
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
