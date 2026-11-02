{{ $subject ?? 'Blockrium account update' }}

Hello {{ $name }},

{{ $body }}

{{ $buttonLabel ?? 'Open Blockrium' }}: {{ $clickUrl ?? $actionUrl }}

Blockrium links
Website: {{ $siteUrl }}
Android app: {{ $appUrl }}
@foreach(['Telegram' => $telegramUrl, 'Instagram' => $instagramUrl ?? null, 'Facebook' => $facebookUrl ?? null, 'X' => $xUrl ?? null, 'YouTube' => $youtubeUrl ?? null] as $network => $networkUrl)
@if(!empty($networkUrl)){{ $network }}: {{ $networkUrl }}
@endif
@endforeach
@if(!empty($supportEmail))Support: {{ $supportEmail }}
@endif
@if(!empty($unsubscribeUrl))Unsubscribe from these email updates: {{ $unsubscribeUrl }}
@elseif(!empty($isTest))The live email includes the recipient's unsubscribe link.
@endif

This message relates to your Blockrium account.
