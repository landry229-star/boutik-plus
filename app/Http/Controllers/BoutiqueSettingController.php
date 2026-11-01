<?php

namespace App\Http\Controllers;

use App\Models\BoutiqueSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BoutiqueSettingController extends Controller
{
    public function edit(): View
    {
        $settings = BoutiqueSetting::current();
        $logoUrl = $settings->logo_path
            ? Storage::disk('public')->url($settings->logo_path)
            : null;

        return view('boutique-settings.edit', compact('settings', 'logoUrl'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'ifu' => ['nullable', 'string', 'max:100'],
            'rccm' => ['nullable', 'string', 'max:100'],
            'legal_notice' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png', 'extensions:jpg,jpeg,png', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $settings = BoutiqueSetting::current();
        $oldLogoPath = $settings->logo_path;
        $disk = Storage::disk('public');

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('branding', 'public');

            if (! $logoPath) {
                throw new RuntimeException('Le nouveau logo n’a pas pu être enregistré.');
            }

            $data['logo_path'] = $logoPath;
        } elseif ($request->boolean('remove_logo')) {
            $data['logo_path'] = null;
        }

        unset($data['logo'], $data['remove_logo']);

        $settings->update($data);

        if ($oldLogoPath && $oldLogoPath !== $settings->logo_path && ! $disk->delete($oldLogoPath)) {
            throw new RuntimeException('Les informations ont été enregistrées, mais l’ancien logo n’a pas pu être supprimé.');
        }

        return redirect()->route('boutique-settings.edit')->with('success', 'Les informations de la boutique ont été mises à jour.');
    }
}
