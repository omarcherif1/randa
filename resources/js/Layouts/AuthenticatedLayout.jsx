import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import Fraicheur from '@/Components/Fraicheur';
import NotificationBell from '@/Components/NotificationBell';
import ThemeToggle from '@/Components/ThemeToggle';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/* Icônes (inline, pas de dépendance) --------------------------------------- */
const I = {
    dashboard: 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z',
    local: 'M4 6h16M4 12h10M4 18h13',
    livraisons:
        'M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm11 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM3 4h11v9H3V4zm12 3h3.5L21 10v3h-6V7z',
    stock: 'M4 4h16v4H4V4zm0 6h16v10H4V10zm4 3h8v2H8v-2z',
    previsions: 'M3 17l6-6 4 4 8-8M14 7h7v7',
    export: 'M4 4h10v2H6v12h12v-8h2v10H4V4zm9 0h7v7h-2V7.4l-7.3 7.3-1.4-1.4L18.6 6H13V4z',
    admin: 'M12 2l8 4v6c0 5-3.4 8.7-8 10-4.6-1.3-8-5-8-10V6l8-4zm0 6a3 3 0 100 6 3 3 0 000-6z',
    parametres:
        'M12 8a4 4 0 100 8 4 4 0 000-8zm8.94 3a7.94 7.94 0 00-.2-1.2l2-1.6-2-3.4-2.4 1a8 8 0 00-2-1.2l-.36-2.6h-4l-.36 2.6a8 8 0 00-2 1.2l-2.4-1-2 3.4 2 1.6c-.1.4-.16.8-.2 1.2s.06.8.2 1.2l-2 1.6 2 3.4 2.4-1a8 8 0 002 1.2l.36 2.6h4l.36-2.6a8 8 0 002-1.2l2.4 1 2-3.4-2-1.6c.14-.4.2-.8.2-1.2z',
    notifications:
        'M12 22a2.5 2.5 0 002.45-2h-4.9A2.5 2.5 0 0012 22zm6-6V11a6 6 0 10-12 0v5l-2 2v1h16v-1l-2-2z',
    aide: 'M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-2h2v2zm1.07-7.75l-.9.92C12.45 12.9 12 13.5 12 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26A2 2 0 1010 8H8a4 4 0 116.07 3.25z',
};

function Icone({ nom, className = 'h-5 w-5' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8}
            strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d={I[nom]} />
        </svg>
    );
}

/* Construction du menu selon les droits ----------------------------------- */
const CATALOGUE = {
    dashboard: { label: 'Tableau de bord', route: 'dashboard', icone: 'dashboard' },
    local: { label: 'Détails commandes', route: 'dashboard.local', icone: 'local' },
    livraisons: { label: 'Livraisons', route: 'livraisons', icone: 'livraisons' },
    stock: { label: 'Stock', route: 'stock', icone: 'stock' },
    previsions: { label: 'Prévisions', route: 'previsions', icone: 'previsions' },
    export: { label: 'Export', route: 'export', icone: 'export' },
};

function construireGroupes({ pages, isAdmin, notifCount }) {
    // `permission` permet de gater une entrée du catalogue sur un droit
    // différent de sa propre clé (ex. « local » hérite du droit « dashboard »,
    // dont ce n'est qu'une seconde page de détail, pas un droit séparé).
    const dispo = (cle, permission = cle) =>
        pages.includes(permission) && CATALOGUE[cle] && route().has(CATALOGUE[cle].route)
            ? [CATALOGUE[cle]]
            : [];

    const groupes = [
        {
            titre: 'Pilotage',
            liens: [
                ...dispo('dashboard'),
                ...dispo('local', 'dashboard'),
                ...dispo('livraisons'),
                ...dispo('stock'),
                ...dispo('previsions'),
            ],
        },
        { titre: 'International', liens: [...dispo('export')] },
    ];

    if (isAdmin) {
        groupes.push({
            titre: 'Administration',
            liens: [
                { label: 'Administration', route: 'admin.index', icone: 'admin', actif: 'admin.*' },
                { label: 'Paramètres', route: 'parametres.index', icone: 'parametres', actif: 'parametres.*' },
            ],
        });
    }

    groupes.push({
        titre: 'Aide',
        liens: [
            { label: 'Notifications', route: 'notifications', icone: 'notifications', badge: notifCount },
            { label: 'Comment lire ces chiffres', route: 'aide.indicateurs', icone: 'aide' },
        ],
    });

    return groupes.filter((g) => g.liens.length > 0);
}

