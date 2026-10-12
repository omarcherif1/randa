<?php

/*
 * Lexique de réparation des accents (App\Domain\Import\AccentRepair).
 *
 * L'extraction Sage F1790079066766 remplace chaque caractère accentué par « � »
 * (U+FFFD). Un mot abîmé n'est réparé que si UN SEUL mot de ce lexique lui
 * correspond (même longueur, mêmes lettres non accentuées, casse ignorée) —
 * sinon il est laissé tel quel et signalé à l'import.
 *
 * Amorcé avec TOUS les mots accentués de l'extraction précédente
 * F1787140089784 (Windows-1252, accents intacts) + mots métier courants.
 * Ajouter ici un mot signalé « non réparé » suffit au prochain import.
 */
return [
    // F1787140089784 — accents d'origine
    'Déchet', 'Désignation', 'Gérant', 'Qté', 'Quantité', 'Soldé', 'Spéciale',
    'Sté', 'Unité', 'ZABÄAR', 'caré', 'commandée', 'dérivés', 'facturée',
    'livrée', 'étuvé',

    // mots métier courants
    'Société', 'Sociétés', 'Livré', 'Facturé', 'Commandé', 'Préparation', 'Préparé',
    'Réservé', 'Clôturé', 'Clôturée', 'Annulé', 'Annulée', 'Pâtes', 'Pâtisserie',
    'Blé', 'Dérivé', 'Céréales', 'Sélection', 'Supérieure', 'Supérieur', 'Qualité',
    'Spécial', 'Entrepôt', 'Dépôt', 'Hôtel', 'Café', 'Sucré', 'Salé', 'Grillé',
    'Précuit', 'Médenine', 'Gabès', 'Béja', 'Kébili', 'Kélibia',
];
