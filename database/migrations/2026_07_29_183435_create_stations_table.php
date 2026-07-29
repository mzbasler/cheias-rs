<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();

            // Proveniência: de qual fonte a estação veio e com que código lá.
            $table->string('source');
            $table->string('external_id');

            $table->string('name');
            $table->string('river')->nullable();
            $table->string('municipality')->nullable();

            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);

            // Cotas de referência. Nulas quando a fonte não as informa — sem cota
            // não há como classificar alerta ou crítico.
            $table->decimal('alert_level', 8, 2)->nullable();
            $table->decimal('critical_level', 8, 2)->nullable();
            $table->string('unit')->nullable();

            $table->timestamps();

            $table->unique(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
