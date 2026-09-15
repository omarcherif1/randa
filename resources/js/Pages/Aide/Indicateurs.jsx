import Fraicheur from '@/Components/Fraicheur';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';

/**
 * Exigence 7 — « Comment lire ces chiffres ». Contenu issu du glossaire partagé
 * (App\Domain\Reporting\IndicatorGlossary), tenu à jour avec les règles de la
 * section 2. Mêmes définitions que l'aide « ? » de chaque écran.
 */
export default function Indicateurs() {
    const glossaire = usePage().props.glossaire ?? {};
    const entrees = Object.entries(glossaire);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Comment lire ces chiffres</h2>
                    <Fraicheur />
                </div>
            }
        >
            <Head title="Comment lire ces chiffres" />

            <div className="mx-auto max-w-3xl space-y-4 py-6 sm:px-6 lg:px-8">
                <p className="banner-info">
                    Chaque indicateur de l'application est défini ici, avec les exclusions
                    appliquées et la règle métier de référence. Cette page reste alignée avec
                    les règles validées ; les seuils affichés sont ceux réellement en vigueur.
                </p>

                <nav className="flex flex-wrap gap-2 text-xs">
                    {entrees.map(([cle, info]) => (
                        <a
                            key={cle}
                            href={`#${cle}`}
                            className="rounded-full bg-white dark:bg-gray-900 px-3 py-1 text-gray-600 dark:text-gray-400 shadow-sm hover:bg-gray-100 dark:hover:bg-gray-800"
                        >
                            {info.titre}
                        </a>
                    ))}
                </nav>

                <div className="space-y-3">
                    {entrees.map(([cle, info]) => (
                        <section
                            key={cle}
                            id={cle}
                            className="panel scroll-mt-24 p-5"
                        >
                            <div className="flex items-baseline justify-between gap-3">
                                <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-100">{info.titre}</h3>
                                <span className="shrink-0 text-xs text-gray-400 dark:text-gray-500">Règle {info.regle}</span>
                            </div>
                            <p className="mt-1 text-sm text-gray-700 dark:text-gray-300">{info.definition}</p>

                            {info.seuil && (
                                <p className="mt-2 text-sm text-gray-700 dark:text-gray-300">
                                    <span className="font-medium">Seuil en vigueur : </span>
                                    {info.seuil}
                                </p>
                            )}

                            {info.exclusions?.length > 0 && (
                                <>
                                    <div className="mt-3 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                        Exclusions et précisions
                                    </div>
                                    <ul className="mt-1 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-400">
                                        {info.exclusions.map((e, i) => (
                                            <li key={i}>{e}</li>
                                        ))}
                                    </ul>
                                </>
                            )}

                            {info.note && (
                                <p className="banner-attention mt-3">
                                    {info.note}
                                </p>
                            )}
                        </section>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
