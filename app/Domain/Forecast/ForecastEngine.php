<?php

namespace App\Domain\Forecast;

/**
 * Règle 4.5 — moteur de prévision de la demande.
 *
 * Le modèle apprend TOUJOURS sur la quantité COMMANDÉE (le vrai besoin exprimé),
 * jamais sur la quantité livrée (structurellement <= commandée → biais vers le bas).
 *
 * Paliers de fiabilité selon l'historique disponible (en mois) :
 *   - 1 à 2 mois : simple moyenne                              → fiabilité « faible »
 *   - 3 à 5 mois : tendance (hausse/baisse/stable) + moyenne   → fiabilité « moyenne »
 *   - 6 mois et + : tendance + ajustement saisonnier            → fiabilité « haute »
 *     (le même mois de l'année précédente pèse pour MOITIÉ dans la projection)
 *   - moins d'1 mois : pas de prévision                        → « insuffisante »
 *
 * Calcul pur et déterministe (aucune dépendance base / réseau) — testable seul
 * et réutilisé tel quel par le backtest.
 */
final class ForecastEngine
{
    /** Seuil de variation mensuelle relative en deçà duquel la tendance est « stable ». */
    private const SEUIL_STABLE = 0.03;

    /**
     * @param  array<string, float>  $serieMensuelle  AAAA-MM => quantité commandée (kg)
     */
    public function prevoir(array $serieMensuelle): ForecastResult
    {
        $serie = $this->completerLesTrous($serieMensuelle);
        $n = count($serie);

        if ($n < 1) {
            return new ForecastResult(null, null, 0, ForecastResult::FIABILITE_INSUFFISANTE,
                ForecastResult::TENDANCE_INDETERMINEE, 0, note: 'Moins d\'un mois d\'historique.');
        }

        $dernierMois = array_key_last($serie);

        return $this->projeterMois($serie, $this->moisSuivant($dernierMois), $n);
    }

    /**
     * Prévision MULTI-MOIS, du mois suivant le dernier mois observé jusqu'à
     * `$moisFin` (AAAA-MM) inclus — ex. décembre de l'année en cours, pour
     * couvrir le reste de l'année à chaque page vue. Le palier de fiabilité
     * (longueur d'historique) est fixe pour toute la projection ; seule la
     * valeur avance mois par mois : la tendance est évaluée plus loin sur la
     * droite de régression, et l'ajustement saisonnier (palier 3) utilise,
     * pour CHAQUE mois cible, son propre « même mois l'an dernier ».
     *
     * @param  array<string, float>  $serieMensuelle
     * @return list<ForecastResult>
     */
    public function prevoirJusqua(array $serieMensuelle, string $moisFin): array
    {
        $serie = $this->completerLesTrous($serieMensuelle);
        $n = count($serie);

        if ($n < 1) {
            return [];
        }

        $dernierMois = array_key_last($serie);
        if ($dernierMois >= $moisFin) {
            return []; // historique déjà à/après le mois cible : rien à prévoir
        }

        $resultats = [];
        $cible = $this->moisSuivant($dernierMois);
        $x = $n;
        while ($cible <= $moisFin) {
            $resultats[] = $this->projeterMois($serie, $cible, $x);
            $cible = $this->moisSuivant($cible);
            $x++;
        }

        return $resultats;
    }

