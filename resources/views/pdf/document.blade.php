<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $document->number }}</title>
    <style>
        @page { margin: 30px 38px 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #202124; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.5; }
        .header { width: 100%; border-bottom: 3px solid #2f2f2f; padding-bottom: 16px; }
        .header td { vertical-align: middle; }
        .logo { width: 88px; height: auto; }
        .brand { font-size: 18px; font-weight: bold; letter-spacing: .4px; }
        .tagline { color: #777; font-size: 9px; }
        .document-title { color: #bd2433; font-size: 25px; font-weight: bold; letter-spacing: 2px; text-align: right; }
        .number { color: #555; font-size: 11px; text-align: right; }
        .meta { width: 100%; margin: 24px 0; }
        .meta td { width: 50%; vertical-align: top; }
        .box { border: 1px solid #e4e4df; border-radius: 8px; padding: 14px 16px; }
        .box-right { margin-left: 12px; }
        .label { color: #9a9a94; font-size: 8px; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase; }
        .client { margin: 5px 0 2px; font-size: 14px; font-weight: bold; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .lines th { background: #2f2f2f; color: #fff; padding: 10px 9px; font-size: 9px; letter-spacing: .6px; text-align: left; text-transform: uppercase; }
        .lines td { border-bottom: 1px solid #ecece8; padding: 11px 9px; vertical-align: top; }
        .lines .qty { width: 70px; text-align: center; }
        .lines .amount { width: 135px; text-align: right; white-space: nowrap; }
        .product-photo { width: 54px; max-height: 54px; object-fit: contain; vertical-align: middle; margin-right: 8px; }
        .description { color: #777; font-size: 9px; margin-top: 3px; }
        .totals { width: 310px; margin: 18px 0 0 auto; border-collapse: collapse; }
        .totals td { padding: 6px 10px; }
        .totals .value { text-align: right; white-space: nowrap; }
        .totals .grand td { border-top: 2px solid #2f2f2f; background: #fffdee; font-size: 14px; font-weight: bold; padding-top: 10px; }
        .status { display: inline-block; margin-top: 5px; padding: 4px 9px; border-radius: 10px; background: #fff7b8; color: #665f00; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .notes { margin-top: 24px; padding: 13px 15px; border-left: 3px solid #fcf108; background: #fafaf7; }
        .products { margin-top: 20px; }
        .products-title { margin-bottom: 7px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .8px; }
        .footer { position: fixed; bottom: -18px; left: 0; right: 0; border-top: 1px solid #e5e5e0; padding-top: 8px; color: #888; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width:110px">@if($logoData)<img class="logo" src="{{ $logoData }}" alt="Madina Import">@endif</td>
            <td><div class="brand">{{ $company['name'] }}</div><div class="tagline">{{ $company['address'] }}<br>Tél. : {{ $company['contact'] }} · WhatsApp : {{ $company['whatsapp'] }}<br>{{ $company['email'] }}</div></td>
            <td><div class="document-title">{{ $title }}</div><div class="number">N° {{ $document->number }}</div></td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td><div class="box"><div class="label">Destinataire</div><div class="client">{{ $document->client_name }}</div><div>{{ $document->client_number }}</div><div>{{ $document->client_contact }}</div>@if($document->client_address)<div>{{ $document->client_address }}</div>@endif</div></td>
            <td><div class="box box-right"><div class="label">Informations du document</div><div style="margin-top:5px"><strong>Date :</strong> {{ \Carbon\Carbon::parse($module==='devis'?($document->quote_date?:$document->created_at):$document->issued_at)->format('d/m/Y') }}</div>@if($module==='devis')<div><strong>Valide jusqu’au :</strong> {{ \Carbon\Carbon::parse($document->valid_until)->format('d/m/Y') }}</div><div><strong>Mode d’envoi :</strong> {{ ucfirst($document->shipping_mode?:'Non défini') }}</div>@else<div><strong>Commande :</strong> {{ $document->order_number }}</div>@endif<div class="status">{{ str_replace('_',' ',$document->status) }}</div></div></td>
        </tr>
    </table>

    <table class="lines">
        <thead><tr><th>Désignation</th><th class="qty">Quantité</th><th class="amount">Prix unitaire</th><th class="amount">Montant</th></tr></thead>
        <tbody>
        @foreach($items as $item)
            <tr>
                <td>@if($module==='devis' && $item->photo_data)<img class="product-photo" src="{{ $item->photo_data }}" alt="">@endif<strong>{{ $module==='devis'?$item->name:$item->label }}</strong>@if($module==='devis' && $item->specifications)<div class="description">{{ $item->specifications }}</div>@endif</td>
                @php($quantity=(float)($item->quantity??1))
                @php($amount=(float)($module==='devis'?$item->total:$item->amount))
                @php($unitPrice=isset($item->unit_price)?(float)$item->unit_price:($quantity>0?$amount/$quantity:0))
                <td class="qty">{{ rtrim(rtrim(number_format($quantity,3,',',' '),'0'),',') }}</td>
                <td class="amount">{{ number_format($unitPrice,0,',','.') }} Ar</td>
                <td class="amount">{{ number_format($amount,0,',','.') }} Ar</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if($module==='factures' && $document->products->isNotEmpty())
        <div class="products"><div class="products-title">Produits / articles de la commande</div>@foreach($document->products as $product)<div>• {{ $product->name }} — Qté {{ rtrim(rtrim(number_format((float)$product->quantity,3,',',' '),'0'),',') }}@if($product->specifications) <span class="description">({{ $product->specifications }})</span>@endif</div>@endforeach</div>
    @endif

    <table class="totals">
        @if($module==='factures')<tr><td>Déjà payé</td><td class="value">{{ number_format((float)$document->paid_amount,0,',','.') }} Ar</td></tr><tr><td>Reste à payer</td><td class="value">{{ number_format((float)$document->balance_due,0,',','.') }} Ar</td></tr>@endif
        <tr class="grand"><td>Total</td><td class="value">{{ number_format((float)($module==='devis'?$document->total:$document->subtotal),0,',','.') }} Ar</td></tr>
    </table>

    @if($module==='devis')<div class="notes"><strong>Informations et conditions</strong>@if($document->shipping_delay)<br><strong>Délai d’expédition :</strong> {{ $document->shipping_delay }}@endif @if($document->bank_details)<br><strong>Informations bancaires :</strong> {!! nl2br(e($document->bank_details)) !!}@endif @if($document->payment_terms)<br><strong>Conditions de paiement :</strong> {!! nl2br(e($document->payment_terms)) !!}@endif @if($document->warranty)<br><strong>Garantie :</strong> {!! nl2br(e($document->warranty)) !!}@endif @if($document->notes)<br><strong>Note / remarque :</strong> {!! nl2br(e($document->notes)) !!}@endif</div>@endif
    <div class="footer">{{ $company['name'] }} · Document généré le {{ now()->format('d/m/Y à H:i') }} · {{ $document->number }}</div>
</body>
</html>
