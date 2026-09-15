<?php

namespace App\Domain\Admin;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Import\CommandeAggregate;
use App\Domain\Reporting\MetricsCache;
use App\Domain\Units\UnitWeightOrigin;
use App\Models\ArticleUnitWeight;
use App\Models\CommandeLigne;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Règle 2.8 — un administrateur fixe/corrige le poids d'une unité de vente pour
 * un article. La correction :
 *   - devient la valeur de référence (`origine = correction_manuelle`, protégée
 *     des ré-imports) ;
 *   - est RÉPERCUTÉE sur toutes les lignes déjà importées de cet article
 *     (conversion kg + agrégats recalculés) — les tonnages reflètent la décision ;
 *   - est journalisée (3.2) et invalide le cache.
 */
final class ArticleWeightCorrection
{
    public function __construct(
        private readonly CommandeAggregate $aggregate = new CommandeAggregate,
        private readonly AuditLogger $audit = new AuditLogger,
    ) {}

    public function corriger(string $article, float $poidsKg, User $admin): void
    {
        if ($poidsKg <= 0) {
            throw new \InvalidArgumentException('Le poids doit être strictement positif.');
        }

        DB::transaction(function () use ($article, $poidsKg, $admin) {
            $ref = ArticleUnitWeight::firstOrNew(['article' => $article]);
            $ancien = $ref->poids_unitaire_kg;

            $ref->forceFill([
                'poids_unitaire_kg' => $poidsKg,
                'origine' => UnitWeightOrigin::CORRECTION_MANUELLE,
                'en_conflit' => false,
                'conflit_details' => null,
                'corrige_par' => $admin->id,
                'corrige_le' => now(),
            ])->save();

            $commandes = [];
            CommandeLigne::where('article', $article)->with('commande')->chunkById(500, function ($lignes) use ($poidsKg, &$commandes) {
                foreach ($lignes as $ligne) {
                    $ligne->forceFill([
                        'poids_unitaire_kg' => $poidsKg,
                        'poids_origine' => UnitWeightOrigin::CORRECTION_MANUELLE,
                        'qte_commandee_kg' => round((float) $ligne->qte_commandee_raw * $poidsKg, 3),
                        'qte_livree_kg' => round((float) $ligne->qte_livree_raw * $poidsKg, 3),
                        'qte_facturee_kg' => round((float) $ligne->qte_facturee_raw * $poidsKg, 3),
                    ])->save();
                    $this->aggregate->rafraichirLigne($ligne);
                    $commandes[$ligne->commande_id] = $ligne->commande;
                }
            });
            foreach ($commandes as $commande) {
                $this->aggregate->rafraichirCommande($commande);
            }

            $this->audit->record(
                AuditAction::PARAMETRE_MODIFIE,
                "Poids unitaire de l'article {$article} fixé à {$poidsKg} kg".($ancien !== null ? " (était {$ancien})" : ''),
                $ref,
                ['article' => $article, 'avant' => $ancien, 'apres' => $poidsKg],
                $admin,
            );
        });

        MetricsCache::bump();
    }
}
