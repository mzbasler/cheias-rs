<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            // Nível (m, medido) e vazão (m³/s, estimada por modelo) são grandezas
            // diferentes: sem separá-las, uma sobrescreveria a outra no mesmo instante.
            $table->string('metric')->default('level')->after('station_id');

            $table->dropUnique(['station_id', 'measured_at']);
            $table->unique(['station_id', 'metric', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dropUnique(['station_id', 'metric', 'measured_at']);
            $table->unique(['station_id', 'measured_at']);
            $table->dropColumn('metric');
        });
    }
};
