<?php

namespace App\Domain\Forecast;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Client HTTP vers le service de prévision ML (voir ml-service/README.md).
 *
 * Découplage total et volontaire : ce client ne fait QUE parler HTTP au
 * service Python, il ne sait rien de son fonctionnement interne. Toute
 * panne (service éteint, pas encore entraîné, timeout) est absorbée ici et
 * remontée comme un simple `null`/`false` — jamais une exception qui
 * casserait la page Prévisions. L'appelant ({@see ForecastMetrics}) décide
 * du repli vers le calcul PHP.
 */
final class MlForecastClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: rtrim((string) config('services.ml_forecast.url'), '/'),
            timeout: (int) config('services.ml_forecast.timeout', 30),
        );
    }

    /** @return array{entraine: bool, entraine_le?: string, n_echantillons?: int, n_produits?: int, mae_kg?: float|null, wape?: float|null, r2?: float|null}|null null = service injoignable */
    public function statut(): ?array
    {
        try {
            $reponse = Http::timeout(5)->get("{$this->baseUrl}/status");

            return $reponse->successful() ? $reponse->json() : null;
        } catch (Throwable $e) {
            Log::info('Service ML de prévision injoignable (statut)', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  list<array{article:string,designation:string,famille:?string,societe:?string,mois:string,qte_commandee_kg:float}>  $lignes
     * @return array{entraine_le:string,n_echantillons:int,n_produits:int,mae_kg:?float,wape:?float,r2:?float}
     *
     * @throws MlForecastException
     */
    public function entrainer(array $lignes): array
    {
        try {
            $reponse = Http::timeout($this->timeout)->post("{$this->baseUrl}/train", ['lignes' => $lignes]);
        } catch (Throwable $e) {
            throw new MlForecastException("Service ML injoignable : {$e->getMessage()}", previous: $e);
        }

        if (! $reponse->successful()) {
            throw new MlForecastException(
                "Échec de l'entraînement ({$reponse->status()}) : ".($reponse->json('detail') ?? $reponse->body()),
            );
        }

        return $reponse->json();
    }

    /**
     * @param  list<array{article:string,designation:string,famille:?string,societe:?string,mois:string,qte_commandee_kg:float}>  $lignes
     * @return array{produits: list<array{article:string,designation:string,previsions:list<array{mois:string,quantite_prevue_kg:float}>}>, modele_entraine_le:string, n_echantillons_entrainement:int}|null null = indisponible/pas entraîné
     */
    public function predire(array $lignes, string $moisFin): ?array
    {
        try {
            $reponse = Http::timeout($this->timeout)->post("{$this->baseUrl}/predict", [
                'lignes' => $lignes,
                'mois_fin' => $moisFin,
            ]);
        } catch (Throwable $e) {
            Log::info('Service ML de prévision injoignable (predict)', ['message' => $e->getMessage()]);

            return null;
        }

        if (! $reponse->successful()) {
            Log::warning('Prédiction ML échouée', ['status' => $reponse->status(), 'body' => $reponse->body()]);

            return null;
        }

        return $reponse->json();
    }
}
