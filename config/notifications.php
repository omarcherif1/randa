<?php

use App\Domain\Notification\Rules\AnomaliesAVerifierRule;
use App\Domain\Notification\Rules\EchecImportRule;
use App\Domain\Notification\Rules\RetardsCommandesRule;
use App\Domain\Notification\Rules\RupturesStockRule;

return [

    /*
    |--------------------------------------------------------------------------
    | Règles d'alerte (section 6)
    |--------------------------------------------------------------------------
    |
    | Chaque entrée est une classe implémentant App\Domain\Notification\AlertRule.
    | Ajouter une alerte = créer la classe + l'ajouter ici. La structure du centre
    | de notifications ne change pas (exigence 3 — conçu pour être étendu :
    | échec d'import, nouvelle anomalie, chute de fiabilité d'une prévision, …).
    |
    */

    'regles' => [
        RetardsCommandesRule::class,
        RupturesStockRule::class,
        EchecImportRule::class,
        AnomaliesAVerifierRule::class,
    ],

    // Section 6 : « jusqu'à 5 » éléments pour les règles qui listent (retards, ruptures).
    'max_par_regle' => 5,

];
