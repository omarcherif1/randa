<?php

namespace App\Domain\Admin;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Import\CommandeAggregate;
use App\Domain\Reporting\MetricsCache;
use App\Models\Anomalie;
use App\Models\CommandeLigne;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Règle 2.9 / section 4.8 — un administrateur tranche une anomalie :
 *
 *   - VALIDER  : « c'est une vraie valeur » → pour une valeur aberrante, la ou
 *                les lignes concernées RÉINTÈGRENT les indicateurs ;
 *   - EXCLURE  : « c'est bien une erreur » → les lignes restent définitivement
 *                hors des indicateurs (état déjà en place).
 *
 * Pour les anomalies qui se corrigent ailleurs (poids d'unité, famille), trancher
 * = enregistrer la revue ; la vraie correction se fait sur l'écran dédié.
 *
 * Toute décision est journalisée (3.2) et invalide le cache d'agrégats.
 */
final class AnomalyResolver
{
    public function __construct(
        private readonly CommandeAggregate $aggregate = new CommandeAggregate,
        private readonly AuditLogger $audit = new AuditLogger,
    ) {}

    public function trancher(Anomalie $anomalie, string $decision, ?string $note, User $admin): void
    {
        if (! in_array($decision, [Anomalie::STATUT_VALIDEE, Anomalie::STATUT_EXCLUE], true)) {
            throw new \InvalidArgumentException('Décision invalide.');
        }

        DB::transaction(function () use ($anomalie, $decision, $note, $admin) {
            if ($anomalie->type === 'outlier_quantite') {
                $this->traiterOutlier($anomalie, $decision);
            }

            $anomalie->forceFill([
                'statut' => $decision,
                'note' => $note,
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
            ])->save();
        });

        MetricsCache::bump();

        $this->audit->record(
            AuditAction::ANOMALIE_TRANCHEE,
            "Anomalie « {$anomalie->type} » ({$anomalie->article}) — ".
                ($decision === Anomalie::STATUT_VALIDEE ? 'validée (réintégrée)' : 'exclue définitivement'),
            $anomalie,
            ['decision' => $decision, 'note' => $note],
            $admin,
        );
    }

    /**
     * VALIDER = réintègre les lignes de cet article/commande dans les indicateurs.
     * EXCLURE = les fait (re)sortir des indicateurs, idempotent — y compris pour
     * une anomalie jamais marquée `is_outlier` par la détection automatique
     * (ex. rattrapage manuel d'une valeur signalée après coup) ou pour annuler
     * une validation précédente (l'admin change d'avis).
     */
    private function traiterOutlier(Anomalie $anomalie, string $decision): void
    {
        $numero = $anomalie->details['numero'] ?? null;
        $article = $anomalie->article;
        if ($numero === null || $article === null) {
            return;
        }

        $veutOutlier = $decision !== Anomalie::STATUT_VALIDEE;

        $lignes = CommandeLigne::query()
            ->where('article', $article)
            ->where('is_outlier', ! $veutOutlier) // ne retouche que ce qui doit réellement changer
            ->whereHas('commande', fn ($c) => $c->where('numero', $numero))
            ->with('commande')
            ->get();

        $commandes = [];
        foreach ($lignes as $ligne) {
            $ligne->forceFill([
                'is_outlier' => $veutOutlier,
                'outlier_reason' => $veutOutlier ? ($ligne->outlier_reason ?? 'exclusion_administrateur') : null,
            ])->save();
            $this->aggregate->rafraichirLigne($ligne);
            $commandes[$ligne->commande_id] = $ligne->commande;
        }
        foreach ($commandes as $commande) {
            $this->aggregate->rafraichirCommande($commande);
        }
    }
}
