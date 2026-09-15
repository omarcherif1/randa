# Notes sur les données source — observations réelles

> Constats tirés de l'analyse du fichier `F1787140089784_OMSTAGE.csv` (89 092 lignes,
> 21 422 commandes, sites RAN01 + SMT01) et des tables `Table client.csv` / `Table famille.csv`.
> Reproductible avec : `php artisan randa:analyze-source "F1787140089784_OMSTAGE.csv"`

## Format du fichier unifié

| Élément | Valeur observée |
|---|---|
| Séparateur | `;` |
| Encodage | Windows-1252 (Latin-1) |
| Date | `JJ/MM/AAAA` |
| Colonnes | 24, avec doublons d'en-tête : `Date commande` ×2, `Famille statistique` ×6 |
| Sociétés | `RAN01` (Randa), `SMT01` (SMT). **Aucune ligne Tunisie Farine** dans cet extrait. |
| Préfixes n° commande | `CMRAN`, `CMSMT` (local) · `EXPRAN`, `EXPSMT` (export) |
| Export | 543 lignes / 115 commandes — écartées du tableau de bord (règle 2.2) |
| États de ligne | `Soldé` (99,1 %), `Attente` (0,9 %) |

## Colonne "Famille statistique" (règle 2.6)

6 colonnes portent ce nom. Deux contiennent des codes valides (100–131) :

| Index (0-based) | Couverture | Validité | Sémantique |
|---|---|---|---|
| 17 | ~8 % | 0 % | texte `STAT1` — ignorée |
| **18** | 100 % | 100 % | **pôle produit réel** (106 PATES NORMALES, 102 FARINES, 100 COUSCOUS…) → **retenue** |
| 19 | 100 % | 100 % | codes valides mais incohérents (pâtes → « DECHETS »/« RISTOURNES ») |
| 20–22 | — | 0 % | `QX`, `STAT1`, `32/42` — ignorées |

➡️ L'auto-détection retient la colonne **valide la plus à gauche (18)** et **signale l'ambiguïté**.
Un admin peut forcer le choix via le paramètre `famille_colonne_index`.

### Résolution article → famille (clé = code article **complet**)

Sur 150 articles distincts (colonne 18) :

| Cas | Nb | Traitement |
|---|---|---|
| Famille **stable** (toujours la même) | 144 | mémorisée telle quelle |
| **Reclassée dans le temps** (familles à plages de dates disjointes) | 3 | garde la plus récente, journalise l'historique |
| **Ambiguë** (familles entremêlées sans logique de date) | 3 | `famille ambiguë — confirmation manuelle requise`, aucun choix automatique |

- Reclassées : `134237` (107→121, coupure fin mars 2026), `418221` (107→130), `419221` (107→130).
- Ambiguës : `134235` (107/121), `514127` (101/130), `512122` (101/130).
- ⚠️ Le CDC §5 cite `134237` comme exemple « ambigu » ; sur les données réelles ses plages sont
  **nettement disjointes** → classé « reclassé » (retient 121). Il reste dans la file « à confirmer »
  admin de toute façon — à valider avec l'entreprise.
- **Jamais** de déduction par préfixe du code article : des articles au même préfixe à 3 chiffres
  ont des familles différentes (test explicite).

## Unités de vente (règle 2.8 — bug critique V1)

Le champ **« Unité vente »** ne suffit PAS à choisir un facteur : pour `SAC` **et** `T`,
la quantité est un **nombre de sacs** dont le poids dépend de l'article. Le poids réel est
dans la **désignation** (« …20 KG », « …50 Kg AT », « …1 KG… »).

| Code | Occurrences | Poids retenu | Origine |
|---|---|---|---|
| `KG` | 73 907 | — (aucune conversion) | neutre |
| `SAC` | 10 703 | 5 / 10 / 20 / 50 kg **selon l'article** | **extrait de la désignation** (100 %) |
| `T` | 19 | 1 / 50 kg **selon l'article** (jamais des tonnes) | **extrait de la désignation** (100 %) |
| `QX` | 4 463 | **selon l'article** ; 100 kg seulement si rien d'extractible | extrait de la désignation, sinon hypothèse |

➡️ **Décision entreprise (2026-09-11) : il n'existe PAS de facteur unique pour `QX`**, exactement
comme `SAC`/`T` — chaque article a son propre poids. `ArticleUnitResolver` tente d'abord
l'extraction depuis la désignation pour **toute** unité non-KG ; l'hypothèse 100 kg (setting
`qx_poids_kg_hypothese`, tonnage marqué « à confirmer ») ne sert plus que de filet de sécurité
quand rien n'est extractible (ex. « Farine PS en sac perdus »). Si aucun poids n'est trouvable
pour une unité non-QX, la ligne est **exclue des tonnages** (comptée à part), jamais un facteur inventé.

