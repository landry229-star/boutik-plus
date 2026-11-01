<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achats', function (Blueprint $table) {
            $table->string('statut')->default('enregistree');
            $table->timestamp('annulee_at')->nullable();
            $table->foreignId('annulee_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_annulation')->nullable();
            $table->index(['statut', 'date_achat']);
        });

        Schema::table('mouvements_caisse', function (Blueprint $table) {
            $table->foreignId('mouvement_origine_id')->nullable()
                ->unique()
                ->constrained('mouvements_caisse')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mouvements_caisse', function (Blueprint $table) {
            $table->dropForeign(['mouvement_origine_id']);
            $table->dropUnique(['mouvement_origine_id']);
            $table->dropColumn('mouvement_origine_id');
        });

        Schema::table('achats', function (Blueprint $table) {
            $table->dropForeign(['annulee_par_user_id']);
            $table->dropIndex(['statut', 'date_achat']);
            $table->dropColumn(['statut', 'annulee_at', 'annulee_par_user_id', 'motif_annulation']);
        });
    }
};
