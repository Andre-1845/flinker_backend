<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suporte à nova regra de conclusão do Flink: dupla confirmação (empresa +
 * profissional) com prazo automático — ver App\Domain\Match\Actions\ConfirmCompletionAction
 * e App\Console\Commands\AutoCompleteFlinks. Antes, só a empresa confirmava, sem
 * prazo, e o pagamento podia ficar preso se ela nunca agisse (achado da auditoria).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('professional_confirmed_at')->nullable()->after('checked_in_at');
            $table->timestamp('company_confirmed_at')->nullable()->after('professional_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['professional_confirmed_at', 'company_confirmed_at']);
        });
    }
};
