<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flink_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();

            // Check-in geolocalizado (preenchido quando o profissional confirma presença)
            $table->timestamp('checked_in_at')->nullable();
            $table->decimal('checkin_latitude', 10, 7)->nullable();
            $table->decimal('checkin_longitude', 10, 7)->nullable();

            $table->timestamps();

            // Um profissional só pode demonstrar interesse uma vez no mesmo Flink
            $table->unique(['flink_id', 'professional_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
