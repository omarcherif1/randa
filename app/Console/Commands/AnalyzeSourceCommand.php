<?php

namespace App\Console\Commands;

use App\Domain\Classification\FamilyColumnDetector;
use App\Domain\Classification\FamilyConsolidation;
use App\Domain\Import\ColumnMap;
use App\Domain\Import\CsvReader;
use App\Domain\Import\ReferenceData;
use App\Domain\Import\RowNormalizer;
use App\Domain\Metrics\SatisfactionCalculator;
use App\Domain\Quality\OutlierDetector;
use App\Domain\Scope\OrderScope;
use App\Domain\Units\UnitWeightOrigin;
use Illuminate\Console\Command;

/**
 * Analyse (à blanc, sans écriture) un fichier unifié quotidien et produit un
 * rapport de contrôle : volumétrie, exclusions, valeurs aberrantes, familles
 * inconnues, unités non gérées, taux de satisfaction par société.
 *
 * Sert à vérifier les règles de la section 2 sur les vraies données AVANT
 * de brancher l'import persistant (Étape 2).
 *
 *   php artisan randa:analyze-source "F1787140089784_OMSTAGE.csv"
 */
class AnalyzeSourceCommand extends Command
{
    protected $signature = 'randa:analyze-source
        {path : Chemin du fichier CSV unifié}
        {--limit=0 : Limiter le nombre de lignes (0 = tout)}
        {--weights : Afficher la table article -> poids unitaire (règle 2.8, preuve de fiabilité)}';

