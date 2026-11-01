<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('actif')->default(true);
        });

        Schema::table('produits', function (Blueprint $table) {
            $table->boolean('actif')->default(true);
            $table->index(['actif', 'nom']);
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->boolean('actif')->default(true);
            $table->index(['actif', 'nom']);
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('statut')->default('payee');
            $table->timestamp('annulee_at')->nullable();
            $table->foreignId('annulee_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_annulation')->nullable();
            $table->index(['statut', 'created_at']);
        });

        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('facture_id')->nullable()->constrained('factures')->nullOnDelete();
            $table->foreignId('achat_id')->nullable()->constrained('achats')->nullOnDelete();
        });

        Schema::table('mouvements_caisse', function (Blueprint $table) {
            $table->enum('moyen_paiement', ['especes', 'mobile_money'])->default('especes');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('facture_id')->nullable()->constrained('factures')->nullOnDelete();
            $table->foreignId('achat_id')->nullable()->constrained('achats')->nullOnDelete();
            $table->index(['date_jour', 'moyen_paiement']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::table('mouvements_caisse')
                ->join('factures', function ($join) {
                    $join->whereRaw("mouvements_caisse.description = CONCAT('Vente facture ', factures.numero)");
                })
                ->where('factures.mode_paiement', 'mobile_money')
                ->update([
                    'mouvements_caisse.moyen_paiement' => 'mobile_money',
                    'mouvements_caisse.facture_id' => DB::raw('factures.id'),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mouvements_caisse', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['facture_id']);
            $table->dropForeign(['achat_id']);
            $table->dropIndex(['date_jour', 'moyen_paiement']);
            $table->dropColumn(['moyen_paiement', 'user_id', 'facture_id', 'achat_id']);
        });

        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['facture_id']);
            $table->dropForeign(['achat_id']);
            $table->dropColumn(['user_id', 'facture_id', 'achat_id']);
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['annulee_par_user_id']);
            $table->dropIndex(['statut', 'created_at']);
            $table->dropColumn(['user_id', 'statut', 'annulee_at', 'annulee_par_user_id', 'motif_annulation']);
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->dropIndex(['actif', 'nom']);
            $table->dropColumn('actif');
        });

        Schema::table('produits', function (Blueprint $table) {
            $table->dropIndex(['actif', 'nom']);
            $table->dropColumn('actif');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('actif');
        });
    }
};
