<?php

namespace App\Domain\Import;

use App\Support\Text;
use RuntimeException;

/**
 * Résout les champs logiques du fichier unifié quotidien vers des index de
 * colonnes physiques (section 5).
 *
 * Le fichier a des en-têtes en double ("Date commande" ×2, "Famille statistique"
 * ×6). On ne code jamais "3ᵉ colonne" en dur : la colonne famille est choisie par
 * FamilyColumnDetector (règle 2.6) et injectée ici via `withFamilyColumn()`.
 */
final class ColumnMap
{
    /** libellé logique => libellés d'en-tête acceptés (normalisés) */
    private const FIELDS = [
        'site_vente' => ['site vente'],
        'date_commande' => ['date commande'],
        'no_commande' => ['no commande', 'numero commande'],
        'client_numero' => ['client commande'],
        'adresse_livraison' => ['adresse livraison'],
        'client_nom' => ['nom client commande'],
        'ville_commande' => ['ville commande'],
        'article' => ['article'],
        'designation' => ['designation 1', 'designation'],
        'unite_vente' => ['unite vente'],
        'qte_commandee' => ['quantite commandee'],
        'qte_livree' => ['quantite livree'],
        'qte_facturee' => ['qte facturee us', 'quantite facturee'],
        'no_livraison' => ['no livraison'],
        'no_facture' => ['no facture'],
        'date_facture' => ['date facture'],
        'etat_ligne' => ['etat ligne'],
    ];

    private const FAMILY_HEADER = 'famille statistique';

    /** @var array<string, int> */
    private array $index = [];

    /** @var list<int> */
    public readonly array $familyColumnCandidates;

    private ?int $familyColumn = null;

    /** @param list<string> $header en-têtes UTF-8 */
    public function __construct(public readonly array $header)
    {
        $normalized = array_map([$this, 'normalize'], $header);

        foreach (self::FIELDS as $logical => $accepted) {
            foreach ($normalized as $i => $label) {
                if (in_array($label, $accepted, true)) {
                    $this->index[$logical] = $i; // première occurrence
                    break;
                }
            }
        }

        $fam = [];
        foreach ($normalized as $i => $label) {
            if ($label === self::FAMILY_HEADER) {
                $fam[] = $i;
            }
        }
        $this->familyColumnCandidates = $fam;
    }

    /** @return list<string> champs logiques manquants dans l'en-tête */
    public function missingFields(): array
    {
        $required = array_keys(self::FIELDS);

        return array_values(array_diff($required, array_keys($this->index)));
    }

    public function withFamilyColumn(?int $columnIndex): self
    {
        $this->familyColumn = $columnIndex;

        return $this;
    }

    public function has(string $logical): bool
    {
        return isset($this->index[$logical]);
    }

    public function indexOf(string $logical): int
    {
        if (! isset($this->index[$logical])) {
            throw new RuntimeException("Champ logique inconnu ou absent de l'en-tête : {$logical}");
        }

        return $this->index[$logical];
    }

    /** @param list<string> $row */
    public function value(array $row, string $logical): string
    {
        return $row[$this->indexOf($logical)] ?? '';
    }

    /** @param list<string> $row */
    public function familyCode(array $row): ?string
    {
        if ($this->familyColumn === null) {
            return null;
        }
        $v = trim($row[$this->familyColumn] ?? '');

        return $v === '' ? null : $v;
    }

    private function normalize(string $label): string
    {
        $label = Text::stripAccents($label);
        $label = preg_replace('/[^a-z0-9]+/', ' ', $label) ?? '';

        return trim(preg_replace('/\s+/', ' ', $label) ?? '');
    }
}
