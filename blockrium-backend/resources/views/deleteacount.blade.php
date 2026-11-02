<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Blockrium Account Deletion</title>
  <meta name="description" content="Request deletion of a Blockrium - Web3 Network app account and associated user data.">
  <style>
    :root {
      color-scheme: dark;
      --bg: #050b14;
      --panel: #0e2138;
      --panel-soft: #132b48;
      --text: #f4f8ff;
      --muted: #b7c5da;
      --line: #27415f;
      --brand: #31dcc5;
      --brand-2: #4d92ff;
      --danger: #ff7b8b;
      --success: #74e0aa;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: Arial, Helvetica, sans-serif;
      background: radial-gradient(circle at 20% 0%, rgba(49, 220, 197, 0.16), transparent 28%),
        linear-gradient(180deg, #050b14 0%, #071629 100%);
      color: var(--text);
      line-height: 1.55;
    }

    main {
      width: min(920px, calc(100% - 32px));
      margin: 0 auto;
      padding: 48px 0;
    }

    .card {
      border: 1px solid var(--line);
      border-radius: 20px;
      background: linear-gradient(180deg, rgba(19, 43, 72, 0.94), rgba(9, 19, 33, 0.96));
      box-shadow: 0 22px 80px rgba(0, 0, 0, 0.34);
      padding: clamp(24px, 4vw, 44px);
    }

    .eyebrow {
      color: var(--brand);
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      margin: 0 0 12px;
    }

    h1 {
      font-size: clamp(34px, 7vw, 58px);
      line-height: 1.05;
      margin: 0 0 16px;
      letter-spacing: 0;
    }

    h2 {
      font-size: 22px;
      margin: 32px 0 12px;
    }

    p {
      margin: 0 0 16px;
      color: var(--muted);
      font-size: 17px;
    }

    ul {
      margin: 0 0 16px;
      padding-left: 22px;
      color: var(--muted);
      font-size: 16px;
    }

    li {
      margin: 8px 0;
    }

    a {
      color: var(--brand);
      font-weight: 700;
    }

    form {
      display: grid;
      gap: 14px;
      margin: 24px 0;
      padding: 20px;
      border: 1px solid var(--line);
      border-radius: 16px;
      background: rgba(5, 11, 20, 0.36);
    }

    label {
      display: grid;
      gap: 8px;
      color: var(--muted);
      font-size: 14px;
      font-weight: 700;
    }

    input {
      width: 100%;
      min-height: 48px;
      border: 1px solid var(--line);
      border-radius: 12px;
      background: var(--panel-soft);
      color: var(--text);
      font: inherit;
      padding: 12px 14px;
    }

    button,
    .button {
      display: inline-flex;
      min-height: 54px;
      align-items: center;
      justify-content: center;
      border: 0;
      border-radius: 999px;
      padding: 14px 20px;
      text-align: center;
      text-decoration: none;
      color: #06101b;
      background: linear-gradient(135deg, var(--brand), var(--brand-2));
      font: inherit;
      font-weight: 800;
      cursor: pointer;
    }

    .message {
      border-radius: 14px;
      padding: 14px 16px;
      margin: 18px 0;
      color: var(--text);
    }

    .message.success {
      border: 1px solid rgba(116, 224, 170, 0.42);
      background: rgba(116, 224, 170, 0.14);
    }

    .message.error {
      border: 1px solid rgba(255, 123, 139, 0.42);
      background: rgba(255, 123, 139, 0.14);
    }

    .small {
      font-size: 14px;
    }

    @media (max-width: 640px) {
      main {
        width: min(100% - 24px, 920px);
        padding: 24px 0;
      }
    }
  </style>
</head>
<body>
  <main>
    <section class="card">
      <p class="eyebrow">Blockrium - Web3 Network</p>
      <h1>Account deletion request</h1>
      <p>
        Use this page to request deletion of your Blockrium app account and associated user data.
        Verified requests are processed within 3 working days.
      </p>

      @if(session('success'))
        <div class="message success">{{ session('success') }}</div>
      @endif

      @if(session('error'))
        <div class="message error">{{ session('error') }}</div>
      @endif

      @if($errors->any())
        <div class="message error">
          @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
          @endforeach
        </div>
      @endif

      <form method="post" action="{{ route('delete-account-request') }}">
        @csrf
        <label>
          Registered email
          <input type="email" name="email" autocomplete="email" required>
        </label>
        <label>
          Account password
          <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit">Submit deletion request</button>
      </form>

      <p>
        If the form is unavailable, email <a href="mailto:support@blockrium.com">support@blockrium.com</a>
        from your registered email address. Include your Blockrium user ID or UID if available.
      </p>

      <h2>Data deleted</h2>
      <ul>
        <li>Account profile details such as name, email, username, UID, phone number, and referral code.</li>
        <li>App wallet reward records, mining reward records, daily reward and task activity, referral activity, and notification tokens linked to the account.</li>
        <li>Marketing or campaign preferences linked to the app account.</li>
      </ul>

      <h2>Data retained when required</h2>
      <ul>
        <li>Records required for security, fraud prevention, dispute handling, tax, legal, or regulatory compliance may be retained only as long as necessary.</li>
        <li>Server backups may retain deleted data for up to 30 days before normal backup rotation removes it.</li>
        <li>Public blockchain transactions cannot be deleted from a blockchain network, but Blockrium deletes the app account data it controls.</li>
      </ul>

      <p class="small">
        Last updated: 3 June 2026. Contact: <a href="mailto:support@blockrium.com">support@blockrium.com</a>.
      </p>
    </section>
  </main>
</body>
</html>
