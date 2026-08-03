<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            // O SGB/CPRM publica três cotas — atenção, alerta e inundação.
            // Espremer as três em duas apagaria a primeira faixa de risco e
            // mostraria como "normal" um rio já em atenção.
            $table->decimal('attention_level', 8, 2)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropColumn('attention_level');
        });
    }
};
