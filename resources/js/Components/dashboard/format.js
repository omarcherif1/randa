// Lisibilité des chiffres (exigence 7) — séparateurs de milliers, unités explicites,
// dates non ambiguës (jour/mois/année).

const fr = new Intl.NumberFormat('fr-FR');
const fr1 = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

export const nInt = (v) => (v == null ? '—' : fr.format(Math.round(v)));
export const n1 = (v) => (v == null ? '—' : fr1.format(v));
export const tonnes = (v) => (v == null ? '—' : `${fr1.format(v)} t`);

export const pct = (v) =>
    v == null ? 'donnée indisponible' : `${fr1.format(v * 100)} %`;

export const dateFr = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('fr-FR');
};

export const dateHeureFr = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

export const moisFr = (ym) => {
    if (!ym) return '';
    const [y, m] = ym.split('-');
    return new Date(y, m - 1, 1).toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
};

export const ETATS = {
    saisie: 'Saisie',
    reservee: 'Réservée',
    expediee: 'Expédiée',
    facturee: 'Facturée',
    cloturee: 'Clôturée',
};
