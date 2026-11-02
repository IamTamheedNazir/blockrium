<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Email preferences</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f6fb;color:#142033;padding:32px">
  <main style="max-width:560px;margin:32px auto;background:#fff;border:1px solid #e3e8f0;border-radius:12px;padding:28px">
    <h1 style="font-size:22px">Blockrium email preferences</h1>
    @if(!empty($completed))
      <p>This account has been opted out of future admin batch emails.</p>
    @else
      <p>Confirm that you want to stop future admin batch emails for this Blockrium account.</p>
      <form method="post" action="{{ request()->fullUrl() }}">@csrf
        <button type="submit" style="padding:11px 16px;border:0;border-radius:7px;background:#087f73;color:#fff;font-weight:700;cursor:pointer">Opt out of batch emails</button>
      </form>
    @endif
  </main>
</body></html>
