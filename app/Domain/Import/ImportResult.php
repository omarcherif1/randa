<?php

namespace App\Domain\Import;

/**
 * Bilan chiffré d'un import (alimente `import_batches`, consultable dans Administration).
 */
final class ImportResult
{
    public function __construct(
        public int $rowsTotal = 0,
        public int $rowsImported = 0,
        public int $rowsSkipped = 0,
        public int $commandesNew = 0,
        public int $commandesReplaced = 0,
        public int $lignesCount = 0,
        public int $clientsNew = 0,
        public int $produitsNew = 0,
        public int $anomaliesCount = 0,
        public int $famillesInconnuesCount = 0,
        public ?int $familleColonneIndex = null,
        /** @var array<int, array<string, mixed>>|null */
        public ?array $familleColonnesCandidates = null,
        /** @var list<string> */
        public array $warnings = [],
        /** @var array<string, mixed> */
        public array $stats = [],
    ) {}

    public function toBatchAttributes(): array
    {
        return [
            'rows_total' => $this->rowsTotal,
            'rows_imported' => $this->rowsImported,
            'rows_skipped' => $this->rowsSkipped,
            'commandes_new' => $this->commandesNew,
            'commandes_replaced' => $this->commandesReplaced,
            'lignes_count' => $this->lignesCount,
            'clients_new' => $this->clientsNew,
            'produits_new' => $this->produitsNew,
            'anomalies_count' => $this->anomaliesCount,
            'familles_inconnues_count' => $this->famillesInconnuesCount,
            'famille_colonne_index' => $this->familleColonneIndex,
            'famille_colonnes_candidates' => $this->familleColonnesCandidates,
            'warnings' => $this->warnings ?: null,
            'stats' => $this->stats ?: null,
        ];
    }
}
