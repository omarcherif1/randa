import { useEffect, useState } from 'react';

/**
 * Exigence 8 — bascule clair / sombre. Par défaut : préférence système ;
 * le choix explicite est mémorisé (localStorage) et appliqué avant le rendu
 * par le script de resources/views/app.blade.php.
 */
function estSombre() {
    if (typeof document === 'undefined') return false;
    return document.documentElement.classList.contains('dark');
}

export default function ThemeToggle({ compact = false }) {
    const [sombre, setSombre] = useState(estSombre);

    useEffect(() => {
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => {
            try {
                if (!localStorage.getItem('theme')) {
                    document.documentElement.classList.toggle('dark', mq.matches);
                    setSombre(mq.matches);
                    window.dispatchEvent(new Event('theme-change'));
                }
            } catch (e) {
                /* localStorage indisponible */
            }
        };
        mq.addEventListener('change', onChange);
        return () => mq.removeEventListener('change', onChange);
    }, []);

    const basculer = () => {
        const prochain = !sombre;
        document.documentElement.classList.toggle('dark', prochain);
        document.documentElement.style.colorScheme = prochain ? 'dark' : 'light';
        try {
            localStorage.setItem('theme', prochain ? 'dark' : 'light');
        } catch (e) {
            /* ignore */
        }
        setSombre(prochain);
        window.dispatchEvent(new Event('theme-change'));
    };

    return (
        <button
            type="button"
            onClick={basculer}
            aria-pressed={sombre}
            aria-label={sombre ? 'Passer en thème clair' : 'Passer en thème sombre'}
            title={sombre ? 'Thème clair' : 'Thème sombre'}
            className={`inline-flex items-center gap-2 rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200 ${
                compact ? 'p-2' : 'px-3 py-2 text-sm'
            }`}
        >
            {sombre ? (
                <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464L4.343 5.757a1 1 0 011.414-1.414l.707.707A1 1 0 015.05 6.464zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" />
                </svg>
            ) : (
                <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                </svg>
            )}
            {!compact && <span>{sombre ? 'Clair' : 'Sombre'}</span>}
        </button>
    );
}
