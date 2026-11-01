<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_money_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->nullable()->constrained('factures')->nullOnDelete();
            $table->string('provider')->default('mtn');
            $table->string('status')->default('pending');
            $table->string('reference')->unique();
            $table->string('external_reference')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('montant', 12, 2);
            $table->string('devise')->default('XOF');
            $table->string('operation')->default('collect');
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index('facture_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_money_payments');
    }
};