    /**
     * Calcule la prévision d'UN mois cible — un seul endroit pour les 3
     * paliers, partagé par `prevoir()` (mois suivant) et `prevoirJusqua()`
     * (plusieurs mois). `$x` est la position du mois cible sur la droite de
     * régression (x = 0..n-1 pour l'historique, donc x = n pour le mois
     * suivant, n+1 pour celui d'après, etc.).
     */
    private function projeterMois(array $serie, string $cible, int $x): ForecastResult
    {
        $n = count($serie);
        $valeurs = array_values($serie);
        $moyenne = array_sum($valeurs) / $n;

        // Palier 1 — 1 à 2 mois : simple moyenne.
        if ($n <= 2) {
            return new ForecastResult(
                $cible, round($moyenne, 3), 1,
                ForecastResult::FIABILITE_FAIBLE, ForecastResult::TENDANCE_INDETERMINEE,
                $n, round($moyenne, 3),
                note: 'Historique court : simple moyenne.',
            );
        }

        [$pente, $ordonnee] = $this->regressionLineaire($valeurs);
        $projection = max(0.0, $ordonnee + $pente * $x);
        $tendance = $this->tendance($pente, $moyenne);

        // Palier 2 — 3 à 5 mois : tendance + moyenne.
        if ($n <= 5) {
            return new ForecastResult(
                $cible, round($projection, 3), 2,
                ForecastResult::FIABILITE_MOYENNE, $tendance,
                $n, round($moyenne, 3),
                note: 'Tendance ajustée sur '.$n.' mois.',
            );
        }

        // Palier 3 — 6 mois et + : tendance + ajustement saisonnier — le même
        // mois cible, un an avant, dans l'historique (s'il y est).
        $memeMoisAnneePrecedente = $serie[$this->moisSuivant($cible, -12)] ?? null;

        if ($memeMoisAnneePrecedente !== null) {
            $prevision = 0.5 * $projection + 0.5 * $memeMoisAnneePrecedente;

            return new ForecastResult(
                $cible, round(max(0.0, $prevision), 3), 3,
                ForecastResult::FIABILITE_HAUTE, $tendance,
                $n, round($moyenne, 3), ajustementSaisonnier: true,
                note: 'Tendance + saisonnalité (même mois l\'an dernier : '.round($memeMoisAnneePrecedente).' kg).',
            );
        }

        return new ForecastResult(
            $cible, round($projection, 3), 3,
            ForecastResult::FIABILITE_HAUTE, $tendance,
            $n, round($moyenne, 3), ajustementSaisonnier: false,
            note: 'Historique >= 6 mois mais pas de « même mois l\'an dernier » : projection de tendance seule.',
        );
    }

    /**
     * Complète les mois manquants entre le premier et le dernier mois observés
     * avec une demande nulle (un mois sans commande = 0, pas une absence de donnée).
     *
     * @param  array<string, float>  $serie
     * @return array<string, float>
     */
    private function completerLesTrous(array $serie): array
    {
        $serie = array_filter($serie, fn ($m) => preg_match('/^\d{4}-\d{2}$/', (string) $m), ARRAY_FILTER_USE_KEY);
        if ($serie === []) {
            return [];
        }

        ksort($serie);
        $mois = array_keys($serie);
        $courant = reset($mois);
        $fin = end($mois);

        $complet = [];
        while (true) {
            $complet[$courant] = (float) ($serie[$courant] ?? 0);
            if ($courant === $fin) {
                break;
            }
            $courant = $this->moisSuivant($courant);
        }

        return $complet;
    }

    /**
     * @param  list<float>  $valeurs
     * @return array{0: float, 1: float} [pente, ordonnée à l'origine] sur x = 0..n-1
     */
    private function regressionLineaire(array $valeurs): array
    {
        $n = count($valeurs);
        $sx = $sy = $sxy = $sxx = 0.0;
        foreach ($valeurs as $i => $y) {
            $sx += $i;
            $sy += $y;
            $sxy += $i * $y;
            $sxx += $i * $i;
        }
        $denom = $n * $sxx - $sx * $sx;
        if (abs($denom) < 1e-9) {
            return [0.0, $sy / $n];
        }
        $pente = ($n * $sxy - $sx * $sy) / $denom;
        $ordonnee = ($sy - $pente * $sx) / $n;

        return [$pente, $ordonnee];
    }

    private function tendance(float $pente, float $moyenne): string
    {
        $relatif = $moyenne > 0 ? $pente / $moyenne : $pente;

        if (abs($relatif) < self::SEUIL_STABLE) {
            return ForecastResult::TENDANCE_STABLE;
        }

        return $relatif > 0 ? ForecastResult::TENDANCE_HAUSSE : ForecastResult::TENDANCE_BAISSE;
    }

    /** Arithmétique de mois sur des chaînes « AAAA-MM ». */
    private function moisSuivant(string $mois, int $pas = 1): string
    {
        [$a, $m] = array_map('intval', explode('-', $mois));
        $total = ($a * 12 + ($m - 1)) + $pas;

        return sprintf('%04d-%02d', intdiv($total, 12), ($total % 12) + 1);
    }
}
