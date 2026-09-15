<?php

namespace App\Domain\Classification;

/**
 * Règle métier 2.6 — attribue une famille officielle à une ligne.
 *
 * CLÉ DE CORRESPONDANCE : le code article dans son INTÉGRALITÉ, jamais un
 * préfixe (1, 2 ou 3 chiffres). Les tests sur données réelles montrent que des
 * articles au même préfixe appartiennent parfois à des familles différentes —
 * un préfixe n'est jamais une source de vérité.
 *
 * Résolution ligne par ligne (`resolve`) :
 *   1. code famille présent sur la ligne ET valide        => source 'file'
 *   2. sinon, famille déjà MÉMORISÉE (import précédent /
 *      correction admin) pour ce code article complet     => source 'backfilled'
 *   3. sinon                                              => 'unknown' (famille inconnue,
 *                                                            jamais devinée par préfixe)
 *
 * En fin d'import, `consolidate()` tranche le cas des articles vus avec
 * plusieurs familles (cf. FamilyConsolidation) :
 *   - familles disjointes dans le temps  => reclassification, garder la plus récente ;
 *   - familles entremêlées sans logique  => ambigu, confirmation manuelle requise.
 */
final class FamilyResolver
{
    /** @var array<string, bool> */
    private array $validCodes;

    /** @var array<string, string> code article complet => famille mémorisée (imports précédents / admin) */
    private array $memoire;

    /** @var array<string, list<array{code:string, date:?string}>> observations de l'import courant */
    private array $observations = [];

    /** @var array<string, string> dernière désignation vue par article */
    private array $designations = [];

    /**
     * @param  list<string>  $validCodes  les 32 codes officiels
     * @param  array<string, string>  $memoire  code article complet => famille déjà mémorisée
     */
    public function __construct(array $validCodes, array $memoire = [])
    {
        $this->validCodes = array_fill_keys(array_map('strval', $validCodes), true);
        $this->memoire = $memoire;
    }

    public function resolve(?string $codeFichier, ?string $article, ?string $date = null, ?string $designation = null): FamilyResolution
    {
        $code = trim((string) $codeFichier);
        $art = trim((string) $article);

        if ($art !== '' && $designation !== null && $designation !== '') {
            $this->designations[$art] = $designation;
        }

        // 1. Code valide sur la ligne : on l'utilise et on l'enregistre comme observation.
        if ($code !== '' && isset($this->validCodes[$code])) {
            $this->observe($art, $code, $date);

            return new FamilyResolution($code, FamilyResolution::SOURCE_FILE);
        }

        // 2. Famille déjà mémorisée pour ce code article COMPLET.
        if ($art !== '' && isset($this->memoire[$art])) {
            return new FamilyResolution($this->memoire[$art], FamilyResolution::SOURCE_BACKFILLED);
        }

        // 3. Inconnue — jamais de déduction par préfixe.
        return new FamilyResolution(null, FamilyResolution::SOURCE_UNKNOWN);
    }

    public function observe(string $article, string $code, ?string $date): void
    {
        $article = trim($article);
        if ($article === '' || $code === '') {
            return;
        }

        $this->observations[$article][] = ['code' => $code, 'date' => $date];
    }

    /**
     * Décisions par article pour les articles observés pendant cet import.
     *
     * @return array<string, FamilyConsolidation>
     */
    public function consolidate(): array
    {
        $out = [];
        foreach ($this->observations as $article => $obs) {
            $out[$article] = $this->consolidateArticle($article, $obs);
        }

        return $out;
    }

    public function designationOf(string $article): ?string
    {
        return $this->designations[trim($article)] ?? null;
    }

    /** @return array<string, string> */
    public function memoire(): array
    {
        return $this->memoire;
    }

    /**
     * @param  list<array{code:string, date:?string}>  $obs
     */
    private function consolidateArticle(string $article, array $obs): FamilyConsolidation
    {
        /** @var array<string, array{code:string, from:?string, to:?string, count:int}> $parCode */
        $parCode = [];
        foreach ($obs as $o) {
            $c = $o['code'];
            $d = $o['date'] ?: null;
            if (! isset($parCode[$c])) {
                $parCode[$c] = ['code' => $c, 'from' => $d, 'to' => $d, 'count' => 0];
            }
            $parCode[$c]['count']++;
            if ($d !== null) {
                if ($parCode[$c]['from'] === null || $d < $parCode[$c]['from']) {
                    $parCode[$c]['from'] = $d;
                }
                if ($parCode[$c]['to'] === null || $d > $parCode[$c]['to']) {
                    $parCode[$c]['to'] = $d;
                }
            }
        }

        // Une seule famille : cas confirmé.
        if (count($parCode) === 1) {
            $seul = array_values($parCode)[0];

            return new FamilyConsolidation($article, $seul['code'], FamilyConsolidation::STATUT_CONFIRME, [$seul]);
        }

        // Plusieurs familles : trier par date de début.
        $periodes = array_values($parCode);
        usort($periodes, fn ($a, $b) => ($a['from'] ?? '9999') <=> ($b['from'] ?? '9999'));

        if ($this->periodesDisjointes($periodes)) {
            // Reclassification dans le temps : garder la plus récente (dernière plage).
            $recente = $periodes[count($periodes) - 1]['code'];

            return new FamilyConsolidation($article, $recente, FamilyConsolidation::STATUT_RECLASSIFICATION, $periodes);
        }

        // Incohérent : aucun choix automatique. On conserve la valeur déjà mémorisée si elle existe.
        return new FamilyConsolidation(
            $article,
            $this->memoire[$article] ?? null,
            FamilyConsolidation::STATUT_AMBIGU,
            $periodes,
        );
    }

    /**
     * Les plages de dates des familles successives ne se chevauchent pas :
     * chaque famille se termine avant que la suivante ne commence.
     *
     * @param  list<array{code:string, from:?string, to:?string, count:int}>  $periodesTriees
     */
    private function periodesDisjointes(array $periodesTriees): bool
    {
        for ($i = 1; $i < count($periodesTriees); $i++) {
            $precedente = $periodesTriees[$i - 1];
            $courante = $periodesTriees[$i];

            // dates manquantes => on ne peut pas affirmer une corrélation temporelle
            if ($precedente['to'] === null || $courante['from'] === null) {
                return false;
            }

            if ($precedente['to'] >= $courante['from']) {
                return false;
            }
        }

        return true;
    }
}
