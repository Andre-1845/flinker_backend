<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            // Nulo para transações de margem da plataforma (platform_fee), que não
            // pertencem à carteira de nenhum usuário — só ficam registradas pra relatório.
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            // Nulo para depósito/saque avulsos; preenchido para reserva, estorno, ganho e margem
            $table->foreignId('flink_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // deposit, withdrawal, reservation, refund, earning, platform_fee
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending')->index(); // pending, completed, failed, cancelled
            // Referência da transação no Mercado Pago (preference_id, payment_id, etc.)
            $table->string('external_reference')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
