# Architecture — Application de pilotage de la satisfaction client (V2)

Groupe Randa (Randa / SMT / Tunisie Farine).

## Pile technique

| Couche | Choix |
|---|---|
| Back | Laravel 12 (PHP 8.2) |
| Front | React 18 + Inertia.js + Tailwind (Laravel Breeze) |
| Base | MySQL / MariaDB (`randa2`) — tests sur SQLite in-memory |
| Tests | PHPUnit 11 |

## Principe directeur : une règle métier = un seul endroit

Toutes les règles de la **section 2** du cahier des charges sont implémentées **une seule fois**,
dans `app/Domain/`. Les pages, exports et agrégats **consomment** le résultat, ils ne recalculent
jamais.

```
app/Domain/
├── Scope/
│   ├── OrderScope.php                 enum Local | Export
│   └── OrderScopeClassifier.php       2.2  export/local par préfixe du n° commande UNIQUEMENT
├── Units/
│   ├── DesignationWeightExtractor.php 2.8  extrait "…N KG…" du texte de la désignation
│   ├── ArticleUnitResolver.php        2.8  SEUL point : poids kg ARTICLE PAR ARTICLE
│   │                                       (KG neutre · SAC/T/QX extraits par article · QX sans indice = hypothèse · sinon exclu)
│   ├── UnitWeightResolution.php             résultat + compteVolume() / volumeFiable()
│   └── UnitWeightOrigin.php                 provenance du poids (preuve de fiabilité)
├── Quality/
│   ├── ExclusionReason.php            2.1  enum des motifs d'exclusion
│   ├── LineExclusionEvaluator.php     2.1  "vraie commande ?" — règles objectives (défaut V1 corrigé)
│   ├── OutlierDetector.php            2.9  valeurs aberrantes (plafond, ratio, statistique)
│   └── OutlierVerdict.php
├── Classification/
│   ├── FamilyColumnDetector.php       2.6  détecte la bonne colonne "Famille statistique"
│   ├── FamilyResolver.php             2.6  famille par CODE ARTICLE COMPLET (jamais un préfixe) ;
│   │                                       mémoire + "inconnue" + consolidate() (reclassé / ambigu)
│   ├── FamilyConsolidation.php              décision par article en fin d'import
│   ├── ClientCategoryClassifier.php   2.7  catégorie client (numéro stable, priorité, mots-clés)
│   └── *Resolution.php
├── Metrics/
│   ├── SatisfactionCalculator.php     2.3  par société, JAMAIS moyenné
│   ├── DelayCalculator.php            2.4  seuil réglable, proxy date de facture, "donnée indisponible" par commande
│   └── StockCoverageCalculator.php    2.5  méthodes A (stock journalier) et B (déduction)
└── Import/
    ├── CsvReader.php                       lecture flux, Windows-1252 → UTF-8
    ├── ColumnMap.php                       champs logiques → index (gère les en-têtes en double)
    ├── ReferenceData.php                   charge les tables de référence, câble les services
    ├── RowNormalizer.php                   applique 2.1→2.9 à une ligne → NormalizedLine
    ├── NormalizedLine.php                  DTO résultat
    ├── ImportManager.php              4.7  POINT D'ENTRÉE UNIQUE : archive, journalise, dispatche
    ├── UnifiedFileValidator.php       3.2  valide structure/colonnes/taille AVANT traitement
    ├── UnifiedFileImporter.php        4.7  moteur : dédoublonnage PAR REMPLACEMENT, transaction
    ├── StockFileImporter.php          2.5  2e format : stock journalier (upsert article+date)
    ├── OrderLifecycle.php             §1   dérive l'état de commande (saisie→…→clôturée, incertain)
    └── AnomalyRecorder.php            2.9  collecte/dédoublonne les anomalies, respecte les décisions admin
```

## Modèle de données (Étape 1)

