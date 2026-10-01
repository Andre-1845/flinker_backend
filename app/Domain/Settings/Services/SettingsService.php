<?php

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Models\Setting;
use Throwable;

/**
 * Configurações administráveis em runtime pelo painel Filament (ver
 * App\Filament\Resources\Settings\SettingResource), com fallback pro valor
 * fixo de config/*.php quando a linha ainda não existe no banco — cobre tanto
 * o período antes da migration rodar quanto os testes unitários, que usam
 * SQLite em memória sem rodar migrations (ver tests/Unit/Domain/Flink/
 * PricingServiceTest.php, que instancia `new PricingService` direto).
 */
class SettingsService
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        try {
            $value = Setting::query()->where('key', $key)->value('value');
        } catch (Throwable) {
            // Tabela ainda não existe (migration não rodou / banco não migrado
            // em teste) — cai pro default em vez de derrubar a aplicação.
            $value = null;
        }

        return $this->cache[$key] = $value ?? $default;
    }

    public function getFloat(string $key, float $default): float
    {
        return (float) $this->get($key, $default);
    }

    public function set(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->cache[$key] = $value;
    }
}
