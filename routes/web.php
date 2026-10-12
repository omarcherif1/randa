<?php

use App\Http\Controllers\Admin\AdministrationController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AnomalyController;
use App\Http\Controllers\Admin\DataCorrectionController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardExportController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\LivraisonController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'));

// Tableau de bord principal (section 4.2). Accès page par page (règle 3.1).
Route::middleware(['auth', 'page:dashboard'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/local', [DashboardController::class, 'local'])->name('dashboard.local');
    Route::get('/dashboard/export.csv', [DashboardExportController::class, 'commandesCsv'])->name('dashboard.export.csv');
    Route::get('/dashboard/rapport', [DashboardExportController::class, 'rapport'])->name('dashboard.rapport');
    Route::get('/dashboard/rapport-hebdomadaire', [DashboardExportController::class, 'rapportHebdomadaire'])->name('dashboard.rapport.hebdo');
});

// Exigence 7 — « Comment lire ces chiffres » : accessible à tout compte connecté.
Route::middleware('auth')->get('/aide/comment-lire-ces-chiffres', [HelpController::class, 'indicateurs'])->name('aide.indicateurs');

// Pages dédiées — Étape 5 (héritent des règles déjà validées, accès page par page).
Route::middleware(['auth', 'page:livraisons'])->get('/livraisons', [LivraisonController::class, 'index'])->name('livraisons');
Route::middleware(['auth', 'page:stock'])->get('/stock', [StockController::class, 'index'])->name('stock');
Route::middleware(['auth', 'page:export'])->get('/export', [ExportController::class, 'index'])->name('export');
Route::middleware(['auth', 'page:previsions'])->group(function () {
    Route::get('/previsions', [ForecastController::class, 'index'])->name('previsions');
    // Réentraînement du modèle ML (règle 4.5) — bouton visible uniquement aux
    // admins côté page, mais l'accès admin est vérifié ici aussi (jamais une
    // vérification uniquement côté client sur une action qui modifie l'état).
    Route::post('/previsions/reentrainer-modele', [ForecastController::class, 'reentrainerModele'])->name('previsions.reentrainer');
});

// Centre de notifications (section 6) — visible par tout compte ; le contenu est
// filtré alerte par alerte selon les droits de la personne.
Route::middleware('auth')->get('/notifications', [NotificationController::class, 'index'])->name('notifications');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Administration + Paramètres — réservées aux administrateurs (règle 3.1).
Route::middleware(['auth', 'page:administration'])->prefix('administration')->name('admin.')->group(function () {
    Route::get('/', [AdministrationController::class, 'index'])->name('index');

    // Import web du fichier unifié quotidien / fichier stock (section 4.7)
    Route::get('import', [ImportController::class, 'index'])->name('import');
    Route::post('import', [ImportController::class, 'store'])->name('import.store');
    Route::delete('import/{batch}', [ImportController::class, 'destroy'])->name('import.destroy');

    Route::post('utilisateurs', [AdminUserController::class, 'store'])->name('users.store');
    Route::post('utilisateurs/{user}/verrouiller', [AdminUserController::class, 'lock'])->name('users.lock');
    Route::post('utilisateurs/{user}/deverrouiller', [AdminUserController::class, 'unlock'])->name('users.unlock');
    Route::post('utilisateurs/{user}/mot-de-passe', [AdminUserController::class, 'resetPassword'])->name('users.reset');
    Route::put('utilisateurs/{user}/acces', [AdminUserController::class, 'updateAccess'])->name('users.access');

    // Anomalies (règle 2.9) : validation / exclusion
    Route::get('anomalies', [AnomalyController::class, 'index'])->name('anomalies');
    Route::post('anomalies/{anomalie}/trancher', [AnomalyController::class, 'resolve'])->name('anomalies.resolve');

    // Corrections de données par article (règles 2.8 / 2.6)
    Route::get('corrections', [DataCorrectionController::class, 'index'])->name('corrections');
    Route::put('corrections/poids', [DataCorrectionController::class, 'updateWeight'])->name('corrections.poids');
    Route::put('corrections/famille', [DataCorrectionController::class, 'updateFamily'])->name('corrections.famille');
});

Route::middleware(['auth', 'page:parametres'])->prefix('parametres')->name('parametres.')->group(function () {
    Route::get('/', [SettingController::class, 'index'])->name('index');
    Route::put('/', [SettingController::class, 'update'])->name('update');

    // Mots-clés de classification client (règle 2.7)
    Route::post('mots-cles', [SettingController::class, 'storeKeyword'])->name('keywords.store');
    Route::put('mots-cles/{keyword}', [SettingController::class, 'updateKeyword'])->name('keywords.update');
    Route::delete('mots-cles/{keyword}', [SettingController::class, 'destroyKeyword'])->name('keywords.destroy');
    Route::post('reclasser-clients', [SettingController::class, 'reclassifyClients'])->name('keywords.reclassify');
});

require __DIR__.'/auth.php';
