<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Section 6 — page « Notifications » : la liste complète des alertes actives de
 * la personne. Les données arrivent par le partage Inertia global
 * (`notifications`, cf. HandleInertiaRequests) — déjà filtrées selon ses droits.
 */
class NotificationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Notifications/Index');
    }
}
