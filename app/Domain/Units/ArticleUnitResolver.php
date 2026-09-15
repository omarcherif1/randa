<?php

namespace App\Domain\Units;

/**
 * Règle 2.8 — SEUL endroit qui détermine le poids en kg d'une unité de vente,
 * article par article (jamais un facteur global par code d'unité — c'est
 * l'erreur de la V1, et *aussi* l'erreur qu'aurait été un facteur fixe pour QX).
 *
 * Ordre de résolution, identique pour TOUTE unité non-KG (SAC, T, QX, ou toute
 * unité future) :
 *   1. unité = KG                    -> aucune conversion (poids 1, cas neutre) ;
 *   2. valeur déjà validée connue    -> on la garde, on surveille les contradictions ;
 *   3. poids extrait de la désignation -> utilisé, fiable, mémorisé par article
 *      (ex. QX "Tchich 25KG" -> 25 kg, extrait, fiable — confirmé par l'entreprise
 *      : il n'existe PAS de facteur unique pour QX, un quintal n'est pas toujours
 *      100 kg selon le produit) ;
 *   4. rien d'extractible ET unité = QX -> filet de sécurité : hypothèse
 *      1 quintal = 100 kg, marquée "non confirmée" (ex. "Farine PS en sac perdus") ;
 *   5. rien d'extractible, toute autre unité -> non déterminé : la ligne est
 *      exclue des tonnages, jamais de facteur inventé.
 *
 * Contradiction : si un import extrait pour un article un poids différent d'une
 * valeur déjà validée, on NE tranche PAS — on garde l'ancienne et on signale.
 *
 * Utilisation : amorcer avec la table déjà connue (`fromMap`), résoudre chaque
 * ligne, puis persister `pendingWeights()` et créer des anomalies pour
 * `conflicts()` (fait par l'import — Étape 2).
 */
final class ArticleUnitResolver
{
    /** @var array<string, array{poids: float|null, origine: string, confirme: bool, designation_ref: string|null}> */
    private array $map;

    /** @var array<string, array{article:string, designation:string, unite:string, poids:float|null, origine:string}> */
    private array $pending = [];

    /** @var array<string, array<string,mixed>> contradiction avec une valeur validée */
    private array $conflicts = [];

    /** @var array<string, array<string,mixed>> QX dont la désignation évoque un autre poids que 100 kg */
    private array $quintalDivergences = [];

    public function __construct(
        array $map,
        private readonly float $quintalHypotheseKg = 100.0,
        private readonly DesignationWeightExtractor $extractor = new DesignationWeightExtractor,
    ) {
        $this->map = $map;
    }

    public function resolve(string $article, ?string $designation, string $uniteVente): UnitWeightResolution
    {
        $article = trim($article);
        $unite = strtoupper(trim($uniteVente));

        return $unite === 'KG'
            ? $this->resolveKg($article, (string) $designation)
            : $this->resolveNonKg($article, (string) $designation, $unite);
    }

    private function resolveKg(string $article, string $designation): UnitWeightResolution
    {
        $this->remember($article, $designation, 'KG', 1.0, UnitWeightOrigin::KG_NEUTRE);

        return new UnitWeightResolution(1.0, UnitWeightOrigin::KG_NEUTRE);
    }

