<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'actif',
    ];

    protected $attributes = [
        'actif' => true,
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }

    public function estPatron(): bool
    {
        return $this->role === 'patron';
    }

    public function estEmploye(): bool
    {
        return $this->role === 'employe';
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function facturesAnnulees(): HasMany
    {
        return $this->hasMany(Facture::class, 'annulee_par_user_id');
    }

    public function achats(): HasMany
    {
        return $this->hasMany(Achat::class);
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    public function mouvementsCaisse(): HasMany
    {
        return $this->hasMany(MouvementCaisse::class);
    }
}
