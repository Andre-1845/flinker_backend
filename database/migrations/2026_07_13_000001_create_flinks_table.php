<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type');
            $table->string('location'); // endereço legível, exibido para o profissional
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->dateTime('start_date_time');
            $table->dateTime('end_date_time');
            $table->text('requirements')->nullable();
            $table->string('status')->default('open')->index();

            // Precificação (ver PricingService) — valores em reais, com 2 casas decimais
            $table->decimal('net_value', 10, 2); // o que o profissional recebe (informado pela empresa)
            $table->decimal('platform_margin', 10, 2); // calculado automaticamente
            $table->decimal('total_value', 10, 2); // net_value + platform_margin, cobrado da empresa

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flinks');
    }
};
