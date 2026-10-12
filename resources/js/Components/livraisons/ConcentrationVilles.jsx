import ChartCard, { DataTable } from '@/Components/dashboard/ChartCard';
import { nInt, pct, tonnes } from '@/Components/dashboard/format';
import { useThemeSombre } from '@/Support/theme';
import { useMemo, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

/**
 * Concentration géographique — une barre par ville (valeur PROPRE de la ville,
 * jamais cumulée), villes rangées de la plus forte à la plus faible : la pente
 * montre à quel point l'activité se concentre sur les premières villes.
 * Seules les TOP premières sont tracées ; le tableau les liste toutes.
 * Ville = ville de COMMANDE (cf. 4.3).
 */
const TOP = 25;

const MESURES = {
    commandes: { label: 'Commandes', format: nInt },
    tonnes: { label: 'Tonnes livrées', format: tonnes },
};

// Série unique : bleu clair, distinct du bleu SMT (#1D4ED8) et des couleurs de statut.
const ENCRE = { clair: '#3B82F6', sombre: '#60A5FA' };

function construire(villes, mesure) {
    const tri = [...villes].sort((a, b) => b[mesure] - a[mesure]).filter((v) => v[mesure] > 0);
    const total = tri.reduce((s, v) => s + v[mesure], 0);
    if (total === 0) return { points: [], toutes: [], nbVilles: 0 };

    const toutes = tri.map((v, i) => ({ ...v, rang: i + 1, valeur: v[mesure], part: v[mesure] / total }));

    return { points: toutes.slice(0, TOP), toutes, nbVilles: tri.length };
}

function Infobulle({ active, payload, format }) {
    if (!active || !payload?.length) return null;
    const p = payload[0].payload;
    return (
        <div className="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div className="font-semibold text-gray-800 dark:text-gray-100">
                {p.rang}. {p.ville}
            </div>
            <div className="text-gray-600 dark:text-gray-300">{format(p.valeur)}</div>
            <div className="text-gray-500 dark:text-gray-400">{pct(p.part)} du total</div>
        </div>
    );
}

export default function ConcentrationVilles({ villes }) {
    const sombre = useThemeSombre();
    const [mesure, setMesure] = useState('commandes');
    const { label, format } = MESURES[mesure];
    const encre = sombre ? ENCRE.sombre : ENCRE.clair;

    const { points, toutes, nbVilles } = useMemo(
        () => construire(villes ?? [], mesure),
        [villes, mesure],
    );

    if (points.length === 0) return null;

    const premiere = toutes[0];

    return (
        <ChartCard
            title="Concentration par ville"
            hauteur="h-96"
            subtitle={`${label} par ville, ${points.length} premières sur ${nbVilles} · 1re : ${premiere.ville} (${pct(premiere.part)} du total) · ville de commande, pas de livraison`}
            table={
                <DataTable
                    columns={[
                        { key: 'rang', label: 'Rang' },
                        { key: 'ville', label: 'Ville (commande)' },
                        { key: 'valeur', label, render: (r) => format(r.valeur) },
                        { key: 'part', label: 'Part du total', render: (r) => pct(r.part) },
                    ]}
                    rows={toutes}
                />
            }
        >
            <div className="flex h-full flex-col">
                <div className="mb-2 flex gap-1 text-xs" role="group" aria-label="Mesure">
                    {Object.entries(MESURES).map(([cle, m]) => (
                        <button
                            key={cle}
                            type="button"
                            onClick={() => setMesure(cle)}
                            aria-pressed={mesure === cle}
                            className={`rounded border px-2 py-1 ${
                                mesure === cle
                                    ? 'border-gray-700 bg-gray-700 text-white dark:border-gray-300 dark:bg-gray-300 dark:text-gray-900'
                                    : 'border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800'
                            }`}
                        >
                            {m.label}
                        </button>
                    ))}
                </div>
                <div className="min-h-0 flex-1">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={points} margin={{ top: 8, right: 16, bottom: 4, left: 0 }}>
                            <CartesianGrid strokeDasharray="3 3" vertical={false} />
                            <XAxis
                                dataKey="ville"
                                interval={0}
                                angle={-40}
                                textAnchor="end"
                                height={70}
                                fontSize={10}
                                tickFormatter={(v) => (v.length > 14 ? `${v.slice(0, 13)}…` : v)}
                            />
                            <YAxis tickFormatter={(v) => nInt(v)} fontSize={11} width={56} />
                            <Tooltip content={<Infobulle format={format} />} cursor={{ fill: sombre ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.04)' }} />
                            <Bar
                                dataKey="valeur"
                                name={label}
                                fill={encre}
                                radius={[4, 4, 0, 0]}
                                maxBarSize={28}
                                isAnimationActive={false}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </div>
        </ChartCard>
    );
}
