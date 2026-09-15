# Design — système de tokens & direction visuelle

> Statut : **7 décisions validées et appliquées (2026-09-11)**. Ce document est la
> source de vérité du système de tokens ; toute page nouvelle ou modifiée doit s'y
> conformer. Historique : audit initial → 7 décisions du commanditaire → application au
> code (`resources/css/app.css`, `resources/js/Components/Kpi.jsx`,
> `resources/js/Support/theme.js`, et propagation à toutes les pages déjà construites).

## 0. Le sujet, pour mémoire

Outil de pilotage interne d'un groupe agro-industriel (Randa / SMT / Tunisie Farine —
pâtes, couscous, semoule, farine), utilisé plusieurs fois par jour par la direction, les
commerciaux, la logistique et le magasin pour agir (retard à traiter, rupture à
anticiper). Ton juste : salle de contrôle / ERP sérieux, pas produit grand public.
Clarté et vitesse de lecture d'abord, esthétique ensuite.

---

## 1. Tokens couleur

Définis dans `resources/css/app.css` (`:root`), avec justification mesurée ci-dessous.

### 1.1 Neutres

| Rôle | Clair | Sombre | Utilisation |
|---|---|---|---|
| Fond de page | `gray-100` | `gray-950` | layout racine (`AuthenticatedLayout`) |
| Fond de carte/panneau | `white` | `gray-900` | `.card-kpi`, `.panel`, barre latérale |
| Fond subtil | `gray-50` | `gray-800/60` | lignes secondaires, en-têtes de section |
| Bordure | `gray-200` / `gray-300` | `gray-800` / `gray-700` | séparateurs, champs |
| Texte principal | `gray-900` / `gray-800` | `gray-100` | titres, valeurs |
| Texte secondaire | `gray-500` / `gray-600` | `gray-400` | légendes, aide |
| Texte tertiaire | `gray-400` | `gray-500` | métadonnées |

### 1.2 Couleurs par société — DÉCISION 1 appliquée

Source de vérité inchangée : `societes.couleur` en base. Nommées en tokens CSS
(`--societe-randa/-smt/-tf`) dans `app.css` pour être citables.

| Société | Hex (clair) | Hex (sombre, même teinte) |
|---|---|---|
| Randa | `#C8102E` | `#EF3A58` |
| SMT | `#1D4ED8` | `#547BE8` |
| Tunisie Farine | `#047857` | `#05966C` |