function LienNav({ lien, onNavigate }) {
    const actif = route().current(lien.actif ?? lien.route);
    return (
        <Link
            href={route(lien.route)}
            onClick={onNavigate}
            aria-current={actif ? 'page' : undefined}
            className={`group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition ${
                actif
                    ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900'
                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100'
            }`}
        >
            <Icone nom={lien.icone} />
            <span className="flex-1 truncate">{lien.label}</span>
            {lien.badge > 0 && (
                <span className="rounded-full bg-violet-600 px-1.5 text-xs font-semibold text-white">
                    {lien.badge > 99 ? '99+' : lien.badge}
                </span>
            )}
        </Link>
    );
}

function Menu({ groupes, onNavigate }) {
    return (
        <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-4">
            {groupes.map((g) => (
                <div key={g.titre}>
                    <div className="px-3 pb-1 text-xs font-semibold text-gray-400 dark:text-gray-500">
                        {g.titre}
                    </div>
                    <div className="space-y-0.5">
                        {g.liens.map((l) => (
                            <LienNav key={l.route} lien={l} onNavigate={onNavigate} />
                        ))}
                    </div>
                </div>
            ))}
        </nav>
    );
}

function BarreLaterale({ groupes, onNavigate }) {
    return (
        <div className="flex h-full flex-col bg-white dark:bg-gray-900">
            <Link href="/" className="flex items-center gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <ApplicationLogo className="h-14 w-auto" />
                <span className="text-sm font-semibold leading-tight text-gray-800 dark:text-gray-100">
                    Pilotage
                    <span className="block text-xs font-normal text-gray-400 dark:text-gray-500">Groupe Randa</span>
                </span>
            </Link>
            <Menu groupes={groupes} onNavigate={onNavigate} />
        </div>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const props = usePage().props;
    const { auth } = props;
    const user = auth.user;
    const [drawer, setDrawer] = useState(false);

    const groupes = construireGroupes({
        pages: auth.pages || [],
        isAdmin: auth.isAdmin,
        notifCount: props.notifications?.count ?? 0,
    });

    useEffect(() => {
        const esc = (e) => e.key === 'Escape' && setDrawer(false);
        document.addEventListener('keydown', esc);
        return () => document.removeEventListener('keydown', esc);
    }, []);

    return (
        <div className="min-h-screen bg-gray-100 text-gray-800 dark:bg-gray-950 dark:text-gray-200">
            {/* Barre latérale fixe (desktop) */}
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-60 border-r border-gray-200 lg:block dark:border-gray-800">
                <BarreLaterale groupes={groupes} />
            </aside>

            {/* Tiroir (mobile) */}
            {drawer && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div
                        className="absolute inset-0 bg-black/40"
                        onClick={() => setDrawer(false)}
                        aria-hidden="true"
                    />
                    <div className="absolute inset-y-0 left-0 w-64 shadow-xl">
                        <BarreLaterale groupes={groupes} onNavigate={() => setDrawer(false)} />
                    </div>
                </div>
            )}

            <div className="lg:pl-60">
                {/* Barre supérieure */}
                <header className="sticky top-0 z-20 border-b border-gray-200 bg-white/90 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90">
                    <div className="flex items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
                        <button
                            type="button"
                            onClick={() => setDrawer(true)}
                            className="rounded-md p-2 text-gray-500 hover:bg-gray-100 lg:hidden dark:text-gray-400 dark:hover:bg-gray-800"
                            aria-label="Ouvrir le menu"
                        >
                            <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>

                        <div className="min-w-0 flex-1">{header}</div>

                        <div className="flex items-center gap-1">
                            <Fraicheur className="hidden md:inline-flex" />
                            <NotificationBell />
                            <ThemeToggle compact />
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        type="button"
                                        className="inline-flex items-center rounded-md px-2 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        <span className="hidden sm:inline">{user.name}</span>
                                        <svg className="h-4 w-4 sm:ms-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path
                                                fillRule="evenodd"
                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                clipRule="evenodd"
                                            />
                                        </svg>
                                    </button>
                                </Dropdown.Trigger>
                                <Dropdown.Content>
                                    <div className="border-b border-gray-100 px-4 py-2 text-xs text-gray-500 dark:border-gray-700">
                                        {user.email}
                                    </div>
                                    <Dropdown.Link href={route('aide.indicateurs')}>
                                        Comment lire ces chiffres
                                    </Dropdown.Link>
                                    <Dropdown.Link href={route('profile.edit')}>Profil</Dropdown.Link>
                                    <Dropdown.Link href={route('logout')} method="post" as="button">
                                        Déconnexion
                                    </Dropdown.Link>
                                </Dropdown.Content>
                            </Dropdown>
                        </div>
                    </div>
                </header>

                <main className="px-4 sm:px-0">{children}</main>
            </div>
        </div>
    );
}
