import { useState } from 'react';

/**
 * Enveloppe d'un graphique avec, en dessous, un tableau de données alternatif
 * (exigence d'accessibilité 7 / 4.2 : chaque graphique a une alternative tableau).
 */
export default function ChartCard({ title, subtitle, children, table, className = '', hauteur = 'h-64' }) {
    const [showTable, setShowTable] = useState(false);

    return (
        <section className={`panel ${className}`}>
            <div className="mb-2 flex items-start justify-between gap-2">
                <div>
                    <h3 className="font-semibold text-gray-800 dark:text-gray-100">{title}</h3>
                    {subtitle && <p className="text-xs text-gray-500 dark:text-gray-400">{subtitle}</p>}
                </div>
                {table && (
                    <button
                        type="button"
                        onClick={() => setShowTable((v) => !v)}
                        className="shrink-0 rounded border border-gray-300 dark:border-gray-700 px-2 py-1 text-xs text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800"
                        aria-expanded={showTable}
                    >
                        {showTable ? 'Voir le graphique' : 'Voir le tableau'}
                    </button>
                )}
            </div>

            {showTable && table ? (
                <div className="overflow-x-auto">{table}</div>
            ) : (
                <div className={`${hauteur} w-full`}>{children}</div>
            )}
        </section>
    );
}

export function DataTable({ columns, rows }) {
    return (
        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
            <thead>
                <tr className="text-left">
                    {columns.map((c) => (
                        <th key={c.key} className="px-3 py-2 font-bold text-gray-700 dark:text-gray-300">
                            {c.label}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {rows.length === 0 && (
                    <tr>
                        <td className="px-3 py-4 text-gray-500 dark:text-gray-400" colSpan={columns.length}>
                            Aucune donnée pour ces filtres.
                        </td>
                    </tr>
                )}
                {rows.map((r, i) => (
                    <tr key={i}>
                        {columns.map((c) => (
                            <td key={c.key} className="px-3 py-2">
                                {c.render ? c.render(r) : r[c.key]}
                            </td>
                        ))}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
