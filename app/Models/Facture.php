<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facture extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'numero',
        'client_nom',
        'client_telephone',
        'client_email',
        'montant_total',
        'montant_ht',
        'montant_taxe',
        'taux_taxe',
        'libelle_taxe',
        'mode_paiement',
        'statut',
        'annulee_at',
        'annulee_par_user_id',
        'annulee_par_nom_snapshot',
        'motif_annulation',
        'utilisateur_nom_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'annulee_at' => 'datetime',
            'montant_total' => 'decimal:2',
            'montant_ht' => 'decimal:2',
            'montant_taxe' => 'decimal:2',
            'taux_taxe' => 'decimal:2',
        ];
    }

    public function utilisateur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(FactureLigne::class);
    }

    public function paiementsMobileMoney(): HasMany
    {
        return $this->hasMany(MobileMoneyPayment::class);
    }
}
