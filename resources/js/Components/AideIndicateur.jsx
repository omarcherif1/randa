import { Link, usePage } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';

/**
 * Exigence 7 (pédagogie intégrée) — aide contextuelle sur un indicateur :
 * définition, exclusions appliquées, règle métier de référence, seuil en vigueur.
 *
 * Le contenu vient du partage Inertia global `glossaire` (source unique,
 * cf. App\Domain\Reporting\IndicatorGlossary). Utilisable au clavier.
 */
export default function AideIndicateur({ cle, className = '' }) {
    const glossaire = usePage().props.glossaire ?? {};
    const info = glossaire[cle];
    const [ouvert, setOuvert] = useState(false);
    const ref = useRef(null);
    const id = useId();

    useEffect(() => {
        if (!ouvert) return;
        const clic = (e) => ref.current && !ref.current.contains(e.target) && setOuvert(false);
        const echap = (e) => e.key === 'Escape' && setOuvert(false);
        document.addEventListener('mousedown', clic);
        document.addEventListener('keydown', echap);
        return () => {
            document.removeEventListener('mousedown', clic);
            document.removeEventListener('keydown', echap);
        };
    }, [ouvert]);

    if (!info) return null;

    return (
        <span className={`relative inline-flex ${className}`} ref={ref}>
            <button
                type="button"
                onClick={() => setOuvert((v) => !v)}
                aria-expanded={ouvert}
                aria-controls={id}
                aria-label={`Comment lire : ${info.titre}`}
                className="inline-flex h-4 w-4 items-center justify-center rounded-full border border-gray-300 dark:border-gray-700 text-[10px] font-bold text-gray-500 dark:text-gray-400 hover:border-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-400"
            >
                ?
            </button>

            {ouvert && (
                <div
                    id={id}
                    role="tooltip"
                    className="absolute left-0 top-6 z-50 w-72 rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 text-left shadow-lg"
                >
                    <div className="text-sm font-semibold text-gray-800 dark:text-gray-100">{info.titre}</div>
                    <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">{info.definition}</p>

                    {info.seuil && (
                        <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">
                            <span className="font-medium">Seuil en vigueur :</span> {info.seuil}
                        </p>
                    )}

                    {info.exclusions?.length > 0 && (
                        <ul className="mt-2 list-disc space-y-0.5 pl-4 text-xs text-gray-500 dark:text-gray-400">
                            {info.exclusions.map((e, i) => (
                                <li key={i}>{e}</li>
                            ))}
                        </ul>
                    )}

                    {info.note && (
                        <p className="mt-2 rounded bg-amber-50 dark:bg-amber-950 px-2 py-1 text-xs text-amber-800 dark:text-amber-200">{info.note}</p>
                    )}

                    <div className="mt-2 flex items-center justify-between border-t pt-2 text-xs">
                        <span className="text-gray-400 dark:text-gray-500">Règle {info.regle}</span>
                        <Link
                            href={route('aide.indicateurs')}
                            className="font-medium text-indigo-600 hover:underline"
                            onClick={() => setOuvert(false)}
                        >
                            Comment lire ces chiffres
                        </Link>
                    </div>
                </div>
            )}
        </span>
    );
}
