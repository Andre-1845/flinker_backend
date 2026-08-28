<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `pix_key` passa a ser criptografado em repouso (cast `encrypted` nos models
 * Professional/Company) — o valor cifrado é bem maior que o texto original, então a
 * coluna precisa deixar de ser `string` (varchar 255) e virar `text`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->text('pix_key')->nullable()->change();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->text('pix_key')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->string('pix_key')->nullable()->change();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('pix_key')->nullable()->change();
        });
    }
};
