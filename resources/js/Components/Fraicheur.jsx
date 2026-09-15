import { usePage } from '@inertiajs/react';
import { dateHeureFr } from '@/Components/dashboard/format';

/**
 * Exigence 7 — indication claire de la fraîcheur des données, affichée de façon
 * cohérente sur chaque page (« Données du JJ/MM à HH:MM »).
 *
 * Prend `iso` en priorité (fraîcheur renvoyée par les métriques de la page),
 * sinon retombe sur le partage Inertia global `fraicheur`.
 */
export default function Fraicheur({ iso, className = '' }) {
    const globale = usePage().props.fraicheur;
    const valeur = iso ?? globale;

    return (
        <span
            className={`inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400 ${className}`}
            title="Date du dernier import terminé. Les chiffres ne sont pas recalculés à chaque affichage."
        >
            <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path
                    fillRule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                    clipRule="evenodd"
                />
            </svg>
            {valeur ? `Données du ${dateHeureFr(valeur)}` : 'Aucune donnée importée'}
        </span>
    );
}
