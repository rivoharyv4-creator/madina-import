<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $payment->receipt_number }}</title>
    <style>
        @page { margin: 34px 42px; }
        * { box-sizing: border-box; }
        body { margin:0; color:#202124; font-family:DejaVu Sans,sans-serif; font-size:11px; line-height:1.55; }
        .header { width:100%; border-bottom:3px solid #2f2f2f; padding-bottom:15px; }
        .header td { vertical-align:middle; }
        .logo { width:82px; height:auto; }
        .brand { font-size:18px; font-weight:bold; }
        .company { color:#777; font-size:8.5px; }
        .title { color:#bd2433; font-size:23px; font-weight:bold; letter-spacing:1.5px; text-align:right; }
        .number { color:#666; text-align:right; }
        .status { display:inline-block; margin-top:6px; padding:4px 10px; border-radius:12px; background:#fff7b8; color:#665f00; font-size:9px; font-weight:bold; text-transform:uppercase; }
        .intro { margin:25px 0 15px; color:#666; }
        .grid { width:100%; border-spacing:0 10px; }
        .grid td { width:50%; vertical-align:top; }
        .box { border:1px solid #e4e4df; border-radius:9px; padding:14px 16px; }
        .right { margin-left:12px; }
        .label { color:#999; font-size:8px; font-weight:bold; letter-spacing:1px; text-transform:uppercase; }
        .value { margin-top:4px; font-size:13px; font-weight:bold; }
        .amount { margin:24px 0; padding:22px; border-left:5px solid #fcf108; background:#2f2f2f; color:#fff; }
        .amount .label { color:#fff; opacity:.55; }
        .amount strong { display:block; margin-top:4px; color:#fcf108; font-size:27px; }
        .allocation { margin-top:16px; padding:14px 16px; border-radius:8px; background:#fafaf7; }
        .allocation strong { color:#bd2433; }
        .notes { margin-top:18px; padding:12px 15px; border:1px solid #ecece8; border-radius:8px; color:#666; }
        .signatures { width:100%; margin-top:48px; }
        .signatures td { width:50%; padding:0 22px; text-align:center; vertical-align:bottom; }
        .line { margin-top:48px; border-top:1px solid #777; padding-top:6px; color:#777; font-size:9px; }
        .footer { position:fixed; bottom:-17px; left:0; right:0; border-top:1px solid #e5e5e0; padding-top:7px; color:#888; font-size:8px; text-align:center; }
    </style>
</head>
<body>
<table class="header"><tr>
    <td style="width:100px">@if($logoData)<img class="logo" src="{{ $logoData }}" alt="">@endif</td>
    <td><div class="brand">{{ $company['name'] }}</div><div class="company">{{ $company['address'] }}<br>Tél. : {{ $company['contact'] }} · WhatsApp : {{ $company['whatsapp'] }}<br>{{ $company['email'] }}<br>NIF : {{ $company['nif'] }} · RCS : {{ $company['rcs'] }}<br>STAT : {{ $company['stat'] }}</div></td>
    <td><div class="title">REÇU DE PAIEMENT</div><div class="number">{{ $payment->receipt_number }}</div><div style="text-align:right"><span class="status">Paiement validé</span></div></td>
</tr></table>

<p class="intro">Nous reconnaissons avoir reçu le paiement décrit ci-dessous.</p>

<table class="grid"><tr>
    <td><div class="box"><div class="label">Reçu de</div><div class="value">{{ $payment->client_name }}</div><div>{{ $payment->client_number }}</div><div>{{ $payment->client_contact }}</div>@if($payment->client_address)<div>{{ $payment->client_address }}</div>@endif</div></td>
    <td><div class="box right"><div class="label">Informations du paiement</div><div class="value">{{ \Carbon\Carbon::parse($payment->paid_at)->format('d/m/Y') }}</div><div>Mode : {{ $payment->method }}</div><div>Motif : {{ $payment->type_label }}</div>@if($payment->reference)<div>Référence : {{ $payment->reference }}</div>@endif</div></td>
</tr></table>

<div class="amount"><div class="label">Montant reçu</div><strong>{{ number_format((float)$payment->amount,0,',','.') }} Ar</strong></div>

@if($payment->order_number || $payment->invoice_number)
<div class="allocation">
    Montant affecté : <strong>{{ number_format((float)$payment->allocated_amount,0,',','.') }} Ar</strong>
    @if($payment->order_number)<br>Commande : {{ $payment->order_number }}@endif
    @if($payment->invoice_number)<br>Facture : {{ $payment->invoice_number }}@endif
    @if($payment->credit_amount>0)<br>Crédit ajouté au compte client : <strong>{{ number_format($payment->credit_amount,0,',','.') }} Ar</strong>@endif
</div>
@endif

@if($payment->notes)<div class="notes"><div class="label">Note</div>{{ $payment->notes }}</div>@endif

<table class="signatures"><tr><td><div class="line">Signature du client</div></td><td><div class="line">Cachet et signature Madina Import</div></td></tr></table>
<div class="footer">{{ $company['name'] }} · Reçu généré le {{ now($company['timezone'])->format('d/m/Y à H:i') }} · {{ $payment->receipt_number }}</div>
</body>
</html>
