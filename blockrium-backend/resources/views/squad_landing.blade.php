<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Join {{ $squad['name'] }} on Blockrium</title>
    <meta name="description" content="Join this Blockrium squad with invite code {{ $squad['invite_code'] }}.">
    <meta property="og:title" content="Join {{ $squad['name'] }} on Blockrium">
    <meta property="og:description" content="Use invite code {{ $squad['invite_code'] }} and help unlock weekly activity chests.">
    <meta property="og:url" content="{{ $squad['share_url'] }}">
    <meta name="twitter:card" content="summary">
    <style>
        :root { color-scheme: dark; --bg: #07111f; --panel: #102039; --line: #213555; --text: #f7fbff; --muted: #9fb0ca; --brand: #28dac4; --blue: #4d8dff; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; font-family: Arial, sans-serif; background: radial-gradient(circle at 20% 10%, #17365f 0, #07111f 45%, #040812 100%); color: var(--text); display: grid; place-items: center; padding: 24px; }
        .invite { width: min(560px, 100%); border: 1px solid var(--line); background: rgba(16, 32, 57, .92); border-radius: 18px; padding: 28px; box-shadow: 0 24px 80px rgba(0, 0, 0, .35); }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
        .mark { width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, var(--brand), var(--blue)); display: grid; place-items: center; font-weight: 800; color: #06111f; }
        h1 { margin: 0 0 10px; font-size: clamp(30px, 8vw, 48px); line-height: 1.03; letter-spacing: 0; }
        p { color: var(--muted); font-size: 18px; line-height: 1.5; }
        .code { margin: 24px 0; padding: 18px; border-radius: 14px; border: 1px dashed rgba(40, 218, 196, .65); background: rgba(40, 218, 196, .09); }
        .code span { display: block; color: var(--muted); font-size: 13px; text-transform: uppercase; letter-spacing: .08em; }
        .code strong { display: block; margin-top: 6px; font-size: 32px; letter-spacing: .08em; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 24px 0; }
        .stat { border: 1px solid var(--line); border-radius: 12px; padding: 14px; }
        .stat strong { display: block; font-size: 22px; }
        .stat span { color: var(--muted); font-size: 13px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { border: 0; border-radius: 999px; padding: 14px 20px; font-weight: 800; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, var(--brand), var(--blue)); color: #06111f; }
        .btn-muted { background: #182944; color: var(--text); border: 1px solid var(--line); }
        .note { margin-top: 22px; font-size: 13px; color: var(--muted); }
        @media (max-width: 520px) { .stats { grid-template-columns: 1fr; } .invite { padding: 22px; } }
    </style>
</head>
<body>
    <main class="invite">
        <div class="brand"><div class="mark">B</div><strong>Blockrium</strong></div>
        <h1>Join {{ $squad['name'] }}</h1>
        <p>{{ $poster['subtitle'] }}</p>
        <div class="code">
            <span>Invite code</span>
            <strong>{{ $squad['invite_code'] }}</strong>
        </div>
        <div class="stats">
            <div class="stat"><strong>{{ number_format($squad['member_count']) }}/{{ $squad['is_unlimited'] ? 'Unlimited' : number_format($squad['max_members']) }}</strong><span>Members</span></div>
            <div class="stat"><strong>{{ number_format($squad['weekly_points']) }}</strong><span>Weekly points</span></div>
            <div class="stat"><strong>{{ $squad['community_boost_percent'] }}%</strong><span>Activity boost</span></div>
        </div>
        <div class="actions">
            <a class="btn btn-primary" href="https://play.google.com/store/apps/details?id=com.blockrium">Open Play Store</a>
            <a class="btn btn-muted" href="{{ $squad['share_url'] }}">Copy invite link</a>
        </div>
        <p class="note">{{ $poster['safe_note'] }}</p>
    </main>
</body>
</html>
