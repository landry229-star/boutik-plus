<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoutiqueSetting extends Model
{
    protected $fillable = [
        'key',
        'name',
        'address',
        'phone',
        'email',
        'ifu',
        'rccm',
        'logo_path',
        'legal_notice',
    ];

    public static function current(): self
    {
        $company = config('boutik.company');
        $legacyIdentifierLabel = mb_strtolower((string) ($company['identifier_label'] ?? ''));
        $legacyIdentifier = $company['identifier'] ?? null;

        return static::query()->firstOrCreate(
            ['key' => 'boutique'],
            [
                'name' => $company['name'] ?: config('app.name'),
                'address' => $company['address'],
                'phone' => $company['phone'],
                'email' => $company['email'],
                'ifu' => $company['ifu'] ?: (
                    str_contains($legacyIdentifierLabel, 'ifu') || str_contains($legacyIdentifierLabel, 'fiscal')
                        ? $legacyIdentifier
                        : null
                ),
                'rccm' => $company['rccm'] ?: (str_contains($legacyIdentifierLabel, 'rccm') ? $legacyIdentifier : null),
                'logo_path' => $company['logo_path'],
                'legal_notice' => $company['legal_notice'],
            ],
        );
    }
}
