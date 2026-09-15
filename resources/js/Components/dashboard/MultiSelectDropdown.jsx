import { useEffect, useId, useRef, useState } from 'react';

/**
 * Liste déroulante à sélection multiple qui GARDE l'apparence d'un `<select>`
 * simple une fois fermée (une ligne, même bordure/rayon que les autres champs)
 * — contrairement à `<select multiple>` natif, toujours ouvert en liste haute.
 * Cliquer l'ouvre sur un panneau de cases à cocher.
 *
 * `useId()` donne un identifiant unique à CHAQUE instance (4 filtres sur le
 * tableau de bord) : jamais le même id que ses voisines dans le DOM.
 */
export default function MultiSelectDropdown({
    label,
    value,
    onChange,
    options,
    getKey,
    getValue,
    getLabel,
    placeholder = 'Tous',
}) {
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

    const toggle = (v) => {
        const set = new Set(value);
        set.has(v) ? set.delete(v) : set.add(v);
        onChange([...set]);
    };

    const optionParValeur = (v) => options.find((o) => getValue(o) === v);
    const resume =
        value.length === 0
            ? placeholder
            : value.length === 1
              ? (getLabel(optionParValeur(value[0])) ?? value[0])
              : `${value.length} sélectionnés`;

    return (
        <div className="text-sm" ref={ref}>
            <span className="font-medium text-gray-700 dark:text-gray-300">{label}</span>
            <div className="relative mt-1">
                <button
                    type="button"
                    id={id}
                    onClick={() => setOuvert((v) => !v)}
                    aria-haspopup="listbox"
                    aria-expanded={ouvert}
                    className="flex w-full items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                >
                    <span className="truncate">{resume}</span>
                    <svg
                        className={`h-4 w-4 shrink-0 text-gray-400 transition-transform dark:text-gray-500 ${ouvert ? 'rotate-180' : ''}`}
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fillRule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clipRule="evenodd"
                        />
                    </svg>
                </button>

                {ouvert && (
                    <div
                        role="listbox"
                        aria-multiselectable="true"
                        aria-labelledby={id}
                        className="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-800 dark:bg-gray-900"
                    >
                        {options.length === 0 && (
                            <p className="px-3 py-2 text-xs text-gray-400 dark:text-gray-500">Aucune valeur</p>
                        )}
                        {options.map((o) => {
                            const v = getValue(o);
                            const coche = value.includes(v);
                            return (
                                <label
                                    key={getKey(o)}
                                    className="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800"
                                >
                                    <input
                                        type="checkbox"
                                        checked={coche}
                                        onChange={() => toggle(v)}
                                        className="rounded border-gray-300 dark:border-gray-700"
                                    />
                                    <span className="truncate">{getLabel(o)}</span>
                                </label>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
}
