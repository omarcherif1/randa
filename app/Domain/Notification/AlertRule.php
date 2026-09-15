<?php

namespace App\Domain\Notification;

use App\Models\User;

/**
 * Section 6 — une source d'alertes automatiques.
 *
 * Ajouter un type d'alerte (échec d'import, nouvelle anomalie, chute de fiabilité
 * d'une prévision, …) = écrire une classe qui implémente cette interface et
 * l'ajouter à `config/notifications.php`. Rien d'autre dans la structure ne change
 * (exigence 3 — conçu pour être étendu).
 */
interface AlertRule
{
    /** Clé stable de la règle (regroupement / journalisation / tests). */
    public function cle(): string;

    /**
     * Page du catalogue (règle 3.1) dont l'accès conditionne la visibilité des
     * alertes de cette règle, ou `null` si l'alerte est visible par tout compte.
     */
    public function pageRequise(): ?string;

    /**
     * Alertes actives pour cette personne. L'implémentation DOIT déjà respecter
     * le périmètre société de l'utilisateur (via les agrégats déjà filtrés ou
     * `visiblePour($user)`).
     *
     * @return list<Alert>
     */
    public function evaluer(?User $user): array;
}
