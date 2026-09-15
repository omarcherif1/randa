<?php

namespace App\Domain\Import;

use RuntimeException;

/**
 * Le fichier n'a pas passé la validation préalable (structure / colonnes / taille).
 * Message destiné au journal d'import ; côté utilisateur, on n'affiche qu'un
 * message générique (exigence 3.2).
 */
class ImportValidationException extends RuntimeException {}
