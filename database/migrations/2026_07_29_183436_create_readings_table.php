<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();

            $table->decimal('value', 8, 2);

            // Instante em que a fonte mediu — nunca o instante em que gravamos.
            // É o que permite mostrar dado velho como velho.
            $table->timestamp('measured_at');

            $table->string('trend')->nullable();
            $table->string('source');

            $table->timestamps();

            // Uma leitura por estação por instante: reimportar não duplica.
            $table->unique(['station_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};
