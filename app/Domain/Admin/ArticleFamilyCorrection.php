<?php

namespace App\Domain\Admin;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Reporting\MetricsCache;
use App\Models\ArticleFamily;
use App\Models\CommandeLigne;
use App\Models\Famille;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Règle 2.6 — un administrateur tranche/corrige la famille d'un article
 * (famille inconnue, ambiguë, reclassement à confirmer).
 *
 * La correction devient la valeur de référence (`statut = correction_manuelle`,
 * protégée des ré-imports), est répercutée sur le produit et toutes ses lignes,
 * journalisée (3.2) et invalide le cache.
 */
final class ArticleFamilyCorrection
{
    public const STATUT = 'correction_manuelle';

    public function __construct(private readonly AuditLogger $audit = new AuditLogger) {}

    public function corriger(string $article, string $familleCode, User $admin): void
    {
        $famille = Famille::where('code', $familleCode)->first();
        if ($famille === null) {
            throw new \InvalidArgumentException("Famille inconnue : {$familleCode}.");
        }

        DB::transaction(function () use ($article, $famille, $familleCode, $admin) {
            $ref = ArticleFamily::firstOrNew(['article' => $article]);
            $avant = $ref->famille_code;

            $ref->forceFill([
                'famille_code' => $familleCode,
                'famille_id' => $famille->id,
                'statut' => self::STATUT,
                'corrige_par' => $admin->id,
                'corrige_le' => now(),
            ])->save();

            Produit::where('article', $article)->update([
                'famille_id' => $famille->id,
                'famille_source' => 'manuel',
            ]);

            CommandeLigne::where('article', $article)->update([
                'famille_id' => $famille->id,
                'famille_source' => 'manuel',
            ]);

            $this->audit->record(
                AuditAction::PARAMETRE_MODIFIE,
                "Famille de l'article {$article} fixée à {$familleCode} ({$famille->intitule})".($avant ? " (était {$avant})" : ''),
                $ref,
                ['article' => $article, 'avant' => $avant, 'apres' => $familleCode],
                $admin,
            );
        });

        MetricsCache::bump();
    }
}
