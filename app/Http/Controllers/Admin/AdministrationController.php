<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\Page;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\LoginAttempt;
use App\Models\PermissionTemplate;
use App\Models\Societe;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page Administration (section 4.8) — réservée aux administrateurs.
 * Comptes, droits, historique des connexions, journal d'audit, journal des imports.
 */
class AdministrationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Index', [
            'utilisateurs' => User::with('societes:id,code')
                ->orderBy('name')
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'is_admin' => $u->is_admin,
                    'verrouille' => $u->isLocked(),
                    'verrou_temporaire' => (bool) $u->locked_until?->isFuture(),
                    'must_change_password' => $u->must_change_password,
                    'societes' => $u->societes->pluck('code'),
                    'pages' => $u->is_admin ? Page::keys() : $u->pagePermissions()->pluck('page_key'),
                    'last_login_at' => $u->last_login_at?->toDateTimeString(),
                ]),
            'societes' => Societe::orderBy('nom')->get(['id', 'code', 'nom']),
            'modeles' => PermissionTemplate::orderBy('nom')->get(['code', 'nom', 'description', 'accorde_admin', 'pages']),
            'pagesCatalogue' => Page::all(),
            'audit' => AuditLog::with('acteur:id,name')->latest('created_at')->limit(50)->get(),
            'connexions' => LoginAttempt::with('user:id,name')->latest('created_at')->limit(50)->get(),
            'imports' => ImportBatch::latest()->limit(20)->get([
                'id', 'type', 'original_filename', 'status', 'rows_total', 'commandes_new',
                'commandes_replaced', 'anomalies_count', 'error_message', 'created_at',
            ]),
        ]);
    }
}
