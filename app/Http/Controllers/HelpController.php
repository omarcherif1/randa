<?php

namespace App\Http\Controllers;

use App\Domain\Reporting\IndicatorGlossary;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Exigence 7 (pédagogie intégrée) — page « Comment lire ces chiffres ».
 * Le contenu vient du partage Inertia global `glossaire`
 * ({@see IndicatorGlossary}), tenu à jour avec la section 2.
 */
class HelpController extends Controller
{
    public function indicateurs(): Response
    {
        return Inertia::render('Aide/Indicateurs');
    }
}
