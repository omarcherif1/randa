<?php

namespace App\Domain\Dashboard;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Jeu de filtres du tableau de bord (règle 2.10).
 *
 *   - societes / articles / clientCategories / familles : sélection multiple
 *   - période                    : from / to
 *
 * `user` sert au filtrage RÉEL par société (règle 3.1 / confidentialité) :
 * un utilisateur ne voit jamais que les sociétés auxquelles il est rattaché.
 */
final class DashboardFilter
{
    public function __construct(
        public readonly ?User $user = null,
        /** @var list<string> codes société */
        public readonly array $societes = [],
        /** @var list<string> codes article */
        public readonly array $articles = [],
        /** @var list<string> codes catégories client */
        public readonly array $clientCategories = [],
        /** @var list<string> codes familles */
        public readonly array $familles = [],
        public readonly ?CarbonImmutable $from = null,
        public readonly ?CarbonImmutable $to = null,
    ) {}

    public static function fromRequest(Request $request, ?User $user): self
    {
        $parseDate = function (?string $v): ?CarbonImmutable {
            if (! $v) {
                return null;
            }
            try {
                return CarbonImmutable::parse($v)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        };

        return new self(
            user: $user,
            societes: array_values(array_filter((array) $request->input('societes', []))),
            articles: array_values(array_filter((array) $request->input('articles', []))),
            clientCategories: array_values(array_filter((array) $request->input('client_categories', []))),
            familles: array_values(array_filter((array) $request->input('familles', []))),
            from: $parseDate($request->input('from')),
            to: $parseDate($request->input('to')),
        );
    }

    /** Empreinte stable — sert de clé de cache (avec la version des données). */
    public function cacheKey(): string
    {
        return md5(json_encode([
            'u' => $this->user?->is_admin ? 'admin' : ($this->user?->societeIds() ?? []),
            's' => $this->sorted($this->societes),
            'a' => $this->sorted($this->articles),
            'cc' => $this->sorted($this->clientCategories),
            'f' => $this->sorted($this->familles),
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
        ]));
    }

    /** Copie SANS le filtre société (bloc satisfaction 2.3 : toujours les 3 sociétés). */
    public function sansSociete(): self
    {
        return new self(
            $this->user, [], $this->articles,
            $this->clientCategories, $this->familles, $this->from, $this->to,
        );
    }

    public function toArray(): array
    {
        return [
            'societes' => $this->societes,
            'articles' => $this->articles,
            'client_categories' => $this->clientCategories,
            'familles' => $this->familles,
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
        ];
    }

    private function sorted(array $a): array
    {
        sort($a);

        return $a;
    }
}
