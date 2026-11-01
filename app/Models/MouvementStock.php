<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MouvementStock extends Model
{
    use HasFactory;

    protected $table = 'mouvements_stock';

    protected $fillable = [
        'produit_id',
        'user_id',
        'utilisateur_nom_snapshot',
        'facture_id',
        'achat_id',
        'type',
        'quantite',
        'motif',
    ];

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }
}
