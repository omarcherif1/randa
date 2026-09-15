import { Link } from '@inertiajs/react';

// [nom de route, libellé, motifs "actif si la route courante correspond"]
const LIENS = [
    ['admin.import', 'Import de données', ['admin.import', 'admin.import.*']],
    ['admin.index', 'Comptes & journaux', ['admin.index', 'admin.users.*']],
    ['admin.anomalies', 'Anomalies', ['admin.anomalies', 'admin.anomalies.*']],
    ['admin.corrections', 'Corrections données', ['admin.corrections', 'admin.corrections.*']],
    ['parametres.index', 'Paramètres', ['parametres.*']],
];

export default function AdminNav() {
    return (
        <nav className="flex flex-wrap gap-2 text-sm">
            {LIENS.map(([name, label, motifs]) => {
                const actif = motifs.some((m) => route().current(m));
                return (
                    <Link
                        key={name}
                        href={route(name)}
                        className={`rounded px-3 py-1 ${
                            actif
                                ? 'bg-gray-800 dark:bg-gray-600 text-white'
                                : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'
                        }`}
                    >
                        {label}
                    </Link>
                );
            })}
        </nav>
    );
}
