<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>{{ $subject ?? 'Blockrium account update' }}</title>
</head>
<body style="margin:0;padding:0;background:#eaf0f3;color:#172533;font-family:Arial,Helvetica,sans-serif;line-height:1.55">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">{{ $previewText ?? $subject ?? 'An update for your Blockrium account.' }}</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#eaf0f3" style="width:100%;background:#eaf0f3;padding:32px 14px">
    <tr><td align="center">
      <table role="presentation" width="620" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:620px;background:#ffffff;border:1px solid #d7e1e6;border-radius:16px;overflow:hidden">
        <tr><td height="5" bgcolor="#08c8ab" style="height:5px;background:#08c8ab;font-size:0;line-height:0">&nbsp;</td></tr>
        <tr><td bgcolor="#101d2a" style="padding:22px 30px;background:#101d2a">
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
            <tr>
              <td width="46" height="46" valign="middle" style="width:46px;height:46px">
                <img src="{{ isset($message) && is_file(base_path('email-assets/blockrium-mark.png')) ? $message->embed(base_path('email-assets/blockrium-mark.png')) : ($logoUrl ?? '') }}" width="46" height="46" alt="Blockrium" style="display:block;width:46px;height:46px;border:0;border-radius:11px">
              </td>
              <td valign="middle" style="padding-left:13px;color:#ffffff;font-size:19px;font-weight:700;letter-spacing:.2px">BLOCKRIUM<br><span style="color:#9eb3c0;font-size:10px;font-weight:600;letter-spacing:1.5px">ACCOUNT SERVICES</span></td>
              <td align="right" valign="middle" style="color:#83dccc;font-size:10px;font-weight:700;letter-spacing:1.4px">ACCOUNT NOTICE</td>
            </tr>
          </table>
        </td></tr>
        <tr><td style="padding:38px 38px 34px">
          @if(!empty($isTest))<p style="margin:0 0 18px;color:#087f73;font-size:10px;font-weight:700;letter-spacing:1.5px">TEST MESSAGE</p>@endif
          <p style="margin:0 0 9px;color:#078b7b;font-size:11px;font-weight:700;letter-spacing:1.6px;text-transform:uppercase">A note about your account</p>
          <h1 style="margin:0 0 22px;color:#101d2a;font-size:30px;line-height:1.2;font-weight:700;letter-spacing:-.5px">{{ $heading ?? $subject ?? 'Your Blockrium account' }}</h1>
          <p style="margin:0 0 16px;color:#273b4b;font-size:16px">Hello {{ $name }},</p>
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0">
            <tr><td bgcolor="#f3f8f8" style="padding:18px 20px;border:1px solid #e0eceb;border-left:3px solid #08b99b;border-radius:8px;color:#364b57;font-size:15px;line-height:1.8">{!! nl2br(e($body)) !!}</td></tr>
          </table>
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:26px"><tr><td align="center" bgcolor="#078b7b" style="background:#078b7b;border-radius:9px">
            <a href="{{ $clickUrl ?? $actionUrl }}" style="display:inline-block;padding:14px 26px;border:1px solid #078b7b;border-radius:9px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700">{{ $buttonLabel ?? 'Open Blockrium' }}</a>
          </td></tr></table>
          <p style="margin:17px 0 0;color:#71818b;font-size:12px;line-height:1.7">Button not opening? <a href="{{ $clickUrl ?? $actionUrl }}" style="color:#087f73;text-decoration:underline;word-break:break-all">Open this secure link</a>.</p>
        </td></tr>
        <tr><td bgcolor="#f5f8fa" style="padding:23px 30px 25px;background:#f5f8fa;border-top:1px solid #e2e9ed">
          <p style="margin:0 0 9px;color:#172533;font-size:12px;font-weight:700;letter-spacing:.5px">BLOCKRIUM</p>
          <p style="margin:0 0 13px;font-size:12px;line-height:2.1">
            <a href="{{ $siteUrl }}" style="color:#087f73;text-decoration:none">Website</a>
            <span style="color:#b0bec5">&nbsp; | &nbsp;</span>
            <a href="{{ $appUrl }}" style="color:#087f73;text-decoration:none">Android app</a>
            @foreach(['Telegram' => $telegramUrl, 'Instagram' => $instagramUrl ?? null, 'Facebook' => $facebookUrl ?? null, 'X' => $xUrl ?? null, 'YouTube' => $youtubeUrl ?? null] as $network => $networkUrl)
              @if(!empty($networkUrl))<span style="color:#b0bec5">&nbsp; | &nbsp;</span><a href="{{ $networkUrl }}" style="color:#087f73;text-decoration:none">{{ $network }}</a>@endif
            @endforeach
          </p>
          @if(!empty($supportEmail))<p style="margin:0 0 12px;color:#71818b;font-size:12px">Support: <a href="mailto:{{ $supportEmail }}" style="color:#087f73;text-decoration:none">{{ $supportEmail }}</a></p>@endif
          @if(!empty($unsubscribeUrl))
            <p style="margin:0 0 9px;font-size:12px"><a href="{{ $unsubscribeUrl }}" style="color:#526672;text-decoration:underline">Unsubscribe from these email updates</a></p>
          @elseif(!empty($isTest))
            <p style="margin:0 0 9px;color:#71818b;font-size:12px">The live email includes the recipient's unsubscribe link.</p>
          @endif
          <p style="margin:0;color:#82919a;font-size:11px">You received this account notice from Blockrium.</p>
        </td></tr>
      </table>
      @if(!empty($openTrackingUrl))<img src="{{ $openTrackingUrl }}" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;opacity:0">@endif
    </td></tr>
  </table>
</body>
</html>