    /** Règle générale pour toute unité non-KG (SAC, T, QX, et toute unité future). */
    private function resolveNonKg(string $article, string $designation, string $unite): UnitWeightResolution
    {
        $connu = $this->map[$article] ?? null;

        // Valeur déjà validée : on la garde, mais on surveille les contradictions.
        if ($connu !== null && $connu['confirme'] && $connu['poids'] !== null) {
            $extrait = $this->extractor->extract($designation);

            if ($extrait !== null && abs($extrait - $connu['poids']) > 0.0001) {
                $details = [
                    'article' => $article,
                    'poids_valide_kg' => $connu['poids'],
                    'poids_extrait_kg' => $extrait,
                    'designation_validee' => $connu['designation_ref'],
                    'designation_nouvelle' => $designation,
                ];
                $this->conflicts[$article] = $details;

                return new UnitWeightResolution(
                    $connu['poids'],
                    $connu['origine'],
                    enConflit: true,
                    conflitDetails: $details,
                );
            }

            return new UnitWeightResolution($connu['poids'], $connu['origine']);
        }

        // Pas encore de valeur fiable : on tente TOUJOURS l'extraction d'abord —
        // y compris pour QX, qui n'a pas de facteur unique (règle 2.8 confirmée).
        $extrait = $this->extractor->extract($designation);

        if ($extrait !== null) {
            $this->remember($article, $designation, $unite, $extrait, UnitWeightOrigin::EXTRAIT_DESIGNATION);

            return new UnitWeightResolution($extrait, UnitWeightOrigin::EXTRAIT_DESIGNATION);
        }

        // Rien d'extractible : filet de sécurité réservé au quintal (règle 2.8).
        if ($unite === 'QX') {
            return $this->resolveQuintalSansIndication($article, $designation);
        }

        // Poids introuvable (ou plusieurs poids contradictoires dans le texte).
        $this->remember($article, $designation, $unite, null, UnitWeightOrigin::NON_DETERMINE);

        return new UnitWeightResolution(null, UnitWeightOrigin::NON_DETERMINE);
    }

    /** QX sans aucun poids extractible dans la désignation : hypothèse 100 kg, non confirmée. */
    private function resolveQuintalSansIndication(string $article, string $designation): UnitWeightResolution
    {
        $this->remember($article, $designation, 'QX', $this->quintalHypotheseKg, UnitWeightOrigin::HYPOTHESE_QUINTAL);

        // Filet de cohérence : si un poids EST présent mais l'extracteur l'a jugé
        // ambigu (plusieurs valeurs), on n'invente rien mais on le signale quand
        // même s'il diverge nettement de l'hypothèse, pour qu'un admin tranche.
        $indices = $this->extractor->allWeights($designation);
        if ($indices !== [] && ! in_array($this->quintalHypotheseKg, $indices, true)) {
            $this->quintalDivergences[$article] = [
                'article' => $article,
                'poids_hypothese_kg' => $this->quintalHypotheseKg,
                'poids_dans_designation_kg' => $indices[0],
                'designation' => $designation,
            ];
        }

        return new UnitWeightResolution($this->quintalHypotheseKg, UnitWeightOrigin::HYPOTHESE_QUINTAL);
    }

    private function remember(string $article, string $designation, string $unite, ?float $poids, string $origine): void
    {
        if ($article === '') {
            return;
        }

        $this->map[$article] = [
            'poids' => $poids,
            'origine' => $origine,
            'confirme' => UnitWeightOrigin::estFiable($origine),
            'designation_ref' => $designation !== '' ? $designation : ($this->map[$article]['designation_ref'] ?? null),
        ];

        $this->pending[$article] = [
            'article' => $article,
            'designation' => $designation,
            'unite' => $unite,
            'poids' => $poids,
            'origine' => $origine,
        ];
    }

    /** @return array<string, array{article:string, designation:string, unite:string, poids:float|null, origine:string}> */
    public function pendingWeights(): array
    {
        return $this->pending;
    }

    /** @return list<array<string,mixed>> */
    public function conflicts(): array
    {
        return array_values($this->conflicts);
    }

    /**
     * QX dont la désignation évoque un poids différent de l'hypothèse quintal,
     * sans qu'un poids unique n'ait pu être extrait automatiquement (sinon il
     * aurait été utilisé directement — cf. resolveNonKg). L'hypothèse est quand
     * même appliquée (règle 2.8), mais l'admin doit trancher.
     *
     * @return list<array<string,mixed>>
     */
    public function quintalDivergences(): array
    {
        return array_values($this->quintalDivergences);
    }
}