| Table | Rôle |
|---|---|
| `societes` | Randa / SMT / Tunisie Farine + **couleur unique** (§8) |
| `familles` | 32 familles officielles (§2.6) |
| `client_categories` | 21 catégories (§2.7), dont `is_export` et `is_fallback` |
| `article_unit_weights` | poids kg d'une unité de vente **par article** (§2.8) : `origine`, `en_conflit`, correction admin |
| `classification_keywords` | mots-clés type client, éditables en Paramètres (§2.7) |
| `settings` | seuils réglables (§4.9), valeurs `null` préservées |
| `clients` | **identifiant = numéro** ; catégorie + source ; classifié une seule fois |
| `produits` | article + désignation + famille dénormalisée |
| `article_families` | mémoire **code article complet → famille** (§2.6) : `statut` (confirme/reclassification/ambigu), `historique`, correction admin |
| `commandes` | n° unique, `scope`, société, état de cycle, agrégats **précalculés** |
| `commande_lignes` | quantités **brutes ET en kg**, `is_satisfied`, `exclusion_reason`, `is_outlier`, `line_hash` |
| `stock_snapshots` | stock/article/date (§2.5 méthode A) — 2e format d'import |
| `import_batches` | journal des imports (§4.7/4.8) : volumétrie, anomalies, colonne famille retenue |
| `anomalies` | liste "à vérifier" (§2.9) — `type`, `article`, `signature`, statut `a_verifier`/`validee`/`exclue` |

## Commandes

```bash
php artisan migrate:fresh --seed              # schéma + données de référence
php artisan randa:analyze-source <fichier.csv> # analyse à blanc (contrôle des règles §2, sans écriture)
php artisan randa:import <fichier.csv>         # import persistant (unifié) — --type=stock pour le stock
php artisan test                              # suite complète
php artisan test --testsuite=Unit            # calculs métier purs
```

## Import (Étape 2)

`ImportManager` est le **point d'entrée unique** (CLI aujourd'hui, upload web plus tard —
même moteur). Il archive le fichier sous `storage/app/imports/AAAA/MM/JJ/`, crée l'entrée
`import_batches`, valide (structure/colonnes/taille — jamais transmis tel quel au moteur),
puis délègue. Un échec technique ne remonte jamais à l'utilisateur : détail en logs,
message générique dans le journal.

`UnifiedFileImporter` — tout dans **une transaction** :
1. streame le fichier → `RowNormalizer` (toutes les règles §2) ;
2. consolide les familles (§2.6) et mémorise poids unitaires (§2.8), familles (`article_families`) ;
3. upsert clients (classifiés une fois, jamais rétrogradés) et produits ;
4. **dédoublonnage par remplacement** (§4.7) : commande connue → ses lignes sont supprimées
   puis réécrites, jamais additionnées ; commande nouvelle → ajoutée ;
5. recalcule les agrégats de commande + l'état de cycle de vie ;
6. `AnomalyRecorder` écrit les anomalies (outliers, familles inconnues/ambiguës/reclassées,
   poids indéterminés/en conflit) — en **respectant** les anomalies déjà tranchées par un admin.

Perf mesurée : fichier réel 89 092 lignes / 21 422 commandes / 905 clients ≈ **15 s**.
Ré-import du même fichier : 0 nouvelle, 21 422 remplacées, aucune donnée dupliquée.

## Comptes, droits, sécurité (Étape 3)

`app/Domain/Access/` + `app/Domain/Audit/` :

| Élément | Rôle |
|---|---|
| `Page` | **catalogue unique** des pages protégeables (dont `administration` / `parametres` = `adminOnly`) |
| `AccessControl` | `canAccessPage(user, key)` : admin ⇒ tout · verrouillé ⇒ rien · sinon ligne `page_permissions` |
| `SocieteScope` | filtre RÉEL des données par société (défaut V1 corrigé) — `Commande::scopeVisiblePour($user)` |
| `AccountService` | créer / verrouiller / réinitialiser / droits page-par-page / appliquer un modèle — **chaque action auditée** |
| `AccountSecurity` | verrouillage temporaire après 5 échecs (15 min) + historique des connexions |
| `PasswordPolicy` | min 10, lettres + chiffres, refus des mots de passe évidents (100 % hors-ligne) |
| `AuditLogger` | journal « qui / quoi / quand » des actions sensibles (3.2) |

