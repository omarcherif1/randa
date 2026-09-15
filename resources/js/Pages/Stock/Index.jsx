import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Kpi from '@/Components/Kpi';
import { DataTable } from '@/Components/dashboard/ChartCard';
import { dateFr, n1, nInt, pct } from '@/Components/dashboard/format';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

const STATUT_STYLE = {
    rupture: 'bg-violet-100 dark:bg-violet-900/40 text-violet-800 dark:text-violet-200',
    alerte: 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200',
    normal: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200',
    indetermine: 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
};

export default function Stock({ metrics }) {
    const [filtre, setFiltre] = useState('');
    const produits = filtre
        ? metrics.produits.filter((p) => p.statut === filtre)
        : metrics.produits;

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Stock</h2>}
        >
            <Head title="Stock" />

                   <p opacity={0.7} className="text-sm text-gray-500 dark:text-gray-400 center">
                       juste un design pour le moment, pas de calculs de stock, juste les statuts et les historiques    
                   </p>

            <div className="mx-auto max-w-7xl space-y-6 py-6 sm:px-6 lg:px-8">
                <div className="grid gap-4 sm:grid-cols-4">
                    <Kpi label="Produits suivis" value={nInt(metrics.total_produits)} />
                    <Kpi label="En rupture" value={nInt(metrics.compte.rupture)} tone="critique" />
                    <Kpi label="En alerte" value={nInt(metrics.compte.alerte)} tone="attention" />
                    <Kpi label="Normal" value={nInt(metrics.compte.normal)} tone="ok" />
                </div>

                <section className="panel">
                    <div className="mb-3 flex items-center gap-2">
                        <h3 className="font-semibold text-gray-800 dark:text-gray-100">Produits</h3>
                        <select
                            value={filtre}
                            onChange={(e) => setFiltre(e.target.value)}
                            className="rounded-md border-gray-300 text-sm dark:border-gray-700"
                        >
                            <option value="">Tous</option>
                            <option value="rupture">Rupture</option>
                            <option value="alerte">Alerte</option>
                            <option value="normal">Normal</option>
                            <option value="indetermine">Indéterminé</option>
                        </select>
                    </div>

                    <div className="overflow-x-auto">
                        <DataTable
                            columns={[
                                { key: 'article', label: 'Article' },
                                { key: 'designation', label: 'Désignation' },
                                {
                                    key: 'statut',
                                    label: 'Statut',
                                    render: (r) => (
                                        <span
                                            className={`rounded px-2 py-0.5 text-xs font-medium ${STATUT_STYLE[r.statut]}`}
                                        >
                                            {r.statut_libelle}
                                        </span>
                                    ),
                                },
                                {
                                    key: 'couverture',
                                    label: metrics.methode === 'A' ? 'Couverture (jours)' : 'Taux de couverture',
                                    render: (r) =>
                                        metrics.methode === 'A'
                                            ? r.jours_couverture != null
                                                ? `${n1(r.jours_couverture)} j`
                                                : '—'
                                            : r.taux_couverture != null
                                              ? pct(r.taux_couverture)
                                              : '—',
                                },
                                {
                                    key: 'historique',
                                    label: 'Historique',
                                    render: (r) =>
                                        r.historique.length
                                            ? r.historique
                                                  .slice(-5)
                                                  .map((h) => `${dateFr(h.date)} : ${nInt(h.stock_kg)} kg`)
                                                  .join(', ')
                                            : '—',
                                },
                            ]}
                            rows={produits}
                        />
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
