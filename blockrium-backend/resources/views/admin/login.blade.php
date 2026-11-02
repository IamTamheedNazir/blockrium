<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Blockrium Admin Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" referrerpolicy="no-referrer">
  <style>
    :root {
      --bg: #f4f7fb;
      --surface: #ffffff;
      --text: #172033;
      --muted: #667085;
      --border: #dce5ef;
      --primary: #0f766e;
      --primary-dark: #115e59;
      --radius: 8px;
    }

    * {
      box-sizing: border-box;
    }

    body {
      min-height: 100vh;
      margin: 0;
      font-family: Inter, Arial, sans-serif;
      color: var(--text);
      background:
        radial-gradient(circle at 20% 20%, rgba(15, 118, 110, 0.16), transparent 28rem),
        radial-gradient(circle at 85% 18%, rgba(37, 99, 235, 0.13), transparent 26rem),
        var(--bg);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    .login-shell {
      width: min(980px, 100%);
      display: grid;
      grid-template-columns: 1.05fr 0.95fr;
      min-height: 560px;
      border: 1px solid var(--border);
      border-radius: 12px;
      overflow: hidden;
      background: var(--surface);
      box-shadow: 0 24px 70px rgba(15, 23, 42, 0.12);
    }

    .login-intro {
      padding: 48px;
      color: #fff;
      background:
        linear-gradient(145deg, rgba(15, 118, 110, 0.96), rgba(17, 24, 39, 0.98)),
        #111827;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 800;
      font-size: 18px;
    }

    .brand-mark {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 42px;
      height: 42px;
      border-radius: var(--radius);
      color: var(--primary);
      background: #fff;
      font-weight: 800;
    }

    .login-intro h1 {
      max-width: 420px;
      margin: 64px 0 16px;
      font-size: clamp(34px, 4vw, 48px);
      line-height: 1.04;
      letter-spacing: 0;
    }

    .login-intro p {
      max-width: 440px;
      margin: 0;
      color: rgba(255, 255, 255, 0.78);
      font-size: 15px;
      line-height: 1.7;
    }

    .login-points {
      display: grid;
      gap: 12px;
      margin-top: 36px;
    }

    .login-point {
      display: flex;
      align-items: center;
      gap: 10px;
      color: rgba(255, 255, 255, 0.86);
      font-size: 14px;
      font-weight: 600;
    }

    .login-point i {
      width: 28px;
      height: 28px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      color: #fff;
      background: rgba(255, 255, 255, 0.16);
    }

    .login-card {
      padding: 48px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .login-card h2 {
      margin: 0;
      color: var(--text);
      font-size: 28px;
      font-weight: 800;
    }

    .login-card > p {
      margin: 8px 0 28px;
      color: var(--muted);
      font-size: 14px;
    }

    .form-group {
      margin-bottom: 16px;
    }

    label {
      display: block;
      margin-bottom: 7px;
      color: var(--text);
      font-size: 13px;
      font-weight: 800;
    }

    .input-wrap {
      position: relative;
    }

    .input-wrap i {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
    }

    input {
      width: 100%;
      min-height: 46px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 10px 14px 10px 42px;
      color: var(--text);
      font: inherit;
      outline: none;
      transition: border-color 160ms ease, box-shadow 160ms ease;
    }

    input:focus {
      border-color: rgba(15, 118, 110, 0.48);
      box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.1);
    }

    .login-row {
      display: flex;
      justify-content: flex-end;
      margin: 2px 0 20px;
    }

    .login-row a {
      color: var(--primary);
      font-size: 13px;
      font-weight: 800;
      text-decoration: none;
    }

    button {
      width: 100%;
      min-height: 48px;
      border: 0;
      border-radius: var(--radius);
      color: #fff;
      background: var(--primary);
      font: inherit;
      font-size: 15px;
      font-weight: 800;
      cursor: pointer;
      transition: background 160ms ease, transform 160ms ease;
    }

    button:hover {
      background: var(--primary-dark);
      transform: translateY(-1px);
    }

    .secure-note {
      margin-top: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.5;
    }

    .secure-note i {
      color: var(--primary);
    }

    .alert {
      padding: 12px 14px;
      margin-bottom: 18px;
      border-radius: var(--radius);
      font-size: 14px;
    }

    @media (max-width: 820px) {
      .login-shell {
        grid-template-columns: 1fr;
      }

      .login-intro {
        padding: 32px;
      }

      .login-intro h1 {
        margin-top: 38px;
      }

      .login-card {
        padding: 32px;
      }
    }
  </style>
</head>
<body>
  <main class="login-shell">
    <section class="login-intro">
      <div>
        <div class="brand">
          <span class="brand-mark">B</span>
          <span>Blockrium Admin</span>
        </div>
        <h1>Operate the app with better control.</h1>
        <p>Manage users, rewards, app content, notifications, games, and wallet activity from a secured admin workspace.</p>
      </div>
      <div class="login-points">
        <div class="login-point"><i class="fa-solid fa-shield-halved"></i><span>Production-focused controls</span></div>
        <div class="login-point"><i class="fa-solid fa-wallet"></i><span>Wallet and reward visibility</span></div>
        <div class="login-point"><i class="fa-solid fa-bell"></i><span>Notification management</span></div>
      </div>
    </section>

    <section class="login-card">
      <h2>Sign in</h2>
      <p>Use your admin credentials to continue.</p>
      @include('flash/flash-message')
      <form action="{{ url('/dologin') }}" method="POST">
        @csrf
        <div class="form-group">
          <label for="admin-email">Email or username</label>
          <div class="input-wrap">
            <i class="fas fa-envelope"></i>
            <input type="text" name="user_email" id="admin-email" placeholder="admin@example.com" autocomplete="username" required>
          </div>
        </div>
        <div class="form-group">
          <label for="admin-password">Password</label>
          <div class="input-wrap">
            <i class="fas fa-lock"></i>
            <input type="password" name="user_password" id="admin-password" placeholder="Enter password" autocomplete="current-password" required>
          </div>
        </div>
        <div class="login-row">
          <a href="{{ url('/admin/forgotpassword') }}">Forgot password?</a>
        </div>
        <button type="submit">Sign in</button>
      </form>
      <div class="secure-note">
        <i class="fa-solid fa-circle-info"></i>
        <span>Keep this panel private. Rotate credentials after any backup or environment leak.</span>
      </div>
    </section>
  </main>
</body>
</html>
