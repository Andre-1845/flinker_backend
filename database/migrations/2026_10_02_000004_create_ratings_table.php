<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avaliações bilaterais de um flink concluído (achado de perfil mockado,
 * 02/10/2026 — "Fase 5" do roadmap original, nunca implementada; só
 * existiam pastas vazias em app/Domain/Rating). Uma avaliação por direção
 * por match: o profissional avalia a empresa, a empresa avalia o
 * profissional. A média de `stars` recebidas alimenta a coluna
 * `reputation` que já existia (zerada) em `professionals`/`companies`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('rater_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rated_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->text('comment')->nullable();
            // Quem foi avaliado pode ocultar uma avaliação específica do mural
            // público do próprio perfil — não afeta o cálculo da média (ver
            // SubmitRatingAction), só evita que escondam as ruins pra inflar nota.
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->unique(['match_id', 'rater_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
