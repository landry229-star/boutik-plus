<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achats', function (Blueprint $table) {
            $table->string('utilisateur_nom_snapshot')->nullable();
            $table->string('annulee_par_nom_snapshot')->nullable();
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->string('utilisateur_nom_snapshot')->nullable();
            $table->string('annulee_par_nom_snapshot')->nullable();
        });

        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->string('utilisateur_nom_snapshot')->nullable();
        });

        Schema::table('mouvements_caisse', function (Blueprint $table) {
            $table->string('utilisateur_nom_snapshot')->nullable();
        });

        DB::table('users')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    foreach (['achats', 'factures', 'mouvements_stock', 'mouvements_caisse'] as $table) {
                        DB::table($table)
                            ->where('user_id', $user->id)
                            ->update(['utilisateur_nom_snapshot' => $user->name]);
                    }

                    DB::table('achats')
                        ->where('annulee_par_user_id', $user->id)
                        ->update(['annulee_par_nom_snapshot' => $user->name]);

                    DB::table('factures')
                        ->where('annulee_par_user_id', $user->id)
                        ->update(['annulee_par_nom_snapshot' => $user->name]);
                }
            });

        Schema::table('achats', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('achats', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('achats')->whereNull('user_id')->whereNotNull('utilisateur_nom_snapshot')->exists()
            || DB::table('factures')->whereNull('user_id')->whereNotNull('utilisateur_nom_snapshot')->exists()
            || DB::table('factures')->whereNull('annulee_par_user_id')->whereNotNull('annulee_par_nom_snapshot')->exists()
            || DB::table('mouvements_stock')->whereNull('user_id')->whereNotNull('utilisateur_nom_snapshot')->exists()
            || DB::table('mouvements_caisse')->whereNull('user_id')->whereNotNull('utilisateur_nom_snapshot')->exists()
            || DB::table('achats')->whereNull('annulee_par_user_id')->whereNotNull('annulee_par_nom_snapshot')->exists()) {
            throw new RuntimeException('Cannot roll back user attribution snapshots after a referenced account has been deleted.');
        }

        Schema::table('achats', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('achats', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropColumn(['utilisateur_nom_snapshot', 'annulee_par_nom_snapshot']);
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->dropColumn(['utilisateur_nom_snapshot', 'annulee_par_nom_snapshot']);
        });

        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->dropColumn('utilisateur_nom_snapshot');
        });

        Schema::table('mouvements_caisse', function (Blueprint $table) {
            $table->dropColumn('utilisateur_nom_snapshot');
        });
    }
};
