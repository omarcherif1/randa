<?php

namespace App\Domain\Classification;

final class ClientCategoryResolution
{
    public const SOURCE_REFERENCE = 'reference';   // table fiable numéro client => catégorie

    public const SOURCE_DEDUCED = 'deduced';       // déduction objective (ex. livré à l'étranger)

    public const SOURCE_KEYWORD = 'keyword';       // mot-clé dans le nom du client

    public const SOURCE_UNDEFINED = 'undefined';   // catégorie "Indéfini"

    public const DETAIL_FICHIER = 'Famille statistique client du fichier';

    public function __construct(
        public readonly ?string $categoryCode,
        public readonly string $source,
        public readonly ?string $detail = null,
    ) {}

    public function isDefined(): bool
    {
        return $this->source !== self::SOURCE_UNDEFINED;
    }
}
