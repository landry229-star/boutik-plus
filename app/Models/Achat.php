<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Achat extends Model
{
    use HasFactory;

    protected $fillable = [
        'fournisseur_id',
        'user_id',
        'montant_total',
        'date_achat',
        'notes',
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
        ];
    }

    public function fournisseur()
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function utilisateur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function annuleePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulee_par_user_id');
    }

    public function lignes()
    {
        return $this->hasMany(AchatLigne::class);
    }

    public function mouvementsCaisse()
    {
        return $this->hasMany(MouvementCaisse::class);
    }
}
