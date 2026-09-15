<?php

namespace App\Domain\Forecast;

use Exception;

/** Échec explicite d'un entraînement demandé — contrairement à `predire()`,
 * qui absorbe silencieusement les pannes pour ne jamais casser la page,
 * `entrainer()` est une action volontaire de l'admin : l'échec doit lui
 * être montré clairement, pas masqué par un repli silencieux. */
final class MlForecastException extends Exception {}
