<?php

namespace App\Models;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Reporting\MetricsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Paramètre réglable (page Paramètres, section 4.9).
 * Accès typé et mis en cache via les helpers statiques — à utiliser partout
 * plutôt que de lire la table directement.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'libelle', 'description'];

    protected $casts = [
        'value' => 'array',
    ];

    private const CACHE_KEY = 'settings.all';

    public static function allValues(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return self::all()->mapWithKeys(fn (Setting $s) => [
                $s->key => $s->typedValue(),
            ])->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::allValues()[$key] ?? $default;
    }

    /**
     * Modifie un paramètre. Action sensible => journalisée dans l'audit (3.2),
     * sauf appel système explicite (`$audit = false`, ex. seeders).
     */
    public static function put(string $key, mixed $value, bool $audit = true): void
    {
        $setting = self::where('key', $key)->firstOrFail();
        $avant = $setting->typedValue();
        $setting->update(['value' => ['v' => $value]]);
        self::flush();

        if ($avant != $value) {
            // Un seuil réglable qui change (règle 2 : seuils configurables) rend
            // obsolètes les agrégats déjà calculés — même mécanisme que l'import.
            MetricsCache::bump();
        }

        if ($audit && $avant != $value) {
            (new AuditLogger)->record(
                AuditAction::PARAMETRE_MODIFIE,
                "Paramètre « {$setting->libelle} » modifié",
                $setting,
                ['key' => $key, 'avant' => $avant, 'apres' => $value],
            );
        }
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function typedValue(): mixed
    {
        $raw = $this->value['v'] ?? null;

        if ($raw === null) {
            return null;
        }

        return match ($this->type) {
            'int' => (int) $raw,
            'float' => (float) $raw,
            'bool' => (bool) $raw,
            default => $raw,
        };
    }
}
