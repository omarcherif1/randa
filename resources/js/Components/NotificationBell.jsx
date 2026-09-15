import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const SEVERITE = {
    critique: { point: 'bg-violet-500', texte: 'text-violet-700 dark:text-violet-400' },
    alerte: { point: 'bg-amber-500', texte: 'text-amber-700' },
    info: { point: 'bg-blue-500', texte: 'text-blue-700' },
};

function lien(a) {
    if (!a.route || !route().has(a.route)) return null;
    try {
        return route(a.route, a.route_params || {});
    } catch {
        return route(a.route);
    }
}

export default function NotificationBell() {
    const { notifications } = usePage().props;
    const alertes = notifications?.alertes ?? [];
    const count = notifications?.count ?? 0;
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        const clic = (e) => {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        };
        document.addEventListener('mousedown', clic);
        return () => document.removeEventListener('mousedown', clic);
    }, []);

    const apercu = alertes.slice(0, 6);

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="relative inline-flex items-center rounded-md p-2 text-gray-500 dark:text-gray-400 transition hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 focus:outline-none"
                aria-label={`Notifications (${count})`}
            >
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.7}>
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"
                    />
                </svg>
                {count > 0 && (
                    <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-violet-600 px-1 text-xs font-semibold text-white">
                        {count > 99 ? '99+' : count}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute end-0 z-50 mt-2 w-80 overflow-hidden rounded-md bg-white dark:bg-gray-900 shadow-lg ring-1 ring-black/5">
                    <div className="flex items-center justify-between border-b px-4 py-2">
                        <span className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            Alertes actives {count > 0 && `(${count})`}
                        </span>
                        <button
                            onClick={() => {
                                setOpen(false);
                                router.reload({ only: ['notifications'] });
                            }}
                            className="text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600"
                        >
                            Rafraîchir
                        </button>
                    </div>

                    {apercu.length === 0 ? (
                        <p className="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                            Aucune alerte. Tout est au vert.
                        </p>
                    ) : (
                        <ul className="max-h-96 divide-y divide-gray-100 dark:divide-gray-800 overflow-y-auto">
                            {apercu.map((a) => {
                                const href = lien(a);
                                const contenu = (
                                    <div className="flex gap-2 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <span
                                            className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${
                                                (SEVERITE[a.severity] ?? SEVERITE.info).point
                                            }`}
                                        />
                                        <div className="min-w-0">
                                            <div className="truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                                                {a.titre}
                                            </div>
                                            <div className="text-xs text-gray-500 dark:text-gray-400">{a.detail}</div>
                                        </div>
                                    </div>
                                );
                                return (
                                    <li key={a.reference}>
                                        {href ? (
                                            <Link href={href} onClick={() => setOpen(false)}>
                                                {contenu}
                                            </Link>
                                        ) : (
                                            contenu
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    <Link
                        href={route('notifications')}
                        onClick={() => setOpen(false)}
                        className="block border-t px-4 py-2 text-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        Voir toutes les alertes
                    </Link>
                </div>
            )}
        </div>
    );
}
