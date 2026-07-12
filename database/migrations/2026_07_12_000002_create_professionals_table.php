<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professionals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('cpf')->unique();
            $table->string('phone');
            $table->string('address')->nullable();
            $table->string('pix_key')->nullable();
            $table->string('photo_url')->nullable();
            $table->boolean('is_mei')->default(false);
            $table->string('cnpj')->nullable(); // preenchido apenas se is_mei = true
            $table->decimal('reputation', 3, 2)->default(0); // média de 0.00 a 5.00
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professionals');
    }
};
