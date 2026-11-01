<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileMoneyPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'facture_id',
        'provider',
        'status',
        'reference',
        'external_reference',
        'phone',
        'montant',
        'devise',
        'operation',
        'payload',
        'response',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'payload' => 'array',
            'response' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }
}
