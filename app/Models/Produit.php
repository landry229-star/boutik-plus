<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produit extends Model
{
    use HasFactory;

    protected $attributes = [
        'actif' => true,
    ];

    protected $fillable = [
        'nom', 'categorie', 'code_barre', 'prix_achat',
        'prix_vente', 'quantite_stock', 'seuil_alerte', 'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    public function factureLignes(): HasMany
    {
        return $this->hasMany(FactureLigne::class);
    }

    public function achatLignes(): HasMany
    {
        return $this->hasMany(AchatLigne::class);
    }

    public function estEnAlerte(): bool
    {
        return $this->quantite_stock <= $this->seuil_alerte;
    }
}
