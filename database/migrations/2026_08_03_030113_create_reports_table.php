<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);

            // Como a coordenada chegou — nunca inferido, sempre o que a pessoa fez.
            $table->string('position_source');

            $table->string('photo_path');
            $table->boolean('consent');

            // Relato solto no mapa: não se mistura com telemetria oficial, então
            // não tem station_id nem entra em `readings`.
            $table->string('status')->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
