MADINA IMPORT

{{ $heading }}
{{ $bodyText }}
@if($code !== null)
Votre code de confirmation est : {{ substr($code,0,3) }} {{ substr($code,3) }}
Ce code est valable pendant 10 minutes.
Ne communiquez jamais ce code à une autre personne.
Si vous n’avez pas créé de compte sur Madina Import, vous pouvez ignorer cet e-mail.
@endif
@if($orderNumber)
Commande : {{ $orderNumber }}
@endif
L’équipe Madina Import
Le monde plus proche de vous.
{{ config('app.url') }}