- **Middleware** : `EnsureAccountActive` (compte verrouillé ⇒ session coupée immédiatement),
  `EnsurePasswordChanged` (changement forcé au 1er login), `page:<key>` (accès page par page).
- **Login durci** (`LoginRequest`) : message générique, verrou compte + verrou temporaire, journalisation.
- Pas d'inscription publique ; comptes créés par un admin (`randa:user:*` ou page Administration).
- **Modèles de permissions** (Direction, Commercial, Transport, Planificatrice, Magasin…) : pré-remplissent
  les droits, ne sont **pas** des rôles — aucune vérification ne les consulte.
- Pages : **Administration** (comptes, droits, journaux audit / connexions / imports) et **Paramètres**
  (seuils réglables, chaque modif auditée), toutes deux réservées aux admins.

Tables : `societe_user`, `page_permissions`, `permission_templates`, `audit_logs`, `login_attempts`
+ colonnes sécurité sur `users`.

CLI : `randa:user:create` · `randa:user:list` · `randa:user:lock [--unlock]` · `randa:user:password`
· `randa:user:access` (grant/revoke/set pages, sociétés, modèle).

Compte de démarrage (local) : `admin@randa.local` / `Admin-Provisoire-2026` — **changement forcé à la 1re connexion**.

## Cache d'agrégats — `App\Domain\Reporting\MetricsCache` (exigence 7)

**Mécanisme UNIQUE pour toute l'application** (tableau de bord, Livraisons, Stock, Export,
Prévisions à venir — aucune page ne recrée le sien) :

- tout agrégat est mis en cache par `(namespace) × (empreinte du jeu de filtres / périmètre
  utilisateur) × (version des données)` ;
- il n'est **jamais** recalculé à chaque affichage ;
- il n'est invalidé que dans **deux cas** :
  1. **nouvel import** → `MetricsCache::bump()` change la version, tous les agrégats deviennent
     obsolètes d'un coup ;
  2. **changement de filtre** → la clé change, seul ce sous-ensemble est (re)calculé, jamais toute la page.
- les tables interactives (commandes / livraisons / export) sont cachées de la même façon —
  la page / le tri / la recherche font partie de la clé.

Perf mesurée (fichier réel 89 092 lignes) : cold ≈ 2,5 s (tableau de bord) / 12–70 ms (autres pages)
→ **réponse en cache < 1 ms** partout.

## Tableau de bord principal (Étape 4)

`app/Domain/Dashboard/` — **exclusivement local** (règle 2.2) :

| Élément | Rôle |
|---|---|
| `DashboardFilter` | filtres (2.10) : société/produit unique · type client/famille multi · période ; porte l'utilisateur pour le filtrage société |
| `DashboardMetrics` | tous les agrégats — part **uniquement** des lignes déjà normalisées (Étapes 1/2), ne recalcule aucune règle §2 |
| `FilterOptions` | valeurs des listes déroulantes (local only) |
| `CommandesTableQuery` | table interactive : recherche / tri / pagination **côté serveur**, résultat caché |

- **Satisfaction (2.3)** : toujours les 3 sociétés côte à côte, une couleur par société, courbe mensuelle ;
  respecte tous les filtres **sauf** société.
- **KPI d'abord**, puis graphiques (chacun avec une **alternative tableau** — accessibilité) ;
  la table des commandes est **différée** (`Inertia::defer`) — chargée après les KPI.
- Filtres **regroupés** : bouton « Appliquer », pas de recalcul à chaque clic (amélioration V2).
- **KPI « valeur des commandes / valeur non livrée » (CDC 4.2) : volontairement RETIRÉS** — pas de
  prix dans la source, et hors périmètre de cette version (ni maintenant ni plus tard).
