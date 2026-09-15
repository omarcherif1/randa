<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Simule le service ML de prévision (règle 4.5, processus Python séparé)
     * injoignable — pour que le test reste déterministe et rapide au lieu de
     * faire un vrai appel réseau. À appeler explicitement dans `setUp()` de
     * tout test qui touche `ForecastMetrics`/la route `previsions` sans
     * vouloir exercer le chemin ML actif (voir MlForecastIntegrationTest
     * pour ça — il pose son propre `Http::fake()` par test, PAS celui-ci :
     * Laravel résout les fakes dans l'ordre d'enregistrement, le premier
     * enregistré qui matche gagne, donc ce fake ne doit être posé QUE là où
     * rien d'autre n'a besoin de le remplacer).
     */
    protected function fakeMlServiceDown(): void
    {
        Http::fake(['127.0.0.1:8001/*' => Http::response('', 500)]);
    }
}
