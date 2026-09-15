import EtatVide from '@/Components/EtatVide';
import { dateHeureFr } from '@/Components/dashboard/format';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

const SEVERITE = {
    // Le rouge reste réservé à l'identité Randa (voir docs/DESIGN.md §1.2) :
    // la sévérité « critique » utilise le violet, jamais le rouge.
    critique: { classe: 'banner-critique', badge: 'bg-violet-100 dark:bg-violet-900/40 text-violet-800 dark:text-violet-200', label: 'Critique' },
    alerte: { classe: 'banner-attention', badge: 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200', label: 'Alerte' },
    info: { classe: 'banner-info', badge: 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200', label: 'Info' },
};

function lien(a) {
    if (!a.route || !route().has(a.route)) return null;
    try {
        return route(a.route, a.route_params || {});
    } catch {
        return route(a.route);
    }
}

export default function NotificationsIndex() {
    const { notifications } = usePage().props;
    const alertes = notifications?.alertes ?? [];
    const fraicheur = notifications?.fraicheur;

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Notifications</h2>}
        >
            <Head title="Notifications" />

            <div className="mx-auto max-w-4xl space-y-6 py-6 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {alertes.length === 0
                            ? 'Aucune alerte active.'
                            : `${alertes.length} alerte(s) active(s).`}{' '}
                        {fraicheur && `Données du ${dateHeureFr(fraicheur)}.`}
                    </p>
                    <button
                        onClick={() => router.reload({ only: ['notifications'] })}
                        className="rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Rafraîchir
                    </button>
                </div>

                <p className="banner-info">
                    Les alertes sont calculées automatiquement à partir des dernières données
                    importées et de vos droits d'accès. Elles disparaissent d'elles-mêmes quand la
                    situation est résolue ou après un nouvel import.
                </p>

                {alertes.length === 0 ? (
                    <EtatVide
                        titre="Tout est au vert"
                        message="Aucune commande en retard exploitable, aucune rupture, aucun incident."
                    />
                ) : (
                    <ul className="space-y-2">
                        {alertes.map((a) => {
                            const s = SEVERITE[a.severity] ?? SEVERITE.info;
                            const href = lien(a);
                            return (
                                <li key={a.reference} className={s.classe}>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className={`rounded px-2 py-0.5 text-xs font-semibold ${s.badge}`}>
                                            {s.label}
                                        </span>
                                        <span className="font-medium text-gray-800 dark:text-gray-100">{a.titre}</span>
                                        <span className="font-mono text-xs text-gray-400 dark:text-gray-500">{a.type}</span>
                                    </div>
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{a.detail}</p>
                                    {href && (
                                        <Link
                                            href={href}
                                            className="mt-2 inline-block text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                        >
                                            Ouvrir la page concernée
                                        </Link>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
