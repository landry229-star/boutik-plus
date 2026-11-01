<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->decimal('montant_ht', 12, 2)->nullable();
            $table->decimal('montant_taxe', 12, 2)->nullable();
            $table->decimal('taux_taxe', 5, 2)->nullable();
            $table->string('libelle_taxe')->nullable();
        });

        DB::table('factures')->update([
            'montant_ht' => DB::raw('montant_total'),
            'montant_taxe' => 0,
            'taux_taxe' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropColumn(['montant_ht', 'montant_taxe', 'taux_taxe', 'libelle_taxe']);
        });
    }
};
