<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MouvementCaisse extends Model
{
    use HasFactory;

    protected $table = 'mouvements_caisse';

    protected $fillable = [
        'date_jour',
        'moyen_paiement',
        'user_id',
        'facture_id',
        'achat_id',
        'mouvement_origine_id',
        'utilisateur_nom_snapshot',
        'type',
        'montant',
        'description',
    ];

    public function annulation(): HasOne
    {
        return $this->hasOne(self::class, 'mouvement_origine_id');
    }

    public function mouvementOrigine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mouvement_origine_id');
    }
}
