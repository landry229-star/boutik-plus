<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $facture->numero }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222;">
    <h2 style="color: #1b7a3d;">Facture {{ $facture->numero }}</h2>

    <p>Bonjour {{ $facture->client_nom ?? '' }},</p>
    <p>Voici le détail de votre achat :</p>

    <table style="width: 100%; border-collapse: collapse;" cellpadding="8">
        <thead>
            <tr style="background-color: #1b7a3d; color: white;">
                <th align="left">Produit</th>
                <th align="left">Quantité</th>
                <th align="left">Prix unitaire</th>
                <th align="left">Sous-total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($facture->lignes as $ligne)
                <tr style="border-bottom: 1px solid #ddd;">
                    <td>{{ $ligne->produit->nom ?? '—' }}</td>
                    <td>{{ $ligne->quantite }}</td>
                    <td>{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }} FCFA</td>
                    <td>{{ number_format($ligne->sous_total, 0, ',', ' ') }} FCFA</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3 style="text-align: right; margin-top: 20px;">Total : {{ number_format($facture->montant_total, 0, ',', ' ') }} FCFA</h3>

    <p style="margin-top: 30px; color: #777; font-size: 0.9em;">Merci pour votre achat.</p>
</body>
</html>
