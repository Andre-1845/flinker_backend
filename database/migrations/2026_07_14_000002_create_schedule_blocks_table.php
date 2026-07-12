<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();
            // Nulo quando é um bloqueio manual (o profissional só marcou indisponibilidade,
            // sem estar ligado a um Flink confirmado)
            $table->foreignId('flink_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('start_date_time');
            $table->dateTime('end_date_time');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['professional_id', 'start_date_time', 'end_date_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_blocks');
    }
};
