<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Câmera de projeto independente (Nível do Rio / Observatório Heller &
     * Jung) — não é fonte oficial, não tem cota nem leitura, existe só pra
     * mostrar o rio ao vivo. Tabela própria porque nem toda câmera fica no
     * mesmo ponto de uma estação já catalogada: algumas não têm coordenada
     * publicada por ninguém, e o pino sai no centro do município (approximate).
     */
    public function up(): void
    {
        Schema::create('cameras', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->string('stream_url');
            $table->boolean('approximate')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cameras');
    }
};