Cas `QX` article **501008** (« Tchich 25KG ») : résolu → **25 kg, extrait de la désignation,
fiable** (n'est plus une hypothèse à 100 kg). Tonnages déjà importés recalculés.

## Valeurs aberrantes (règle 2.9)

- `CMSMT012608966` : `Semoule_fine_20KG` — **200 504 005 SAC** commandés / 99 livrés → erreur de
  saisie évidente. **Exclue définitivement** (décision entreprise 2026-09-11).
- `CMRAN012604867` : `Déchet des dérivés industriels` — 9 lignes à **1 000 000 kg** commandés
  chacune (33 960 kg livrés) → **exclue définitivement** (décision entreprise 2026-09-11).
  *Point encore ouvert : la même famille de commandes porte aussi des lignes à 100 000 kg
  (≈ 27× la médiane de l'article, commandes CMRAN012606815/012604438/012605812) — pas tranché,
  laissées telles quelles en attendant confirmation.*
- Constat d'investigation : le contrôle **statistique par article** (`OutlierDetector::statisticalOutliers()`,
  niveau 2 de la règle 2.9, déjà codé et testé) n'est **pas branché** dans le pipeline d'import —
  seul le contrôle ligne par ligne (plafond, ratio commande/livraison) l'est. Le brancher tel quel
  (seuil `outlier_ratio_mediane_max` = 25×) a été essayé puis annulé : ~614 lignes signalées sur le
  jeu réel, trop pour rester exploitable (la plupart ressemblent à des grosses commandes légitimes,
  pas des erreurs de saisie). À reprendre avec un seuil revu avec l'entreprise plutôt que la valeur
  par défaut actuelle.

## ⚠️ Risque à vérifier — dédoublonnage par remplacement et extraits partiels

**Statut : constaté et prouvé par un test (2026-09-11), PAS corrigé — en attente de confirmation
avec l'encadreur sur la nature réelle des fichiers Sage.** Aucune modification du mécanisme n'a
été faite à ce stade ; ceci documente uniquement ce que le code fait réellement aujourd'hui.

**Le mécanisme actuel** (`UnifiedFileImporter::persistCommandes`, règle 4.7) : quand une commande
déjà connue réapparaît dans un import, **toutes ses lignes existantes sont supprimées puis
réécrites** avec exactement ce que contient le nouveau fichier pour cette commande — jamais
fusionnées ligne par ligne, jamais additionnées.

- **Sans risque** si chaque fichier contient l'état COMPLET d'une commande qu'il mentionne.
- **Perte de données silencieuse** si un fichier est un **extrait partiel** (ne liste que les
  lignes modifiées sur sa période) : les lignes réelles et toujours valides absentes du nouvel
  extrait sont effacées, sans anomalie, sans avertissement.

**Test réalisé** (`tests/Feature/Domain/PartialReimportRiskTest.php`), pipeline réel
(`ImportManager` → `UnifiedFileImporter`), deux fixtures :

| | Import 1 (période A) | Import 2 (période B, chevauche A) |
|---|---|---|
| Commande | `CM-TEST-001` | `CM-TEST-001` (même commande) |
| Lignes listées | Produit A (qté 100), Produit B (qté 50), Produit C (qté 200) | **Seulement** Produit B (qté 50, maintenant livrée) |

**Résultat observé après l'import 2** (aucune interprétation, sortie brute du test) :

```
Nombre de lignes : 1
Détail : [{"article":"700802","qte_commandee":50,"qte_livree":50}]
commande.lignes_total      : 1     (était 3 après l'import 1)
commande.lignes_eligibles  : 1
commande.qte_commandee_kg  : 50    (était 350 après l'import 1 — 100+50+200)
commande.qte_livree_kg     : 50
batch2.commandes_replaced  : 1
batch2.anomalies_count     : 0
Anomalie signalant la disparition de A/C : AUCUNE
```

➡️ **Les lignes A (produit 700801) et C (produit 700803) ont bien disparu**, silencieusement,
sans anomalie ni avertissement. Le batch d'import est marqué `completed` normalement. **Réponse à
la question posée : après l'import 2, CM-TEST-001 ne contient plus QUE la ligne B — les lignes A
et C sont perdues, pas fusionnées.**

**Ce qui n'a volontairement PAS été fait** : le mécanisme de remplacement n'a pas été modifié.
Cette section documente le comportement actuel comme preuve à discuter avec l'encadreur — la
question de fond (les fichiers Sage réels sont-ils toujours des états complets par commande, ou
parfois des extraits partiels ?) reste ouverte et conditionne la décision : ne rien changer (si
toujours complets) ou fusionner ligne par ligne au niveau produit plutôt qu'écraser toute la
commande (si un extrait partiel est possible en pratique).

## Retards de livraison (règle 2.4) — **décision tranchée : date de facture en proxy**

Le fichier ne contient **qu'une seule date réelle** (`Date commande`, dupliquée) + `Date facture`.
Aucune vraie date d'expédition / dernière livraison.
➡️ **Décision entreprise (2026-09-11)** : la **date de FACTURE** sert de date de référence pour le
retard — proxy accepté, pas la mesure idéale (la facturation précède le départ du camion).
`DelayCalculator` calcule désormais réellement (date facture − date commande) > seuil ; une
commande pas encore facturée reste **« donnée indisponible »** pour elle seule (jamais un faux 0).
Le setting `date_livraison_fiable` est supprimé (n'avait plus de sens en interrupteur global).

## Ville de livraison (règle 4.3) — **statu quo confirmé**

Seule `Ville commande` existe. Décision entreprise (2026-09-11) : accepté que la ville affichée
reste la ville de la COMMANDE, analyses géographiques approximatives en connaissance de cause.
L'étiquetage honnête déjà en place (« ville de commande », pas « ville de livraison ») suffit.

## Taux de satisfaction observé (local, lignes éligibles)

| Société | Taux | Lignes |
|---|---|---|
| RAN | 96,8 % | 63 653 / 65 729 |
| SMT | 68,4 % | 15 572 / 22 783 |

---

## Points à clarifier avec l'entreprise (cahier des charges §10)

**Tranchés le 2026-09-11** (décisions de l'encadreur, voir sections ci-dessus) :
1. ~~Poids d'un quintal (`QX`)~~ → pas de facteur unique, extraction par article comme SAC/T ;
   501008 « Tchich 25KG » résolu à 25 kg fiable.
2. ~~Vraie date d'expédition~~ → proxy accepté : date de FACTURE (à documenter comme tel dans l'aide).
4. ~~Vraie ville de livraison~~ → statu quo confirmé, étiquetage honnête suffisant.
6. ~~Code société `TF`~~ → même règle que RAN/SMT (`OrderScopeClassifier` déjà générique), pas
   encore de commandes TF dans les données actuelles — à vérifier au premier import qui en contient.
7. ~~Lignes `Déchet` à 1 000 000 kg~~ → valeur aberrante confirmée, exclue définitivement.
   **Décision jamais réellement implémentée jusqu'ici** (le seuil `outlier_qte_max_kg` était resté
   à 2 000 000 kg, sa valeur d'avant cette décision) — trouvé le 2026-09-13 en entraînant le
   nouveau modèle ML de prévision (article 911111 « Déchet des dérivés industriels », commande
   CMRAN012604867, faussait sa précision mesurée). Corrigé : seuil par défaut abaissé à
   1 000 000 kg (`OutlierDetector::fromSettings()` + `SettingSeeder`), et la comparaison passée en
   `>=` (pas `>`) pour exclure la valeur pile au plafond. Fichier réel ré-importé pour appliquer la
   correction aux données déjà en base.
11. ~~« Qté facturée US » anormalement énorme (KPI Facturé du tableau de bord)~~ → cette colonne
    est DÉJÀ en kg dans le fichier source (contrairement à Quantité commandée/livrée, dans l'unité
    de vente brute SAC/T/QX/KG) — vérifié ligne par ligne (ex. SAC 50 kg : commandée=2000,
    facturée US=100000=2000×50 ; QX hypothèse 100 kg : commandée=6, facturée US=600=6×100). Le
    code lui appliquait la même conversion (règle 2.8) qu'à commandée/livrée, la multipliant une
    seconde fois par le poids unitaire → Facturé affiché jusqu'à ×1000 la réalité. Corrigé dans
    `RowNormalizer` (2026-09-11) : facturée est utilisée directement, sans passer par
    `poids->convertir()`. Toutes les commandes déjà importées ont été recalculées (ré-import du
    même fichier source).

**Encore ouverts :**
3. **Source fiable `numéro client → catégorie`** — le point le plus important, toujours ouvert.
5. Les 3 articles à famille ambiguë (134235, 514127, 512122) et les 3 reclassifications à confirmer
   (134237, 418221, 419221) — toujours en attente de validation manuelle par l'entreprise.
8. Lignes `Déchet` à **100 000 kg** (commandes CMRAN012606815, CMRAN012604438, CMRAN012605812,
   article 911111) — pas tranchées, distinctes du cas 1 000 000 kg ci-dessus.
9. Seuil du contrôle statistique par article (`outlier_ratio_mediane_max`) — la valeur par défaut
   (25×) signale trop de commandes légitimes sur le jeu réel une fois réellement appliquée ; le
   mécanisme existe (`OutlierDetector::statisticalOutliers()`) mais n'est pas branché dans l'import.
10. **Les fichiers Sage réels sont-ils toujours l'état COMPLET d'une commande qu'ils mentionnent,
    ou parfois un extrait partiel (seulement les lignes modifiées sur la période) ?** Testé et
    prouvé (voir « ⚠️ Risque à vérifier » ci-dessus) : le dédoublonnage par remplacement **perd
    silencieusement** les lignes non re-listées si un fichier est un extrait partiel. Rien n'a été
    corrigé en attendant la réponse — la correction (fusion ligne par ligne au niveau produit)
    dépend entièrement de cette réponse.

*(Résolu : le poids des unités `SAC` et `T` est désormais extrait automatiquement de la
désignation, article par article — plus d'hypothèse globale.)*
