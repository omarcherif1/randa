<?php

namespace App\Domain\Classification;

use App\Support\Text;

/**
 * Règle métier 2.7 — détermine la catégorie d'un client.
 *
 * Identifiant STABLE : le numéro client (jamais le nom). Chaque client n'est
 * classifié qu'UNE fois ; la classification est ensuite réutilisée pour toutes
 * ses commandes.
 *
 * Ordre de priorité :
 *   1. source fiable numéro client => catégorie : la « Famille statistique »
 *      client portée par la ligne du fichier (code déjà vérifié par l'appelant),
 *      sinon la table de référence injectée ;
 *   2. déduction objective (client livré à l'étranger => Exportation) ;
 *   3. reconnaissance par mots-clés dans le nom du client ;
 *   4. sinon "Indéfini" (jamais masqué).
 */
final class ClientCategoryClassifier
{
    /**
     * @param  array<string, string>  $referenceMap  numéro client => code catégorie (source fiable)
     * @param  array<int, array{code:string, keyword:string, priorite:int}>  $keywords
     *                                                                                  liste plate des mots-clés normalisés (issue de la table `classification_keywords`)
     * @param  string  $exportCategoryCode  code de la catégorie "Exportation"
     * @param  string  $fallbackCategoryCode  code de la catégorie "Indéfini / Divers"
     */
    public function __construct(
        private readonly array $referenceMap,
        private readonly array $keywords,
        private readonly string $exportCategoryCode,
        private readonly string $fallbackCategoryCode,
    ) {}

    public function classify(
        string $numeroClient,
        ?string $nomClient,
        bool $livreALEtranger = false,
        ?string $categorieFichier = null,
    ): ClientCategoryResolution {
        $numero = trim($numeroClient);

        // 1. Source fiable : famille statistique client du fichier, puis table de référence
        if ($categorieFichier !== null && $categorieFichier !== '') {
            return new ClientCategoryResolution(
                $categorieFichier,
                ClientCategoryResolution::SOURCE_REFERENCE,
                ClientCategoryResolution::DETAIL_FICHIER,
            );
        }
        if ($numero !== '' && isset($this->referenceMap[$numero])) {
            return new ClientCategoryResolution(
                $this->referenceMap[$numero],
                ClientCategoryResolution::SOURCE_REFERENCE,
            );
        }

        // 2. Déduction objective
        if ($livreALEtranger) {
            return new ClientCategoryResolution(
                $this->exportCategoryCode,
                ClientCategoryResolution::SOURCE_DEDUCED,
                'Client livré à l\'étranger',
            );
        }

        // 3. Mots-clés dans le nom
        $match = $this->matchKeyword($nomClient);
        if ($match !== null) {
            return new ClientCategoryResolution(
                $match['code'],
                ClientCategoryResolution::SOURCE_KEYWORD,
                'Mot-clé « '.$match['keyword'].' »',
            );
        }

        // 4. Indéfini
        return new ClientCategoryResolution(
            $this->fallbackCategoryCode,
            ClientCategoryResolution::SOURCE_UNDEFINED,
        );
    }

    /**
     * @return array{code:string, keyword:string, priorite:int}|null
     */
    private function matchKeyword(?string $nomClient): ?array
    {
        $nom = Text::normalize($nomClient);
        if ($nom === '') {
            return null;
        }

        $padded = ' '.$nom.' ';
        $best = null;

        foreach ($this->keywords as $entry) {
            $kw = $entry['keyword'];
            if ($kw === '') {
                continue;
            }
            // match "mot entier" : le mot-clé entouré de séparateurs
            if (! str_contains($padded, ' '.$kw.' ') && ! str_contains($nom, $kw)) {
                continue;
            }

            if (
                $best === null
                || $entry['priorite'] > $best['priorite']
                || ($entry['priorite'] === $best['priorite'] && mb_strlen($kw) > mb_strlen($best['keyword']))
            ) {
                $best = $entry;
            }
        }

        return $best;
    }
}
