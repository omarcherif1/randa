import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

/**
 * Écran d'accès (connexion, mot de passe oublié, etc.) — section 8 : sobre,
 * professionnel, identité du groupe visible dès le premier écran, pas un
 * gabarit d'authentification générique.
 *
 * Signature : un champ de semoule généré (grains de blé dur, la matière du
 * groupe) sur le panneau de marque, avec la légende des trois sociétés — le
 * seul fait vraiment propre à cette application (une couleur = une société).
 */

const SOCIETES = [
    { nom: 'Randa', couleur: '#C8102E' },
    { nom: 'SMT', couleur: '#1D4ED8' },
    { nom: 'Tunisie Farine', couleur: '#047857' },
];

// Champ de grains généré une fois par chargement de page — dispersion
// organique, jamais deux fois identique, jamais recalculé au re-rendu.
const GRAINS = Array.from({ length: 110 }, () => ({
    cx: Math.random() * 100,
    cy: Math.random() * 100,
    r: 0.25 + Math.random() * 1.35,
    o: 0.12 + Math.random() * 0.45,
}));

function ChampDeGrains() {
    return (
        <svg
            className="pointer-events-none absolute inset-0 h-full w-full motion-safe:animate-grain-drift"
            viewBox="0 0 100 100"
            preserveAspectRatio="xMidYMid slice"
            aria-hidden="true"
        >
            {GRAINS.map((g, i) => (
                <circle key={i} cx={g.cx} cy={g.cy} r={g.r} fill="#E8B84B" opacity={g.o} />
            ))}
        </svg>
    );
}

function PanneauDeMarque({ headline, tagline }) {
    return (
        <div className="relative isolate flex h-48 shrink-0 flex-col justify-between overflow-hidden bg-[#241608] px-6 py-6 sm:px-10 sm:py-8 lg:h-auto lg:w-[44%] lg:px-14 lg:py-12">
            {/* Fond : dégradé blé-dur profond, jamais un noir pur ni un accent néon. */}
            <div className="absolute inset-0 bg-gradient-to-br from-[#3A2410] via-[#241608] to-[#140B04]" />
            <div className="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-[#E8B84B]/10 blur-3xl" />
            <ChampDeGrains />

            <Link href="/" className="relative z-10 flex items-center gap-3">
                <ApplicationLogo className="h-10 w-auto rounded bg-white/95 p-1" />
                <span className="text-sm font-semibold tracking-tight text-white/90">
                    Groupe Randa
                </span>
            </Link>

            <div className="relative z-10 hidden lg:block">
                <h1 className="max-w-sm text-3xl font-extrabold leading-[1.15] tracking-tight text-white">
                    {headline}
                </h1>
                <p className="mt-4 max-w-xs text-sm leading-relaxed text-[#F6D998]/80">
                    {tagline}
                </p>
            </div>

            <div className="relative z-10 flex flex-wrap items-center gap-x-5 gap-y-1.5">
                {SOCIETES.map((s) => (
                    <span key={s.nom} className="inline-flex items-center gap-1.5 text-xs font-medium text-white/70">
                        <span
                            className="h-2 w-2 rounded-full"
                            style={{ backgroundColor: s.couleur }}
                            aria-hidden="true"
                        />
                        {s.nom}
                    </span>
                ))}
            </div>
        </div>
    );
}

export default function GuestLayout({
    children,
    headline = 'Une vision claire, du blé à l’assiette.',
    tagline = 'Pâtes, couscous, semoule, farine : le pilotage de la satisfaction client pour tout le groupe, en un seul endroit.',
}) {
    return (
        <div className="flex min-h-screen flex-col bg-gray-50 dark:bg-gray-950 lg:flex-row">
            <PanneauDeMarque headline={headline} tagline={tagline} />

            <div className="flex flex-1 items-center justify-center px-6 py-10 sm:px-10">
                <div className="w-full max-w-sm">{children}</div>
            </div>
        </div>
    );
}
