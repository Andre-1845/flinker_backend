<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Retenção do chat (achado #6, pedido do Andre 2026-10-02): conversas de
     * matches concluídos há mais de N dias são apagadas pelo comando agendado
     * `chat:prune-expired`. 0 = nunca apaga. Padrão 60 dias.
     */
    public function up(): void
    {
        DB::table('settings')->insert([
            'key' => 'chat_retention_days',
            'value' => '60',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'chat_retention_days')->delete();
    }
};