    protected $description = 'Analyse à blanc un fichier source et contrôle les règles métier (section 2)';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $path = base_path($path);
        }
        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$this->argument('path')}");

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit') ?: null;

        $reader = new CsvReader($path);
        $header = $reader->header();
        $columns = new ColumnMap($header);

        $missing = $columns->missingFields();
        if ($missing !== []) {
            $this->warn('Champs logiques absents de l\'en-tête : '.implode(', ', $missing));
        }

        $ref = ReferenceData::load();

        // --- Règle 2.6 : détection de la colonne famille ---
        $familleColonne = $ref->familleColonneForcee();
        $detection = null;
        if ($familleColonne === null) {
            $sample = [];
            foreach ($reader->rows(2000) as $r) {
                $sample[] = $r;
            }
            $detection = (new FamilyColumnDetector)->detect($header, $sample, $ref->familleCodes);
            $familleColonne = $detection->chosenIndex;
        }
        $columns->withFamilyColumn($familleColonne);

        $this->line('');
        $this->info('=== Fichier ===');
        $this->line("  {$path}");
        $this->line('  Colonnes : '.count($header).'  |  Colonne famille retenue : '.($familleColonne ?? 'AUCUNE (familles => inconnues)'));
        if ($detection !== null) {
            foreach ($detection->candidates as $cand) {
                $this->line(sprintf(
                    '    - col[%d] "%s"  couverture %.0f%%  validité %.0f%%  codes distincts %d  %s',
                    $cand['index'], $cand['entete'],
                    $cand['couverture'] * 100, $cand['validite'] * 100,
                    $cand['codes_distincts'], $cand['retenable'] ? '[retenable]' : '',
                ));
            }
            if ($detection->hasAmbiguity()) {
                $this->warn('    Plusieurs colonnes candidates : à confirmer dans Paramètres (famille_colonne_index).');
            }
        }

        $unitResolver = $ref->articleUnitResolver();
        $familyResolver = $ref->familyResolver();
        $normalizer = new RowNormalizer(
            columns: $columns,
            ref: $ref,
            units: $unitResolver,
            familyResolver: $familyResolver,
            clientClassifier: $ref->clientClassifier(),
            outliers: OutlierDetector::fromSettings(),
        );

        // --- Accumulateurs ---
        $total = 0;
        $parScope = [OrderScope::Local->value => 0, OrderScope::Export->value => 0];
        $exclusions = [];
        $outliers = 0;
        $famillesInconnues = 0;
        $poidsOrigines = [];
        $lignesPoidsIndetermine = 0;
        $lignesPoidsConflit = 0;
        $tonnesLocalFiable = 0.0;
        $tonnesLocalAConfirmer = 0.0;
        $satisfactionRows = [];
        $commandes = [];

        foreach ($reader->rows($limit) as $row) {
            $total++;
            $line = $normalizer->normalize($row);
            $parScope[$line->scope->value]++;
            $commandes[$line->numeroCommande] = $line->scope->value;

            if ($line->isExcluded()) {
                $exclusions[$line->exclusionReason->value] = ($exclusions[$line->exclusionReason->value] ?? 0) + 1;
            }
            if ($line->isOutlier) {
                $outliers++;
            }
            if ($line->familleInconnue()) {
                $famillesInconnues++;
            }
            $poidsOrigines[$line->poidsOrigine] = ($poidsOrigines[$line->poidsOrigine] ?? 0) + 1;
            if ($line->poidsOrigine === UnitWeightOrigin::NON_DETERMINE) {
                $lignesPoidsIndetermine++;
            }
            if ($line->poidsEnConflit) {
                $lignesPoidsConflit++;
            }

            if ($line->scope === OrderScope::Local) {
                if ($line->compteVolume && $line->qteLivreeKg !== null) {
                    if ($line->volumeFiable) {
                        $tonnesLocalFiable += $line->qteLivreeKg / 1000;
                    } else {
                        $tonnesLocalAConfirmer += $line->qteLivreeKg / 1000;
                    }
                }
                $satisfactionRows[] = [
                    'societe_code' => $line->societeCode ?? '??',
                    'eligible' => $line->isEligible,
                    'satisfaite' => $line->isSatisfied,
                ];
            }
        }

        $satisfaction = (new SatisfactionCalculator)->compute($satisfactionRows);

        $nbCommandes = count($commandes);
        $nbExport = count(array_filter($commandes, fn ($s) => $s === OrderScope::Export->value));

        $this->line('');
        $this->info('=== Volumétrie ===');
        $this->line("  Lignes analysées ......... {$total}");
        $this->line("  Commandes distinctes ..... {$nbCommandes}  (dont export : {$nbExport})");
        $this->line('  Lignes locales ........... '.$parScope[OrderScope::Local->value]);
        $this->line('  Lignes export ............ '.$parScope[OrderScope::Export->value].'  (absentes du tableau de bord — règle 2.2)');

        $this->line('');
        $this->info('=== Règle 2.1 — exclusions (conservées en base, hors indicateurs) ===');
        if ($exclusions === []) {
            $this->line('  aucune');
        }
        foreach ($exclusions as $reason => $n) {
            $this->line(sprintf('  %-24s %d', $reason, $n));
        }

        $this->line('');
        $this->info('=== Règle 2.9 — valeurs aberrantes ===');
        $this->line("  Lignes signalées « anomalie à vérifier » : {$outliers}");

        $this->line('');
        $this->info('=== Règle 2.6 — familles (clé = code article complet) ===');
        $this->line("  Lignes à famille inconnue : {$famillesInconnues}");

        $consolidations = $familyResolver->consolidate();
        $confirmes = $reclass = $ambigus = [];
        foreach ($consolidations as $cons) {
            match ($cons->statut) {
                FamilyConsolidation::STATUT_RECLASSIFICATION => $reclass[] = $cons,
                FamilyConsolidation::STATUT_AMBIGU => $ambigus[] = $cons,
                default => $confirmes[] = $cons,
            };
        }
        $this->line(sprintf(
            '  Articles distincts : %d  —  famille stable : %d  ·  reclassés dans le temps : %d  ·  ambigus : %d',
            count($consolidations), count($confirmes), count($reclass), count($ambigus),
        ));
        foreach ($reclass as $c) {
            $suite = implode(' → ', array_map(fn ($p) => "{$p['code']} ({$p['from']}…{$p['to']})", $c->historique));
            $this->line("    reclassé  {$c->article} : {$suite}  ⇒ retient {$c->familleCode}");
        }
        foreach ($ambigus as $c) {
            $detail = implode(' / ', array_map(fn ($p) => "{$p['code']} x{$p['count']} ({$p['from']}…{$p['to']})", $c->historique));
            $this->warn("    AMBIGU  {$c->article} : {$detail}  ⇒ confirmation manuelle requise (aucun choix automatique)");
        }

        $this->line('');
        $this->info('=== Règle 2.8 — poids unitaire / conversion en kg ===');
        foreach ($poidsOrigines as $origine => $n) {
            $this->line(sprintf('  %-40s %d', UnitWeightOrigin::libelle($origine), $n));
        }
        if ($lignesPoidsIndetermine > 0) {
            $this->warn("  {$lignesPoidsIndetermine} lignes exclues des tonnages — unité à confirmer");
        }
        if ($lignesPoidsConflit > 0) {
            $this->warn("  {$lignesPoidsConflit} lignes en conflit de poids — ancienne valeur conservée, à trancher par un admin");
        }
        $nbPoidsAppris = count($unitResolver->pendingWeights());
        $this->line("  Articles dont le poids a été déterminé/mis à jour : {$nbPoidsAppris}");
        foreach ($unitResolver->quintalDivergences() as $d) {
            $this->warn(sprintf(
                '  QX article %s : hypothèse %d kg mais la désignation « %s » évoque %s kg — à trancher par un admin',
                $d['article'], $d['poids_hypothese_kg'], $d['designation'], $d['poids_dans_designation_kg'],
            ));
        }

        $this->line('');
        $this->info('=== Règle 2.3 — taux de satisfaction (local, par société, jamais moyenné) ===');
        foreach ($satisfaction->parSociete as $code => $s) {
            $taux = $s['taux'] === null ? 'n/a' : number_format($s['taux'] * 100, 1).' %';
            $this->line(sprintf('  %-4s  %s   (%d / %d lignes éligibles)', $code, $taux, $s['satisfaites'], $s['eligibles']));
        }

        $this->line('');
        $this->info('=== Tonnage (local) ===');
        $this->line('  Tonnes livrées — fiable ......... '.number_format($tonnesLocalFiable, 1, ',', ' ').' t');
        $this->line('  Tonnes livrées — à confirmer (QX) '.number_format($tonnesLocalAConfirmer, 1, ',', ' ').' t');
        $this->line('  Total affichable ............... '.number_format($tonnesLocalFiable + $tonnesLocalAConfirmer, 1, ',', ' ').' t');

        if ($this->option('weights')) {
            $this->line('');
            $this->info('=== Règle 2.8 — table article -> poids unitaire (preuve de fiabilité) ===');
            $rows = [];
            foreach ($unitResolver->pendingWeights() as $w) {
                $rows[] = [
                    $w['article'],
                    $w['unite'],
                    $w['poids'] === null ? '— (exclu des tonnages)' : rtrim(rtrim(number_format($w['poids'], 4, '.', ''), '0'), '.').' kg',
                    UnitWeightOrigin::libelle($w['origine']),
                    mb_strimwidth((string) $w['designation'], 0, 40, '…'),
                ];
            }
            usort($rows, fn ($a, $b) => [$a[1], $a[0]] <=> [$b[1], $b[0]]);
            $this->table(['Article', 'Unité', 'Poids retenu', 'Origine', 'Désignation'], $rows);

            foreach ($unitResolver->conflicts() as $c) {
                $this->warn(sprintf(
                    '  CONFLIT article %s : validé %s kg / extrait %s kg — ancienne valeur conservée',
                    $c['article'], $c['poids_valide_kg'], $c['poids_extrait_kg'],
                ));
            }
        }

        $this->line('');

        return self::SUCCESS;
    }
}
