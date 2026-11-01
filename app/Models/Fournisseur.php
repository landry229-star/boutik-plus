<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fournisseur extends Model
{
    use HasFactory;

    protected $attributes = [
        'actif' => true,
    ];

    protected $fillable = ['nom', 'telephone', 'adresse', 'notes', 'actif'];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function achats()
    {
        return $this->hasMany(Achat::class);
    }
}
