<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boutique_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name')->default('Boutik Plus');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('ifu')->nullable();
            $table->string('rccm')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('legal_notice')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boutique_settings');
    }
};
