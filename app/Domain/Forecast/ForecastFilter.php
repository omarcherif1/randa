<?php

namespace App\Domain\Forecast;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Filtres de la page Prévisions. Volontairement minimal et extensible :
 * la V3 (estimation des quantités à produire, lignes de production) ajoutera
 * ses propres critères ici sans casser l'existant.
 */
final class ForecastFilter
{
    public function __construct(
        public readonly ?User $user = null,
        public readonly ?string $societeCode = null,
        /** @var list<string> codes familles */
        public readonly array $familles = [],
    ) {}

    public static function fromRequest(Request $request, ?User $user): self
    {
        return new self(
            user: $user,
            societeCode: $request->filled('societe') ? (string) $request->string('societe') : null,
            familles: array_values(array_filter((array) $request->input('familles', []))),
        );
    }

    public function cacheKey(): string
    {
        $familles = $this->familles;
        sort($familles);

        return md5(json_encode([
            'u' => $this->user?->is_admin ? 'admin' : ($this->user?->societeIds() ?? []),
            's' => $this->societeCode,
            'f' => $familles,
        ]));
    }

    public function toArray(): array
    {
        return ['societe' => $this->societeCode, 'familles' => $this->familles];
    }
}
