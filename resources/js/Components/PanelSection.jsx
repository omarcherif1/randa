/**
 * Section « niveau 2 » avec bandeau de titre — même traitement de surface que
 * `.panel` (bordure nette, `rounded-md`, pas d'ombre — docs/DESIGN.md §3.1),
 * pour un contenu dense découpé en blocs titrés (tables d'administration).
 * Remplace les deux copies locales de `Section` (Admin/Index.jsx,
 * Admin/Settings.jsx) qui avaient dérivé vers l'ancien motif « carte SaaS »
 * (`shadow-sm rounded-lg`, corrigé partout ailleurs).
 */
export default function PanelSection({ title, children, contentClassName = 'p-4' }) {
    return (
        <section className="overflow-hidden rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <h2 className="border-b border-gray-200 bg-gray-50 px-4 py-3 font-semibold text-gray-700 dark:border-gray-800 dark:bg-gray-800/60 dark:text-gray-300">
                {title}
            </h2>
            <div className={contentClassName}>{children}</div>
        </section>
    );
}
