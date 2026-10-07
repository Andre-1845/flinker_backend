<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Achado (02/10/2026): o upload de foto da empresa nunca teve coluna nem
 * endpoint no backend — só o do profissional foi corrigido na auditoria de
 * 2026-10-01 (achado #17). O componente AvatarUpload.tsx já documentava essa
 * lacuna ("comportamento usado hoje pela empresa, que ainda não tem endpoint
 * de logo no backend"). Esta migration fecha a paridade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('photo_url')->nullable()->after('pix_key');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('photo_url');
        });
    }
};
