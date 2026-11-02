<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Blockrium account verification</title></head>
<body style="margin:0;background:#f3f6fb;color:#142033;font-family:Arial,sans-serif;line-height:1.6">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f6fb;padding:24px 12px">
    <tr><td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border:1px solid #e3e8f0;border-radius:12px">
        <tr><td style="padding:24px 28px;background:#111b2e;color:#fff;border-radius:12px 12px 0 0;font-size:20px;font-weight:700">Blockrium</td></tr>
        <tr><td style="padding:28px">
          <h1 style="margin:0 0 16px;font-size:22px;color:#142033">Complete your identity verification</h1>
          <p>Hello {{ $name }},</p>
          <div>{!! nl2br(e($body)) !!}</div>
          <p style="margin:24px 0"><a href="{{ $actionUrl }}" style="display:inline-block;padding:12px 18px;background:#087f73;color:#fff;text-decoration:none;border-radius:8px;font-weight:700">Continue in Blockrium</a></p>
          <p style="font-size:13px;color:#56647a">For your security, complete verification through Blockrium. Do not send identity documents by replying to this email.</p>
        </td></tr>
        <tr><td style="padding:18px 28px;border-top:1px solid #e3e8f0;color:#657187;font-size:12px">You received this account verification notice because your Blockrium account has not completed KYC. <a href="{{ $unsubscribeUrl }}" style="color:#087f73">Manage bulk email preferences</a>.</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
