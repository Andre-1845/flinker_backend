<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela genérica de configurações administráveis em runtime (ver
     * App\Domain\Settings\Services\SettingsService). Primeiro uso: margem da
     * plataforma, editável pelo painel Filament (Resources\Settings) em vez de
     * fixa em config/flinker.php — ver achado da auditoria 2026-10-01 (#15:
     * taxa exibida pro frontend divergia da margem real cobrada).
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Semeia a margem com o valor que já estava fixo em config/flinker.php,
        // pra não mudar o comportamento de ninguém no momento em que essa
        // migration roda — o admin ajusta depois pelo painel se quiser.
        DB::table('settings')->insert([
            'key' => 'platform_margin_percent',
            'value' => (string) config('flinker.platform_margin_percent', 7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
