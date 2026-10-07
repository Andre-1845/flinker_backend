<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fonte única de verdade de "quando esse Flink foi concluído", preenchida
     * só em CompleteFlinkAction::handle() — o único lugar por onde passam os
     * dois caminhos de conclusão (confirmação dupla explícita e o comando
     * `flinks:auto-complete`). Sem isso, não dá pra ancorar a expiração do
     * chat (achado #6, retenção configurável) de forma confiável: o
     * auto-complete pode concluir o Flink sem nunca preencher os dois
     * `*_confirmed_at` do match.
     */
    public function up(): void
    {
        Schema::table('flinks', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
        });

        // Backfill pros Flinks já concluídos antes desta coluna existir — sem
        // isso eles nunca teriam a conversa expirada pela retenção (achado
        // #6), mesmo já concluídos há muito tempo. updated_at é a melhor
        // aproximação disponível (é a própria CompleteFlinkAction quem fez
        // esse update, então, salvo algo mais ter mexido no Flink depois, é o
        // exato momento da conclusão).
        DB::table('flinks')
            ->where('status', 'completed')
            ->whereNull('completed_at')
            ->update(['completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('flinks', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
