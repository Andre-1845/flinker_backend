<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chat do match (achado #6 da auditoria 2026-10-01: a tela de Chat era 100%
     * mock, sem nenhuma infraestrutura no backend). Uma conversa por match,
     * liberada só depois do aceite mútuo (MatchStatus::Confirmed) — mesma regra
     * já anunciada na UI ("o chat é liberado somente após o aceite bilateral").
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            // Lido pelo destinatário (não pelo remetente) — usado pro contador de
            // não lidas na lista de conversas.
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // (match_id, id) cobre tanto "últimas mensagens de um match" quanto o
            // polling incremental (WHERE match_id = ? AND id > ?).
            $table->index(['match_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
