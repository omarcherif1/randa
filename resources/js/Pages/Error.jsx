import { Head, Link } from '@inertiajs/react';

/**
 * Exigence 7 — ne jamais laisser un écran vide sans message explicite, même en
 * cas d'erreur serveur. Rendu par le gestionnaire d'exceptions (bootstrap/app.php).
 */
const MESSAGES = {
    403: {
        titre: 'Accès refusé',
        texte: "Vous n'avez pas les droits pour cette page. Si c'est une erreur, contactez un administrateur.",
    },
    404: {
        titre: 'Page introuvable',
        texte: "Cette adresse n'existe pas ou a changé.",
    },
    419: {
        titre: 'Session expirée',
        texte: 'Votre session a expiré. Reconnectez-vous et réessayez.',
    },
    429: {
        titre: 'Trop de requêtes',
        texte: 'Patientez un instant avant de réessayer.',
    },
    500: {
        titre: 'Erreur inattendue',
        texte: "Une erreur technique s'est produite. Elle a été enregistrée dans les journaux. Réessayez dans un moment.",
    },
    503: {
        titre: 'Service indisponible',
        texte: "L'application est momentanément en maintenance. Réessayez dans quelques minutes.",
    },
};

export default function Error({ status }) {
    const { titre, texte } = MESSAGES[status] ?? MESSAGES[500];

    return (
        <>
            <Head title={titre} />
            <div className="flex min-h-screen flex-col items-center justify-center bg-gray-100 px-4 text-center dark:bg-gray-950 dark:text-gray-200">
                <div className="text-5xl font-bold text-gray-300">{status}</div>
                <h1 className="mt-2 text-xl font-semibold text-gray-800 dark:text-gray-100">{titre}</h1>
                <p className="mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">{texte}</p>
                <Link
                    href="/"
                    className="mt-6 rounded-md bg-gray-800 dark:bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                >
                    Retour à l'accueil
                </Link>
            </div>
        </>
    );
}
