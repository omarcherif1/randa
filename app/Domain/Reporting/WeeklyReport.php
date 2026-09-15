<?php

namespace App\Domain\Reporting;

use App\Domain\Dashboard\DashboardFilter;
use App\Domain\Dashboard\DashboardMetrics;
use App\Models\Anomalie;
use App\Models\Commande;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Exigence 7 — rapport hebdomadaire imprimable/exportable pour la direction.
 *
 * Ne recalcule rien : compose les agrégats déjà mis en cache par
 * {@see DashboardMetrics} sur deux fenêtres de 7 jours (semaine écoulée et
 * semaine précédente) pour montrer l'évolution. Respecte le rattachement société.
 */
final class WeeklyReport
{
    public function __construct(private readonly DashboardMetrics $dashboard = new DashboardMetrics) {}

    /** @return array<string, mixed> */
    public function pour(?User $user, ?CarbonImmutable $fin = null): array
    {
        // Ancré sur la dernière date de commande RÉELLEMENT présente dans les
        // données locales, pas sur l'horloge système — sinon, dès que le
        // dernier import date de plus de 7 jours (pause dans les imports,
        // environnement de démo…), la fenêtre "semaine écoulée" ne contient
        // aucune commande et tout le rapport affiche 0, alors que des données
        // existent bel et bien, juste pas dans les 7 derniers jours calendaires.
        $fin = ($fin ?? $this->dernierJourDeDonnees())->startOfDay();
        $debut = $fin->subDays(6);
        $finPrec = $debut->subDay();
        $debutPrec = $finPrec->subDays(6);

        $semaine = $this->dashboard->forFilter(new DashboardFilter(user: $user, from: $debut, to: $fin));
        $precedente = $this->dashboard->forFilter(new DashboardFilter(user: $user, from: $debutPrec, to: $finPrec));

        $tauxPrec = [];
        foreach ($precedente['satisfaction']['par_societe'] as $s) {
            $tauxPrec[$s['code']] = $s['taux'];
        }

        return [
            'periode' => ['du' => $debut->toDateString(), 'au' => $fin->toDateString()],
            'periode_precedente' => ['du' => $debutPrec->toDateString(), 'au' => $finPrec->toDateString()],
            'kpi' => $semaine['kpi'],
            'kpi_precedent' => $precedente['kpi'],
            'satisfaction' => array_map(fn ($s) => [
                'code' => $s['code'],
                'nom' => $s['nom'],
                'couleur' => $s['couleur'],
                'taux' => $s['taux'],
                'taux_precedent' => $tauxPrec[$s['code']] ?? null,
                'satisfaites' => $s['satisfaites'],
                'commandes' => $s['commandes'],
            ], $semaine['satisfaction']['par_societe']),
            'ruptures' => array_slice($semaine['ruptures']['liste'] ?? [], 0, 5),
            'top_clients' => array_slice($semaine['classements']['top_clients'] ?? [], 0, 5),
            'causes' => $semaine['causes_non_satisfaction']['causes'] ?? [],
            'anomalies_a_verifier' => Anomalie::where('statut', Anomalie::STATUT_A_VERIFIER)->count(),
            'fraicheur' => $semaine['fraicheur'] ?? MetricsCache::fraicheur(),
            'genere_le' => CarbonImmutable::now(),
            'genere_par' => $user?->name,
        ];
    }

    private function dernierJourDeDonnees(): CarbonImmutable
    {
        $max = Commande::local()->max('date_commande');

        return $max !== null ? CarbonImmutable::parse($max) : CarbonImmutable::now();
    }
}