**Pourquoi une variante sombre** : mesuré (WCAG, luminance relative) — les couleurs
société sur fond `gray-900` ne passaient PAS le seuil AA texte normal (4,5:1) :
Randa 3,02:1, SMT 2,65:1, TF 3,23:1. Chaque variante sombre garde exactement la même
teinte (H, S identiques dans l'espace HSL), seule la luminosité est relevée jusqu'à
franchir 4,5:1 sur `gray-900` **et** `gray-950` :

| | Contraste sur gray-900 | Contraste sur gray-950 |
|---|---|---|
| Randa `#EF3A58` | 4,58:1 | 5,19:1 |
| SMT `#547BE8` | 4,54:1 | 5,15:1 |
| TF `#05966C` | 4,72:1 | 5,36:1 |

Appliqué via `resources/js/Support/theme.js` (`couleurSociete(hex, sombre)` +
`useThemeSombre()`, qui se resynchronise sur l'événement `theme-change` émis par
`ThemeToggle`) partout où une couleur société est utilisée en style inline : bloc
satisfaction et courbe (Tableau de bord), bloc satisfaction export (Export).

**Collision rouge Randa / rouge « critique » — corrigée.** Le rouge Randa (`#C8102E`,
teinte 350°) n'est plus jamais utilisé pour un statut. La sévérité « critique » utilise
désormais le **violet** (`#7C3AED`, teinte 258°) — écart de teinte ≈ 92°, à l'opposé du
rouge, choix conforme à la demande et sûr pour la déutéranomalie (l'axe bleu-violet
n'est pas celui que confond ce type de daltonisme). Variante sombre mesurée :
`#9864F1` (4,59:1 sur gray-900, 5,20:1 sur gray-950).

**Extension proactive — vert « ok » séparé du vert Tunisie Farine.** En construisant le
token `--statut-ok`, le choix initial (émeraude, `#059669`, teinte 161°) s'est révélé
quasi identique à la teinte TF (`#047857`, teinte 163°, écart ≈ 2°) : exactement le même
risque que rouge Randa/critique, non demandé explicitement mais découvert en
construisant le système. Remplacé par **vert-600** (`#16A34A`, teinte 142°, écart ≈ 21°
avec TF) — reste lisible comme « vert/ok », déjà ≥ 4,5:1 sur `gray-900`/`gray-950` sans
ajustement de luminosité nécessaire. *Signalé explicitement : cette extension n'était
pas dans les 7 décisions demandées — à confirmer ou révoquer.*

### 1.3 Échelle sémantique de statut — une seule, nommée, appliquée partout

```
--statut-critique  #7C3AED (sombre #9864F1)   violet — jamais rouge
--statut-attention #D97706 (sombre #FBBF24)   ambre
--statut-ok        #16A34A                    vert-600 (pas émeraude, cf. §1.2)
--statut-neutre    #6B7280                    gris
```

Portée par [`Components/Kpi.jsx`](../resources/js/Components/Kpi.jsx) (prop `tone` :
`normal|critique|attention|ok`) et par les classes `.banner-*` (§3). Les 4 déclarations
locales dupliquées qui existaient (`NotificationBell`, `Anomalies.jsx`, `Stock/Index.jsx`,
`Previsions/Index.jsx`) réutilisent maintenant cette même échelle de couleurs (rouge→violet,
émeraude→vert-600), même si chaque écran garde sa propre table de libellés métier.

**Portée volontairement EXCLUE** de ce remplacement rouge→violet : `DangerButton` (action
destructrice — verrouiller/supprimer/révoquer — convention universelle « rouge = danger »,
sémantique différente d'un statut) et `InputError`/erreurs de validation de formulaire
(convention universelle « rouge = champ invalide »). Ces deux cas restent rouges par
choix, pas par oubli — ils n'apparaissent jamais à côté d'un élément coloré par société
d'une façon qui prêterait à confusion.

### 1.4 Dégradés décoratifs

Aucun dans le code — conforme, rien à faire.

---

## 2. Typographie — DÉCISIONS appliquées

- **Police : Figtree, conservée**, seule famille. C'est le choix par défaut de Laravel
  Breeze, mais neutre et lisible ; aucune décision explicite demandée pour en changer —
  gardée telle quelle.
- **Taille des chiffres KPI : une seule, partout.** `text-2xl font-bold tabular-nums`,
  posée une fois dans [`Components/Kpi.jsx`](../resources/js/Components/Kpi.jsx) et
  réutilisée par les 5 pages qui affichent des KPI (Tableau de bord, Livraisons, Stock,
  Export, Prévisions) — plus de dérive `text-2xl` vs `text-xl` selon la page.
  `tabular-nums` ajouté pour que les chiffres ne « sautent » plus visuellement.
- **Étiquettes MAJUSCULES ESPACÉES : retirées partout**, y compris les boutons Breeze
  (`Primary/Secondary/DangerButton` : `text-xs uppercase tracking-widest` →
  `text-sm font-semibold`, casse normale), les libellés de KPI (13 endroits), les
  en-têtes de section Admin, les groupes du menu latéral. La hiérarchie est maintenant
  portée par la taille et la graisse, jamais par la casse. Vérifié : 0 occurrence
  d'`uppercase` restante dans `resources/js` en dehors de contenu non textuel.

---

## 3. Mise en page — DÉCISIONS appliquées

### 3.1 Hiérarchie de surface à 3 niveaux (remplace le « tout-carte »)

Classes définies dans `app.css` (`@layer components`), à utiliser partout plutôt que de
récrire `rounded-lg bg-white shadow-sm` à la main :

| Classe | Niveau | Traitement | Exemple |
|---|---|---|---|
| `.card-kpi` | 1 — peu de contenu, à retenir d'un coup d'œil | `rounded-lg`, ombre légère | tuiles KPI (`Kpi.jsx`) |
| `.panel` | 2 — contenu dense (tableaux, graphiques, filtres) | `rounded-md`, bordure nette, **pas d'ombre** | `ChartCard`, tables de commandes, blocs de filtres |
| `.banner-info` / `.banner-attention` / `.banner-critique` / `.banner-succes` | 3 — message, pas une donnée | bordure gauche colorée, pas d'ombre, pas d'arrondi à gauche | bannières d'info/avertissement/alerte/confirmation |

Propagé à toutes les pages déjà construites (Tableau de bord, Livraisons, Stock, Export,
Prévisions, Administration ×4, Notifications, Aide) : plus aucune occurrence du motif
`rounded-lg bg-white … shadow-sm` appliqué indifféremment à un KPI, un tableau ou une
bannière.

### 3.2 Gabarit de page unifié

**Une seule valeur** pour les pages listées par le commanditaire (Tableau de bord,
Livraisons, Stock, Export, Prévisions, Administration — ses 4 écrans) :
`mx-auto max-w-7xl space-y-6 py-6 sm:px-6 lg:px-8`.

Exception assumée et documentée (pages hors de cette liste, contenu différent — prose /
liste courte, pas de tableau dense) : Aide (`max-w-3xl`) et Notifications (`max-w-4xl`)
gardent une largeur plus étroite adaptée à leur contenu, mais partagent désormais le
même `py-6` que le reste de l'application (plus de `py-8` isolé sans raison).

### 3.3 Rayons et ombres

Échelle documentée, inchangée dans son principe : `rounded-md` pour les contrôles
(boutons, champs), `rounded-lg` réservé au niveau 1 (`.card-kpi`), `rounded-full` pour
les badges/pastilles. Le niveau 2 (`.panel`) utilise `rounded-md` + bordure, jamais
d'ombre — c'est le changement principal de la passe de correction (§3.1).

### 3.4 Boutons Breeze — DÉCISION appliquée

`PrimaryButton` / `SecondaryButton` / `DangerButton` : casse normale, `text-sm
font-semibold`, plus de `uppercase tracking-widest`. `SecondaryButton` perd aussi son
ombre décorative (cohérence avec §3.1 : un bouton est un contrôle, pas une carte).
`DangerButton` reste rouge (portée exclue, §1.3).

---

## 4. Comparaison avec les tics à éviter — état après correction

| Tic générique | Avant | Après |
|---|---|---|
| Fond crème + titre serif + accent terracotta | Absent | Absent (inchangé) |
| Fond quasi noir + accent vert acide/vermillon | Absent | Absent (inchangé) |
| « Kit carte SaaS » | Présent, systémique | **Corrigé** — hiérarchie à 3 niveaux (§3.1) |
| Étiquettes MAJUSCULES ESPACÉES | Présent, 13 endroits | **Corrigé** — 0 occurrence restante |
| Points médians (A · B · C) | Présent, 6 endroits | **Corrigé** — reformulés au cas par cas (virgule, parenthèses, ou phrase complète selon le contexte) |
| « MOT — fragment » | Présent, répété | **Corrigé** dans les titres/messages ; le tiret seul `'—'` (valeur vide) et le motif « code — libellé » dans les listes déroulantes (convention de données, pas un titre décoratif) sont volontairement conservés |
| Flèche « → » systématique | Présent, 1 endroit | **Corrigé** — retirée de « Ouvrir la page concernée » ; les 2 usages restants de `→` sont fonctionnels (icône de tendance stable, chaîne de reclassement historique), pas des flèches de lien |
| Mot accentué sans raison | Absent | Absent (inchangé) |
| Marqueurs 01/02/03 décoratifs | Absent | Absent (inchangé) |

**Bilan : les 4 tics présents sont corrigés ; les 4 déjà absents le restent.**

---

## 5. Mouvement — DÉCISION appliquée

- `prefers-reduced-motion` : règle globale ajoutée dans `app.css` — neutralise toute
  animation/transition non essentielle pour qui a activé la préférence système.
- Seule animation fonctionnelle conservée : transition d'ouverture des menus (Headless UI
  `Transition` dans `Dropdown.jsx`) — déjà conforme, pas décorative.
- Toujours aucune animation d'entrée systématique sur les cartes/sections au chargement.

---

## 6. Contenu / texte

- Vocabulaire déjà côté utilisateur, cohérent (« clôturée » partout, jamais « terminée »).
- Quelques libellés de bouton resserrés au passage (« OK » → « Rechercher »).
- Messages reformulés sans motif générique restent fidèles au sens d'origine (aucun
  changement de règle métier, uniquement de formulation).

---

## 7. Plancher qualité — état après correction

| Exigence | État |
|---|---|
| Responsive jusqu'au mobile | Couvert (menu tiroir, Étape 9), inchangé par cette passe |
| **Focus clavier visible partout** | **Corrigé** — anneau de focus unique posé en couche de base (`app.css`, `:focus-visible` sur tout élément interactif), ne dépend plus d'une classe posée composant par composant |
| **Contraste clair/sombre des couleurs société** | **Mesuré et corrigé** (§1.2) — ≥ 4,5:1 sur `gray-900`/`gray-950` en thème sombre |
| **`prefers-reduced-motion` respecté** | **Corrigé** (§5) |
| Couleurs société cohérentes en thème sombre | Acquis, renforcé (variantes lisibles ajoutées) |

---

## 8. Points restés hors de cette passe (signalés, pas traités)

- Extraction des neutres (`bg-white dark:bg-gray-900`, etc.) en tokens CSS nommés — au
  lieu de dépendre uniquement des classes `.card-kpi`/`.panel` qui les enferment déjà, un
  jeu de variables CSS pour le texte serait la suite logique si de nouvelles pages
  s'ajoutent.
- Vert « ok » séparé du vert Tunisie Farine (§1.2) — extension proactive, à confirmer.
- Audit ligne par ligne de la cohérence du vocabulaire des boutons sur toute
  l'application (fait par sondage, pas exhaustif).
