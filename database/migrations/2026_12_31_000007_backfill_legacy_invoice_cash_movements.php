<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('mouvements_caisse')
            ->join('factures', function ($join) {
                $join->whereRaw("mouvements_caisse.description = CONCAT('Vente facture ', factures.numero)");
            })
            ->update([
                'mouvements_caisse.moyen_paiement' => DB::raw('factures.mode_paiement'),
                'mouvements_caisse.facture_id' => DB::raw('factures.id'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Historical links are retained because removing them would reduce auditability.
    }
};
