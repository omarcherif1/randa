<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sauvegarde / restauration (section 3.2, étape 10)
    |--------------------------------------------------------------------------
    |
    | Sauvegarde LOGIQUE et portable : un dossier horodaté contenant une copie
    | JSONL de chaque table applicative + les fichiers sources archivés à
    | l'import + un manifeste. Aucun outil externe (mysqldump, zip) requis :
    | fonctionne à l'identique en local, en test et en production.
    |
    */

    // Où déposer les sauvegardes.
    'racine' => storage_path('app/backups'),

    // Nombre de sauvegardes à conserver (les plus anciennes sont supprimées).
    'conserver' => (int) env('BACKUP_CONSERVER', 14),

    // Tables techniques exclues (recréées par les migrations / sans valeur métier).
    'tables_ignorees' => [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
    ],

    // Disque + dossier des fichiers sources archivés par ImportManager.
    'disque_imports' => 'local',
    'dossier_imports' => 'imports',

];
