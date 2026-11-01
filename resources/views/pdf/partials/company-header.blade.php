<table class="header">
    <tr>
        @if ($logoDataUri)
            <td class="logo-cell"><img class="logo" src="{{ $logoDataUri }}" alt="Logo de la boutique"></td>
        @endif
        <td>
            @if (filled($company['name'] ?? null))
                <div class="company-name">{{ $company['name'] }}</div>
            @endif
            <div class="company-details">
                @foreach (['address' => 'Adresse', 'phone' => 'Tél.', 'email' => 'E-mail'] as $field => $label)
                    @if (filled($company[$field] ?? null))
                        <div>{{ $label }} : {{ $company[$field] }}</div>
                    @endif
                @endforeach
                @if (filled($company['ifu'] ?? null))
                    <div>IFU : {{ $company['ifu'] }}</div>
                @endif
                @if (filled($company['rccm'] ?? null))
                    <div>RCCM : {{ $company['rccm'] }}</div>
                @endif
            </div>
        </td>
        <td>
            <div class="document-kind">{{ $documentTitle }}</div>
            <div class="document-number">N° {{ $documentNumber }}</div>
            <div class="document-number">{{ $documentDate }}</div>
        </td>
    </tr>
</table>
