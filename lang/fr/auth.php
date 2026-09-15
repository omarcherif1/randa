<?php

/**
 * Traductions du cœur d'authentification Laravel — manquaient entièrement
 * (le framework ne fournit plus de fichiers de langue par défaut depuis la v11).
 * `trans('auth.failed')` / `trans('auth.throttle')` sont utilisés par
 * App\Http\Requests\Auth\LoginRequest (règle 3.2) ; sans ce fichier, la clé
 * brute "auth.failed" s'affichait telle quelle à l'utilisateur.
 */
return [
    'failed' => 'Ces identifiants ne correspondent à aucun compte.',
    'password' => 'Le mot de passe saisi est incorrect.',
    'throttle' => 'Trop de tentatives de connexion. Réessayez dans :seconds secondes.',
];
