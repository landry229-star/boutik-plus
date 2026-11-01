@extends('layouts.app')

@section('title', 'Informations de la boutique - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Identité commerciale</p>
            <h1 class="h3">Informations de la boutique</h1>
            <p class="bp-page-description">Ces coordonnées et le logo seront utilisés en haut des factures et des documents PDF.</p>
        </div>
    </div>

    <form action="{{ route('boutique-settings.update') }}" method="POST" enctype="multipart/form-data" class="bp-form-card">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-12">
                <label for="name" class="form-label">Nom de la boutique</label>
                <input id="name" type="text" name="name" value="{{ old('name', $settings->name) }}" class="form-control" maxlength="255" required>
                @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
                <label for="address" class="form-label">Adresse</label>
                <input id="address" type="text" name="address" value="{{ old('address', $settings->address) }}" class="form-control" maxlength="255">
                @error('address') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="phone" class="form-label">Téléphone</label>
                <input id="phone" type="tel" name="phone" value="{{ old('phone', $settings->phone) }}" class="form-control" maxlength="50">
                @error('phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="email" class="form-label">E-mail</label>
                <input id="email" type="email" name="email" value="{{ old('email', $settings->email) }}" class="form-control" maxlength="255">
                @error('email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="ifu" class="form-label">IFU</label>
                <input id="ifu" type="text" name="ifu" value="{{ old('ifu', $settings->ifu) }}" class="form-control" maxlength="100">
                @error('ifu') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="rccm" class="form-label">RCCM</label>
                <input id="rccm" type="text" name="rccm" value="{{ old('rccm', $settings->rccm) }}" class="form-control" maxlength="100">
                @error('rccm') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
                <label for="legal_notice" class="form-label">Mention complémentaire (facultatif)</label>
                <textarea id="legal_notice" name="legal_notice" class="form-control" rows="3" maxlength="2000">{{ old('legal_notice', $settings->legal_notice) }}</textarea>
                @error('legal_notice') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
                <label for="logo" class="form-label">Logo de la boutique</label>
                <input id="logo" type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                <div class="form-text">Formats acceptés : JPG ou PNG, taille maximale 2 Mo.</div>
                @error('logo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            @if ($logoUrl)
                <div class="col-12">
                    <p class="form-label">Logo actuel</p>
                    <img src="{{ $logoUrl }}" alt="Logo actuel de la boutique" class="img-thumbnail" style="max-height: 120px; max-width: 240px;">
                    <div class="form-check mt-2">
                        <input id="remove_logo" type="checkbox" name="remove_logo" value="1" class="form-check-input">
                        <label for="remove_logo" class="form-check-label">Supprimer le logo actuel</label>
                    </div>
                </div>
            @endif
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
            <button type="submit" class="btn btn-success"><i class="bi bi-check2"></i> Enregistrer les informations</button>
            <a href="{{ route('produits.index') }}" class="btn btn-outline-secondary">Retour</a>
        </div>
    </form>
@endsection