- **Indisponible et affiché comme tel** : retards (pas de date d'expédition), « ville » = ville de
  commande (pas de livraison).
- Exports : **CSV** (Excel, `;` + BOM) et **rapport PDF** (`barryvdh/laravel-dompdf`, vue `rapports/dashboard`).
- Perf : voir section « Cache d'agrégats » (réponse en cache < 1 ms).

Routes : `dashboard`, `dashboard.export.csv`, `dashboard.rapport` (middleware `page:dashboard`).
Front : `Pages/Dashboard.jsx` + `Components/dashboard/*` (recharts).

## Livraisons · Stock · Export (Étape 5)

Trois pages dédiées, chacune **hérite** des règles/agrégats déjà calculés (mêmes calculateurs
`DelayCalculator` / `StockCoverageCalculator`, flags `is_satisfied` / `compte_volume` posés à
l'import), aucune logique métier dupliquée. Cache partagé via `MetricsCache` (cf. section « Cache d'agrégats »).

| Page | Domaine | Points clés |
|---|---|---|
| **Livraisons** (4.3) | `Delivery\DeliveryMetrics` + `DeliveryTableQuery` | commandes **locales**, état de cycle (§1) + `etat_incertain` ; **retards calculés sur la date de FACTURE** (proxy accepté par l'entreprise, décision 2026-09-11) — une commande pas encore facturée reste « donnée indisponible » pour elle seule, jamais un faux 0 (règle 2.4) ; « ville » étiquetée « ville de commande » (défaut 4.3) |
| **Stock** (4.4) | `Stock\StockMetrics` | **méthode active affichée** (2.5) : B par défaut (déduction commandes ouvertes), A si un fichier stock est importé + setting `stock_methode_active` = 'A' ; statut rupture/alerte/normal par produit ; historique de couverture en méthode A ; module volontairement simple |
| **Export** (4.6) | `Export\ExportMetrics` + `ExportTableQuery` | scope **EXP uniquement** (2.2) — jamais mélangé au local ; satisfaction export par société (même déf. 2.3) ; top destinations / clients / produits export ; **aucune règle locale** (pas de retard 48 h) ; respecte le rattachement société |

Routes : `livraisons`, `stock`, `export` (middleware `page:<key>`). Navigation construite
dynamiquement depuis `auth.pages` (règle 3.1). Front : `Pages/{Livraisons,Stock,Export}/Index.jsx`.

## Prévisions (Étape 6, puis v2 — modèle ML)

`app/Domain/Forecast/` — prévision de la demande par produit (section 4.5), LOCAL uniquement,
cache partagé (`MetricsCache`).

| Élément | Rôle |
|---|---|
| `ForecastEngine` | **calcul pur, déterministe** — apprend TOUJOURS sur la quantité **commandée** (jamais livrée). Paliers de fiabilité selon la longueur d'historique : 1–2 mois → moyenne simple / *faible* · 3–5 mois → tendance (hausse/baisse/stable) + moyenne / *moyenne* · ≥ 6 mois → tendance + **saisonnalité** (même mois n-1 pèse pour moitié) / *haute*. Trous de mois = demande nulle. Prévision jamais négative. `prevoirJusqua()` répète ce calcul mois par mois jusqu'à une cible (ex. décembre). Reste le **repli** si le modèle ML est indisponible, et la **seule** méthode utilisée par `ForecastSnapshotWriter` (voir plus bas). |
| `ForecastBacktest` | **walk-forward** : pour chaque mois passé, recalcule la prévision avec les seules données antérieures et la compare au réel → MAE, **MAPE**, biais. Réutilise `ForecastEngine`. |
| `MlForecastClient` | client HTTP vers `ml-service/` (FastAPI, séparé, voir `ml-service/README.md`) — Random Forest **global** (un seul modèle pour tous les produits, décomposition tendance/résidu par article pour que les tendances continuent de s'extrapoler). Toute panne réseau est absorbée ici (`null`), jamais une exception. |
| `ForecastMetrics` | série mensuelle par article (SUM `qte_commandee_kg`, `compte_volume`), palier + backtest par produit. Interroge `MlForecastClient` ; si le service est indisponible ou pas encore entraîné, **repli automatique** sur `ForecastEngine`. Le champ `methode` (`'ml'`\|`'calcul'`) dit toujours laquelle a produit le résultat affiché — jamais silencieux. `previsionsCalculSeules()` expose le calcul PHP seul, sans appel réseau, pour les usages qui doivent rester déterministes dans le temps. |
| `ForecastSnapshotWriter` | à chaque import : enregistre la photo des prévisions **par société** (`forecast_snapshots`) → historique « prévu vs réel » qui s'enrichit. Utilise **`previsionsCalculSeules()`, jamais le ML** — une photo historique doit rester comparable d'un import à l'autre, indépendamment de si/quand le modèle ML a été réentraîné entretemps. Table avec `qte_a_produire_kg` **réservé V3**. |

La page affiche **le palier ET le backtest** (calcul) ou **les métriques du modèle** (ML, MAE/MAPE/R²
mesurées sur le dernier mois de chaque article, jamais vu à l'entraînement) : « fiabilité haute »
n'est jamais une garantie de précision en soi.

Réentraînement du modèle ML : bouton sur la page Prévisions, visible seulement aux comptes admin
(vérifié aussi côté serveur, `ForecastController::reentrainerModele`) — POST vers `/train` sur tout
l'historique local disponible, jamais un sous-ensemble filtré à l'écran.

Route `previsions` (+ `previsions.reentrainer`) ; CLI `randa:forecast:run` ; front
`Pages/Previsions/Index.jsx` (recharts : historique réel + prévisions backtest + courbe de
prévision jusqu'à décembre).

## Administration / Paramètres complets (Étape 7)

`app/Domain/Admin/` — outils de l'administrateur pour **fiabiliser les chiffres à la main**
quand une règle automatique n'a pas pu trancher. Chaque action : transaction → `MetricsCache::bump()`
(le cache de **toutes** les pages est invalidé) → audit (3.2). Réservé aux admins (`page:administration`
/ `page:parametres`).

| Élément | Rôle |
|---|---|
| `CommandeAggregate` | **source unique** de la ré-agrégation depuis la base : `rafraichirLigne()` repose les flags (`is_eligible`, `is_satisfied`, `compte_volume`, `volume_fiable`), `rafraichirCommande()` recompose les tonnages/compteurs. Utilisé par toutes les corrections admin — jamais de recalcul ad hoc. |
| `AnomalyResolver` | règle 2.9 — l'admin **valide** (vraie valeur → la/les ligne(s) d'une valeur aberrante réintègrent les indicateurs) ou **exclut** définitivement une anomalie. Décision respectée aux ré-imports (`AnomalyRecorder`). |
| `ArticleWeightCorrection` | règle 2.8 — fixe le poids d'une unité de vente : devient `origine = correction_manuelle` (protégé des ré-imports), **répercuté sur toutes les lignes déjà importées** (conversion kg + agrégats recalculés). |
| `ArticleFamilyCorrection` | règle 2.6 — tranche la famille d'un article (inconnue / ambiguë / reclassement) : `statut = correction_manuelle`, répercuté sur le produit et ses lignes. |
| `KeywordService` | règle 2.7 — gestion des mots-clés de classification client **sans intervention technique** (stockés normalisés). `reclasserNonTranches()` ne retouche que les clients « Indéfini » ou « mot-clé » — jamais `reference` / `deduced`. |

Écrans : `Admin/Anomalies` (liste filtrable statut/type + décision en ligne, CDC 4.8),
`Admin/Corrections` (onglets poids / familles, recherche + « à confirmer », correction en ligne),
`Admin/Settings` (seuils réglables + mots-clés groupés par catégorie + bouton de reclassement).
Navigation par onglets `Components/admin/AdminNav.jsx`. Routes : `admin.anomalies[.resolve]`,
`admin.corrections[.poids|.famille]`, `parametres.keywords.{store,update,destroy}`,
`parametres.keywords.reclassify`.

## Notifications (Étape 8)

`app/Domain/Notification/` — centre d'alertes automatiques (section 6). Même cache
que le reste (`MetricsCache`, namespace `notifications`), même règle de visibilité (3.1).

| Élément | Rôle |
|---|---|
| `Alert` | DTO immuable : `type`, `severity` (critique/alerte/info), `titre`, `detail`, lien profond, `reference` stable. |
| `AlertRule` (interface) | `cle()`, `pageRequise()` (accès conditionnant la visibilité, `null` = tout le monde), `evaluer(?User)` → `Alert[]` **déjà filtré au périmètre société**. |
| `NotificationCenter` | agrège les règles de `config/notifications.php`, ne garde que celles dont la personne a l'accès, trie critique → info, met en cache par (périmètre société + pages accessibles) × version. |
| `Rules\RetardsCommandesRule` | section 6 #1 — jusqu'à 5 commandes locales les plus en retard. Verdict via `DelayCalculator` (règle 2.4, proxy date de facture) : **aucune alerte pour une commande pas encore facturée** (jamais un faux retard). |
| `Rules\RupturesStockRule` | section 6 #2 — jusqu'à 5 produits en rupture, couverture la plus faible d'abord. Consomme l'agrégat `StockMetrics` (méthode A/B), ne recalcule rien. |
| `Rules\EchecImportRule` | extension — dernier import unifié en échec (réservé Administration). |
| `Rules\AnomaliesAVerifierRule` | extension — décompte des anomalies en attente d'arbitrage (réservé Administration). |

**Étendre** = une classe `AlertRule` + une ligne dans `config/notifications.php`. Rien d'autre.

Partage Inertia global `notifications` (`{count, alertes, fraicheur}`) → cloche
`Components/NotificationBell.jsx` dans l'en-tête + page `Pages/Notifications/Index.jsx`
(route `notifications`, tout compte connecté ; le contenu reste filtré alerte par alerte).

Invalidation du cache d'alertes : import réussi **ou échoué** (`MetricsCache::bump()` ajouté
dans `ImportManager::fail()`), arbitrage d'anomalie, et **changement d'un seuil réglable**
(`Setting::put()` bumpe désormais le cache quand la valeur change — les seuils 2.x sont
configurables, un changement rend les agrégats obsolètes).

## Polissage professionnel — passe 1 (Étape 9)

Exigence 7 (fiabilité perçue, pédagogie, lisibilité) + exigence 8 (interface).

| Élément | Rôle |
|---|---|
| `Domain\Reporting\IndicatorGlossary` | **définition unique** de chaque indicateur : ce qu'il mesure, exclusions appliquées, règle métier (section 2), seuil réglable en vigueur. Alimente l'aide « ? » de chaque écran **et** la page « Comment lire ces chiffres » — une seule source. |
| `Domain\Reporting\WeeklyReport` | rapport hebdomadaire pour la direction : compose les agrégats déjà cachés de `DashboardMetrics` sur 2 fenêtres de 7 jours (semaine vs semaine précédente), ne recalcule rien, respecte le rattachement société. |
| Partage Inertia global | `glossaire` + `fraicheur` (dernier import), disponibles sur toutes les pages. |
| `Components/AideIndicateur.jsx` | bouton « ? » accessible clavier → définition / exclusions / seuil + lien vers la page d'aide. Câblé sur satisfaction, tonnes, retard, rupture, prévisions, export, cycle de vie. |
| `Components/Fraicheur.jsx` | badge « Données du JJ/MM à HH:MM » cohérent sur tous les en-têtes (remplace les `<span>` disparates). |
| `Components/EtatVide.jsx` | bloc « aucune donnée » explicite (jamais d'écran vide). Tableau de bord : état vide tant qu'aucun import. |
| `Pages/Error.jsx` + `bootstrap/app.php` | page d'erreur explicite (403/404/419/429/500/503) en production ; trace de debug native conservée en local/tests. |
| `Pages/Aide/Indicateurs.jsx` | route `aide.indicateurs` (tout compte connecté) — « Comment lire ces chiffres », rendu depuis le glossaire partagé. |
| Rapport hebdo PDF | route `dashboard.rapport.hebdo` (`?html=1` pour prévisualiser), vue `rapports/hebdomadaire.blade.php`. |

En-têtes de tableaux : normalisés `font-bold text-gray-700` sur toutes les pages (exigence 8).

### Passe 2 — menu latéral + thème sombre (exigence 8)

| Élément | Rôle |
|---|---|
| `Layouts/AuthenticatedLayout.jsx` | **menu latéral hiérarchisé** (fixe sur desktop, tiroir sur mobile) : groupes *Pilotage* / *International* / *Administration* / *Aide*, icônes inline (aucune dépendance), état actif contrasté, liens filtrés par `auth.pages` + `auth.isAdmin`. Barre supérieure fine : titre de page, fraîcheur, cloche, bascule de thème, menu compte. |
| `Components/ThemeToggle.jsx` | bascule clair / sombre ; défaut = préférence système, choix explicite mémorisé (`localStorage`). |
| `resources/views/app.blade.php` | applique la classe `dark` **avant le premier rendu** (pas de flash), `color-scheme` + fond `<html>` par thème. |
| `tailwind.config.js` | `darkMode: 'class'`. |
| `resources/css/app.css` | `@layer base` sombre pour ce que les variantes `dark:` ne couvrent pas : champs de formulaire (`@tailwindcss/forms`), textes / grilles / tooltips Recharts. |
| Variantes `dark:` | ajoutées sur ~43 composants/pages (surfaces, textes, bordures, bannières colorées, badges). Couleurs par société inchangées (une couleur = une société, exigence 8). |

## Tests et fiabilisation (Étape 10)

| Suite | Ce qu'elle garantit |
|---|---|
| `tests/Feature/Export/NoExportLeakTest` | **règle 2.2** — la fixture a une commande export dont le client / produit / ville n'existent que là ; on sérialise chaque surface locale (DashboardMetrics, FilterOptions, tables, DeliveryMetrics, StockMetrics, ForecastMetrics, NotificationCenter, CSV, rapports PDF/hebdo) et on vérifie qu'aucun de ces marqueurs n'apparaît. |
| `tests/Feature/Access/SocieteScopingTest` | **défaut 3.1** — un utilisateur SMT ne voit aucune donnée Randa sur aucune surface ; les comptes correspondent exactement au périmètre ; le bloc satisfaction 2.3 liste les 3 sociétés mais met à zéro celles hors périmètre ; un compte sans société ne voit rien. |
| `tests/Feature/Backup/BackupRestoreTest` | **3.2** — sauvegarde → on casse tout (tables + fichiers) → restauration → tout revient à l'identique ; purge ; commandes artisan ; refus d'une sauvegarde sans manifeste. |

### Sauvegarde / restauration — `app/Domain/Backup/`

| Élément | Rôle |
|---|---|
| `DatabaseBackup` | sauvegarde **logique et portable** (aucun outil externe) : dossier horodaté `backups/AAAAMMJJ_HHMMSS[_label]/` avec `manifest.json`, `tables/<table>.jsonl` (une ligne JSON par enregistrement, via `cursor()`), `imports/…` (copie des fichiers sources archivés à l'import). Purge : `config('backup.conserver')` (14). Tables techniques exclues via `config('backup.tables_ignorees')`. |
| `DatabaseRestore` | vide **toutes** les tables sauvegardées puis les recharge (cohérence FK vraie seulement à la fin → contraintes mises en sommeil selon le pilote : `SET FOREIGN_KEY_CHECKS` / `PRAGMA defer_foreign_keys`), restaure les fichiers importés, `MetricsCache::bump()`. |
| `randa:backup {--label=}` | planifié quotidiennement à 02:00 (`routes/console.php`, `withoutOverlapping`), relançable à la main. |
| `randa:restore {chemin?} {--force}` | restaure le dossier donné (ou le plus récent), destructif, confirmation sauf `--force`. |

Fonctionne à l'identique sur MySQL (production) et SQLite (tests) ; vérifié sur les 89 092 lignes réelles.

## Ce qui n'est PAS encore fait

- Upload web du fichier (réutilisera `ImportManager` tel quel) ; automatisation du dépôt (V3).

Voir `docs/DATA_NOTES.md` pour les constats sur les données réelles et les points à clarifier.
