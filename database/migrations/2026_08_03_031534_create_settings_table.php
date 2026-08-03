<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Linha única (id 1): não é config genérica chave-valor, são os
        // poucos campos concretos que o admin precisa trocar sem redeploy.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->string('pix_key')->nullable();
            $table->string('pix_receiver_name')->nullable();
            $table->string('pix_receiver_city')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
