import AideIndicateur from '@/Components/AideIndicateur';

/**
 * Tuile KPI unique, réutilisée sur toutes les pages (Tableau de bord, Livraisons,
 * Stock, Export, Prévisions) — décision design : une seule taille de chiffre
 * intentionnelle partout (`text-2xl`, chiffres tabulaires), plus de dérive
 * entre pages. Étiquette en casse normale (plus de MAJUSCULES ESPACÉES) : la
 * hiérarchie est portée par la taille/graisse, pas par la casse.
 *
 * `tone` porte un sens de statut, JAMAIS une couleur société (règle : les deux
 * échelles ne doivent jamais pouvoir se confondre — voir docs/DESIGN.md §1.2).
 */
const TON_COULEUR = {
    normal: 'text-gray-900 dark:text-gray-100',
    critique: 'text-violet-600 dark:text-violet-400',
    attention: 'text-amber-600 dark:text-amber-400',
    // vert-600, pas émeraude : trop proche de la teinte Tunisie Farine (voir app.css).
    ok: 'text-green-600 dark:text-green-400',
    // Rouge explicitement demandé pour certaines tuiles — malgré la collision
    // connue avec le rouge de société Randa (voir docs/DESIGN.md §1.2), qui a
    // motivé le remplacement du rouge par du violet pour « critique » ailleurs.
    // N'utiliser « rouge » qu'à la demande explicite, jamais par défaut.
    rouge: 'text-red-600 dark:text-red-400',
};

export default function Kpi({ label, value, hint, tone = 'normal', alert = false, aide, className = '' }) {
    const t = alert ? 'critique' : tone;

    return (
        <div className={`card-kpi ${className}`}>
            <div className="flex items-center gap-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                {label}
                {aide && <AideIndicateur cle={aide} />}
            </div>
            <div className={`mt-1 text-2xl font-bold tabular-nums ${TON_COULEUR[t] ?? TON_COULEUR.normal}`}>
                {value}
            </div>
            {hint && <div className="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{hint}</div>}
        </div>
    );
}
