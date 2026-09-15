<?php

namespace App\Domain\Access;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Models\PermissionTemplate;
use App\Models\Societe;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Section 3 / 4.8 — gestion des comptes et des droits.
 * Toute action passe par ici ; chaque action sensible est journalisée (3.2).
 */
final class AccountService
{
    public function __construct(private readonly AuditLogger $audit = new AuditLogger) {}

    /**
     * Crée un compte. Renvoie [User, motDePasseProvisoire].
     *
     * @param  list<int>  $societeIds
     * @param  list<string>  $pageKeys
     * @return array{0: User, 1: string}
     */
    public function creer(string $nom, string $email, bool $admin, array $societeIds, array $pageKeys = []): array
    {
        $provisoire = $this->motDePasseProvisoire();

        $user = User::create([
            'name' => $nom,
            'email' => mb_strtolower(trim($email)),
            'password' => Hash::make($provisoire),
            'is_admin' => $admin,
            'must_change_password' => true, // changement forcé au premier login (3.2)
            'created_by' => Auth::id(),
        ]);

        $user->societes()->sync($this->societesValides($societeIds));
        if (! $admin) {
            $this->definirPages($user, $pageKeys, journaliser: false);
        }

        $this->audit->record(
            AuditAction::COMPTE_CREE,
            "Création du compte {$email}".($admin ? ' (administrateur)' : ''),
            $user,
            ['societes' => $societeIds, 'pages' => $pageKeys, 'admin' => $admin],
        );

        return [$user, $provisoire];
    }

    public function verrouiller(User $user, ?string $motif = null): void
    {
        $user->forceFill(['locked_at' => now(), 'locked_reason' => $motif])->save();

        $this->audit->record(
            AuditAction::COMPTE_VERROUILLE,
            "Verrouillage du compte {$user->email}".($motif ? " — {$motif}" : ''),
            $user,
        );
    }

    public function deverrouiller(User $user): void
    {
        $user->forceFill([
            'locked_at' => null, 'locked_reason' => null,
            'locked_until' => null, 'failed_login_attempts' => 0,
        ])->save();

        $this->audit->record(AuditAction::COMPTE_DEVERROUILLE, "Déverrouillage du compte {$user->email}", $user);
    }

    /** Réinitialise le mot de passe et force son changement au login suivant. */
    public function reinitialiserMotDePasse(User $user): string
    {
        $provisoire = $this->motDePasseProvisoire();

        $user->forceFill([
            'password' => Hash::make($provisoire),
            'must_change_password' => true,
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ])->save();

        $this->audit->record(
            AuditAction::MOT_DE_PASSE_REINITIALISE,
            "Réinitialisation du mot de passe de {$user->email}",
            $user,
        );

        return $provisoire;
    }

    /** @param list<string> $pageKeys */
    public function definirPages(User $user, array $pageKeys, bool $journaliser = true): void
    {
        $valides = array_values(array_intersect($pageKeys, Page::assignableKeys()));
        $avant = $user->pagePermissions()->pluck('page_key')->sort()->values()->all();

        $user->pagePermissions()->whereNotIn('page_key', $valides)->delete();
        foreach ($valides as $key) {
            $user->pagePermissions()->firstOrCreate(
                ['page_key' => $key],
                ['granted_by' => Auth::id()],
            );
        }

        if ($journaliser && $avant !== $valides) {
            $this->audit->record(
                AuditAction::DROITS_MODIFIES,
                "Droits d'accès de {$user->email} modifiés",
                $user,
                ['avant' => $avant, 'apres' => $valides],
            );
        }
    }

    /** @param list<int> $societeIds */
    public function definirSocietes(User $user, array $societeIds): void
    {
        $avant = $user->societes()->pluck('societes.id')->sort()->values()->all();
        $valides = $this->societesValides($societeIds);
        $user->societes()->sync($valides);

        if ($avant !== collect($valides)->sort()->values()->all()) {
            $this->audit->record(
                AuditAction::SOCIETES_MODIFIEES,
                "Rattachement société de {$user->email} modifié",
                $user,
                ['avant' => $avant, 'apres' => $valides],
            );
        }
    }

    public function appliquerModele(User $user, PermissionTemplate $modele): void
    {
        if ($modele->accorde_admin) {
            $user->forceFill(['is_admin' => true])->save();
            $user->pagePermissions()->delete();
        } else {
            $user->forceFill(['is_admin' => false])->save();
            $this->definirPages($user, $modele->pages ?? [], journaliser: false);
        }

        $this->audit->record(
            AuditAction::MODELE_APPLIQUE,
            "Modèle « {$modele->nom} » appliqué à {$user->email}",
            $user,
            ['modele' => $modele->code],
        );
    }

    private function motDePasseProvisoire(): string
    {
        // conforme à la politique (longueur + lettres + chiffres), jamais "évident"
        return 'Rnd-'.Str::upper(Str::random(4)).Str::lower(Str::random(4)).random_int(1000, 9999);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function societesValides(array $ids): array
    {
        return Societe::whereIn('id', $ids)->pluck('id')->all();
    }
}
