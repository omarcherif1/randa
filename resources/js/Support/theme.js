import { useEffect, useState } from 'react';

/**
 * Décision design (docs/DESIGN.md §1.2) — les couleurs société (une couleur =
 * une société, source de vérité : `societes.couleur` en base) n'atteignaient
 * pas un contraste WCAG suffisant sur fond sombre (mesuré : 2,65–3,23:1 sur
 * gray-900, sous le seuil AA texte normal de 4,5:1). Ces variantes gardent
 * EXACTEMENT la même teinte, seule la luminosité est relevée (mesuré ≥ 4,5:1
 * sur gray-900 ET gray-950). Aucune couleur n'est changée, aucune donnée n'est
 * touchée — c'est un ajustement d'affichage uniquement.
 */
const VARIANTES_SOMBRES = {
    '#C8102E': '#EF3A58', // Randa
    '#1D4ED8': '#547BE8', // SMT
    '#047857': '#05966C', // Tunisie Farine
};

export function themeSombreActif() {
    return typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
}

/** Couleur société adaptée au thème courant (clair = inchangée, sombre = variante lisible). */
export function couleurSociete(hex, sombre) {
    if (!hex) return hex;
    const cle = hex.toUpperCase();
    return sombre && VARIANTES_SOMBRES[cle] ? VARIANTES_SOMBRES[cle] : hex;
}

/**
 * Se met à jour quand `ThemeToggle` bascule le thème (événement `theme-change`)
 * ou quand la préférence système change — pour que les couleurs société
 * suivent immédiatement sans recharger la page.
 */
export function useThemeSombre() {
    const [sombre, setSombre] = useState(themeSombreActif);

    useEffect(() => {
        const maj = () => setSombre(themeSombreActif());
        window.addEventListener('theme-change', maj);
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        mq.addEventListener('change', maj);
        return () => {
            window.removeEventListener('theme-change', maj);
            mq.removeEventListener('change', maj);
        };
    }, []);

    return sombre;
}
