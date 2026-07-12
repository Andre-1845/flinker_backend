<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('cnpj')->unique();
            $table->string('responsible_name');
            $table->string('responsible_cpf');
            $table->string('phone');
            $table->string('pix_key')->nullable();
            $table->decimal('reputation', 3, 2)->default(0); // média de 0.00 a 5.00
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
