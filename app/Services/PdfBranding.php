<?php

namespace App\Services;

use App\Models\BoutiqueSetting;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;

class PdfBranding
{
    /**
     * @return array{company: array<string, mixed>, currency: string, logoDataUri: string|null}
     */
    public function viewData(): array
    {
        $settings = BoutiqueSetting::current();

        return [
            'company' => $this->companyData($settings),
            'currency' => config('boutik.currency'),
            'logoDataUri' => $this->logoDataUri($settings->logo_path),
        ];
    }

    /**
     * @return array{name: string, address: string|null, phone: string|null, email: string|null, ifu: string|null, rccm: string|null, logo_path: string|null, legal_notice: string|null}
     */
    public function companyData(?BoutiqueSetting $settings = null): array
    {
        $settings ??= BoutiqueSetting::current();

        return [
            'name' => $settings->name,
            'address' => $settings->address,
            'phone' => $settings->phone,
            'email' => $settings->email,
            'ifu' => $settings->ifu,
            'rccm' => $settings->rccm,
            'logo_path' => $settings->logo_path,
            'legal_notice' => $settings->legal_notice,
        ];
    }

    private function logoDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            throw new RuntimeException('Le logo enregistré dans les informations de la boutique est introuvable sur le disque public.');
        }

        if ($disk->size($path) > 2 * 1024 * 1024) {
            throw new LogicException('Le logo enregistré ne doit pas dépasser 2 Mo.');
        }

        $mimeType = $disk->mimeType($path);

        if (! in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            throw new LogicException('Le logo enregistré doit être une image JPEG ou PNG.');
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($disk->get($path));
    }
}
