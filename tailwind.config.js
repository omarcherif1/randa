import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Exigence 8 — variante d'affichage sombre (salle de pilotage / session longue).
    // Activée par la classe `dark` posée sur <html> (cf. resources/views/app.blade.php).
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // Écran de connexion — dérive très lente du champ de grains
            // (signature de marque). `motion-safe:` respecte déjà la
            // préférence système ; durée longue et amplitude faible pour
            // rester ambiant, jamais une animation qui attire l'œil.
            keyframes: {
                'grain-drift': {
                    '0%, 100%': { transform: 'scale(1) translate(0, 0)' },
                    '50%': { transform: 'scale(1.03) translate(-1%, 1%)' },
                },
            },
            animation: {
                'grain-drift': 'grain-drift 40s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
