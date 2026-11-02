import { FormEvent, useEffect, useMemo, useState } from 'react';
import { acceptAuthResponse, api, get, post, readData, session } from './api';

type Area = 'home' | 'mining' | 'wallet' | 'community' | 'profile' | 'verified' | 'promo' | 'support' | 'tasks' | 'campaigns' | 'squads' | 'notifications';
type Notice = { kind: 'error' | 'success' | 'info'; text: string };

const nav: { id: Area; title: string; icon: string }[] = [
  { id: 'home', title: 'Home', icon: '⌂' }, { id: 'mining', title: 'Mining', icon: '⛏' },
  { id: 'wallet', title: 'Wallet', icon: '▣' }, { id: 'community', title: 'Community', icon: '♧' },
  { id: 'profile', title: 'Profile', icon: '◉' },
];
const asList = (value: any): any[] => Array.isArray(value) ? value : ['data','items','tasks','campaigns','notifications','squads','members','missions','redemptions'].map((key) => value?.[key]).find(Array.isArray) || [];
const money = (value: unknown) => Number(value ?? 0).toLocaleString(undefined, { maximumFractionDigits: 4 });
const textOf = (value: any, ...keys: string[]) => keys.map((key) => value?.[key]).find((item) => item !== undefined && item !== null && item !== '') ?? '—';

export default function App() {
  const [loggedIn, setLoggedIn] = useState(Boolean(session.token()));
  const [authMode, setAuthMode] = useState<'login' | 'signup'>('login');
  const [area, setArea] = useState<Area>('home');
  const [notice, setNotice] = useState<Notice | null>(null);
  const [busy, setBusy] = useState(false);
  const [payload, setPayload] = useState<Record<string, any>>({});
  const [installPrompt, setInstallPrompt] = useState<any>(null);
  const [iosHelp, setIosHelp] = useState(false);

  useEffect(() => {
    const onPrompt = (event: Event) => { event.preventDefault(); setInstallPrompt(event); };
    window.addEventListener('beforeinstallprompt', onPrompt);
    return () => window.removeEventListener('beforeinstallprompt', onPrompt);
  }, []);

  useEffect(() => {
    if (loggedIn) void loadArea(area);
  }, [area, loggedIn]);

  async function loadArea(target: Area) {
    const routes: Record<Area, string[]> = {
      home: ['auth/profile', 'auth/wallet', 'auth/get_mining', 'auth/verification/status', 'auth/get-task', 'banners'],
      mining: ['auth/get_mining', 'auth/wallet', 'auth/my-boost', 'auth/boost-plans', 'auth/get-task'],
      wallet: ['auth/wallet', 'auth/verification/status', 'auth/brc/transfers?limit=20'],
      community: ['top-miners', 'auth/my-network?filter=all', 'auth/referral-missions'],
      profile: ['auth/profile', 'auth/count-notification', 'auth/verification/status'],
      verified: ['auth/verification/status'],
      promo: ['auth/partner-promo-codes/redemptions'],
      support: ['auth/verification/status'],
      tasks: ['auth/get-task', 'auth/daily-missions', 'auth/onboarding-quest'],
      campaigns: ['auth/partner-campaigns'],
      squads: ['auth/squads/app-home', 'auth/squads/discover'],
      notifications: ['auth/all-notification'],
    };
    const pairs = await Promise.all(routes[target].map(async (route) => [route, await get(route)] as const));
    setPayload((old) => ({ ...old, ...Object.fromEntries(pairs.map(([route, response]) => [route, response])) }));
  }

  async function authenticate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setNotice(null);
    const form = new FormData(event.currentTarget);
    const email = String(form.get('email') || '').trim().toLowerCase();
    const password = String(form.get('password') || '');
    const input = authMode === 'login'
      ? { email, password }
      : { username: String(form.get('username') || '').trim(), name: String(form.get('username') || '').trim(), email, phone_number: String(form.get('phone') || '').trim(), password, password_confirmation: password, country: String(form.get('country') || '').trim() };
    const result = await post(authMode === 'login' ? 'auth/login' : 'auth/register', input);
    setBusy(false);
    if (!result.success || !acceptAuthResponse(result)) {
      setNotice({ kind: 'error', text: result.error || 'The server did not return a login session. Please check your details and retry.' }); return;
    }
    setLoggedIn(true); setArea('home'); setNotice({ kind: 'success', text: authMode === 'login' ? 'Welcome back.' : 'Your Blockrium account is ready.' });
  }

  async function startMining() {
    setBusy(true); const result = await post('auth/click-button', {}); setBusy(false);
    setNotice({ kind: result.success ? 'success' : 'error', text: result.success ? 'Mining request sent. Your active session will be confirmed by Blockrium.' : result.error || 'Could not start mining.' });
    await loadArea('mining');
  }

  async function startKyc() {
    const shouldRetry = Boolean(verification?.kyc_retry_available || verification?.kyc_status === 'rejected');
    setBusy(true); const result = await post(shouldRetry ? 'auth/verification/retry' : 'auth/verification/start', {}); setBusy(false);
    const body: any = readData(result);
    const url = body?.next_action_url || body?.verification_url || body?.url || body?.session_url;
    if (result.success && url) window.location.assign(url);
    else setNotice({ kind: result.success ? 'info' : 'error', text: result.success ? body?.message || 'Verification session created. Refresh this page to continue.' : result.error || 'Could not start identity verification.' });
  }

  async function redeem(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); const form = new FormData(event.currentTarget); const code = String(form.get('code') || '').trim().toUpperCase();
    setBusy(true); const result = await post('auth/partner-promo-codes/redeem', { code, request_key: crypto.randomUUID() }); setBusy(false);
    const body: any = readData(result);
    setNotice({ kind: result.success ? 'success' : 'error', text: result.success ? body?.message || 'Promo code was accepted. Check your rewards wallet.' : result.error || 'Promo code could not be redeemed.' });
    if (result.success) { event.currentTarget.reset(); await loadArea('promo'); }
  }

  async function submitPaymentSupport(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); const form = new FormData(event.currentTarget); setBusy(true);
    const result = await api('auth/verification/payment/support', { method: 'POST', body: form }); setBusy(false);
    setNotice({ kind: result.success ? 'success' : 'error', text: result.success ? 'Your payment report was sent to Blockrium Support.' : result.error || 'Could not send your report.' });
    if (result.success) event.currentTarget.reset();
  }

  async function sendTransfer(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); const form = new FormData(event.currentTarget);
    const amount = String(form.get('amount') || '').trim(); const recipient = String(form.get('recipient') || '').trim();
    if (!recipient || !/^\d+(\.\d{1,8})?$/.test(amount) || Number(amount) <= 0) { setNotice({ kind: 'error', text: 'Enter a recipient username and a valid BRC amount.' }); return; }
    if (!window.confirm(`Send ${amount} BRC to ${recipient}? A 2% fee is charged on top for eligible verified members.`)) return;
    setBusy(true); const result = await post('auth/brc/transfers', { recipient_username: recipient, amount, idempotency_key: crypto.randomUUID() }); setBusy(false);
    setNotice({ kind: result.success ? 'success' : 'error', text: result.success ? 'Transfer request recorded.' : result.error || 'Transfer failed.' });
    if (result.success) { event.currentTarget.reset(); await loadArea('wallet'); }
  }

  async function sendSupportReply(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); const form = new FormData(event.currentTarget); const message = String(form.get('message') || '').trim();
    const caseId = verification?.payment_support?.latest?.id;
    if (!caseId || message.length < 2) { setNotice({ kind: 'error', text: 'A payment support case and message are required.' }); return; }
    setBusy(true); const result = await post(`auth/verification/payment/support/${caseId}/messages`, { message }); setBusy(false);
    setNotice({ kind: result.success ? 'success' : 'error', text: result.success ? 'Your reply was sent to Blockrium Support.' : result.error || 'Could not send your reply.' });
    if (result.success) { event.currentTarget.reset(); await loadArea('verified'); }
  }

  async function openCampaign(campaign: any) {
    const popup = window.open('about:blank', '_blank', 'noopener,noreferrer'); setBusy(true);
    const result = await post(`auth/partner-campaigns/${campaign.id}/click`, {}); setBusy(false);
    const data: any = readData(result); const destination = data?.destination_url || data?.destinationUrl;
    if (result.success && destination) { if (popup) popup.location.href = destination; else window.location.assign(destination); }
    else { popup?.close(); setNotice({ kind: 'error', text: result.error || 'This partner link is not available right now.' }); }
    if (result.success) await loadArea('campaigns');
  }

  async function joinSquad(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); const form = new FormData(event.currentTarget); setBusy(true);
    const result = await post('auth/squads/join', { invite_code: String(form.get('invite_code') || '').trim() }); setBusy(false);
    setNotice({ kind: result.success ? 'success' : 'error', text: result.success ? 'Squad join request sent.' : result.error || 'Could not join that squad.' });
    if (result.success) { event.currentTarget.reset(); await loadArea('squads'); }
  }

  async function signOut() { await post('auth/logout', {}).catch(() => undefined); session.clear(); setLoggedIn(false); setPayload({}); setArea('home'); }
  async function install() {
    if (installPrompt) { await installPrompt.prompt(); setInstallPrompt(null); return; }
    setIosHelp(true);
  }

  const profile = readData(payload['auth/profile'])?.user || readData(payload['auth/profile']) || {};
  const wallet = readData(payload['auth/wallet'])?.wallet || readData(payload['auth/wallet']) || {};
  const mining = readData(payload['auth/get_mining']);
  const verification = readData(payload['auth/verification/status']);
const navTitle = useMemo(() => ({ home: 'Home', mining: 'Mining', wallet: 'Wallet', community: 'Community', profile: 'Profile', verified: 'Verified Member', promo: 'Promo code', support: 'Support', tasks: 'Tasks & rewards', campaigns: 'Partner campaigns', squads: 'Squads', notifications: 'Notifications' }[area]), [area]);

  if (!loggedIn) return <main className="auth-page"><div className="auth-brand"><img src="/icon-192.png" alt="" /><span>BLOCKRIUM</span></div><section className="auth-card"><div className="eyebrow">WEB3 NETWORK</div><h1>{authMode === 'login' ? 'Welcome back' : 'Create your Blockrium identity'}</h1><p className="muted">Your wallet, mining, community and rewards in one place.</p>{notice && <NoticeBox notice={notice} />}
    <form onSubmit={authenticate} className="stack-form">
      {authMode === 'signup' && <><label>Username<input name="username" required autoComplete="username" minLength={3} maxLength={30} /></label><label>Phone number<input name="phone" required autoComplete="tel" /></label><label>Country<input name="country" required autoComplete="country-name" /></label></>}
      <label>Email<input name="email" type="email" required autoComplete="email" /></label><label>Password<input name="password" type="password" required autoComplete={authMode === 'login' ? 'current-password' : 'new-password'} minLength={8} /></label>
      <button className="primary wide" disabled={busy}>{busy ? 'Please wait…' : authMode === 'login' ? 'Sign in' : 'Create account'}</button>
    </form><button className="text-button" onClick={() => { setAuthMode(authMode === 'login' ? 'signup' : 'login'); setNotice(null); }}>{authMode === 'login' ? 'New to Blockrium? Create an account' : 'Already have an account? Sign in'}</button>
    <small className="muted legal">Continuing means you agree to Blockrium’s <a href="https://blockrium.com/privacy-policy" target="_blank" rel="noreferrer">Privacy Policy</a>.</small>
  </section></main>;

  return <div className="app-frame">
    <aside className="side-rail"><a className="brand" href="#home" onClick={(event) => { event.preventDefault(); setArea('home'); }}><img src="/icon-192.png" alt=""/><span>Blockrium</span></a><div className="side-label">YOUR NETWORK</div>{nav.map((item) => <button key={item.id} className={`nav-item ${area === item.id ? 'selected' : ''}`} onClick={() => setArea(item.id)}><span className="nav-icon">{item.icon}</span>{item.title}</button>)}<div className="side-divider"/>{([['tasks','☷','Tasks & rewards'],['campaigns','✦','Partner campaigns'],['squads','♧','Squads'],['notifications','♢','Notifications'],['verified','⬡','Verified Member'],['promo','◇','Promo code'],['support','?','Support']] as [Area,string,string][]).map(([id,icon,title])=><button key={id} className={`nav-item ${area===id?'selected':''}`} onClick={()=>setArea(id)}><span className="nav-icon">{icon}</span>{title}</button>)}<div className="rail-bottom"><span>Web3 Network</span><button className="text-button" onClick={signOut}>Sign out</button></div></aside>
    <main className="main-area"><header className="topbar"><div className="crumb"><span className="status-dot"/> Blockrium <span className="crumb-muted">/ {navTitle}</span></div><div className="top-actions"><button className="outline install-button" onClick={install}>↓ <span>Install app</span></button><button className="avatar" onClick={() => setArea('profile')}>{String(textOf(profile, 'username', 'name')).slice(0,1).toUpperCase()}</button></div></header>
      <div className="content"><div className="page-heading"><div><div className="eyebrow">BLOCKRIUM WEB3 NETWORK</div><h1>{navTitle}</h1><p className="muted">{area === 'home' ? `Good to see you, ${textOf(profile, 'username', 'name')}.` : 'Your Blockrium account, activity and services.'}</p></div><button className="outline refresh" onClick={() => loadArea(area)} aria-label="Refresh">↻ Refresh</button></div>
        {notice && <NoticeBox notice={notice} />}
        {area === 'home' && <><AdSlot banners={readData(payload.banners)} /><section className="metric-grid"><Metric label="Available balance" value={`${money(textOf(wallet, 'wallet_balance', 'balance', 'available_balance') === '—' ? 0 : textOf(wallet, 'wallet_balance', 'balance', 'available_balance'))} BRC`} icon="◈"/><Metric label="Mining status" value={textOf(mining, 'status', 'mining_status', 'message')} icon="⛏"/><Metric label="KYC status" value={textOf(verification, 'kyc_status', 'status')} icon="⬡"/></section><section className="panel"><PanelTitle title="Your Blockrium" sub="Shortcuts to the main features"/><div className="shortcut-grid">{[['Mining','Start or check your session','mining','⛏'],['Wallet','Balance and transfers','wallet','▣'],['Verified Member','Identity and membership','verified','⬡'],['Promo code','Redeem a partner reward','promo','◇']].map(([title, sub, key, icon])=><button className="shortcut" key={key} onClick={()=>setArea(key as Area)}><span className="shortcut-icon">{icon}</span><span><b>{title}</b><small>{sub}</small></span><span className="arrow">↗</span></button>)}</div></section><section className="panel"><PanelTitle title="Daily activity" sub="Tasks and updates from Blockrium"/><Records value={readData(payload['auth/get-task'])} /></section></>}
        {area === 'mining' && <><section className="hero-panel"><div className="eyebrow">MINING SESSION</div><h2>{textOf(mining,'status','mining_status','Ready to start')}</h2><p className="muted">Mining sessions are confirmed by Blockrium’s server. The web app will never show a reward as earned before the API confirms it.</p><button className="primary" disabled={busy} onClick={startMining}>{busy?'Sending…':'Start mining session'}</button></section><section className="metric-grid"><Metric label="Mining rate" value={`${money(textOf(mining,'mining_rate','rate')==='—'?0:textOf(mining,'mining_rate','rate'))} BRC`} icon="↗"/><Metric label="Paid boost" value={textOf(readData(payload['auth/my-boost']),'boost_percentage','percentage','0%')} icon="✦"/></section><section className="panel"><PanelTitle title="Mining tasks" sub="Available tasks from your account"/><Records value={readData(payload['auth/get-task'])}/></section><AdSlot banners={readData(payload.banners)}/></>}
        {area === 'wallet' && <><section className="balance-panel"><div className="eyebrow">BLOCKRIUM WALLET</div><h2>{money(textOf(wallet,'wallet_balance','balance','available_balance')==='—'?0:textOf(wallet,'wallet_balance','balance','available_balance'))} <small>BRC</small></h2><p className="muted">Available balance · confirmed by Blockrium</p><div className="wallet-actions"><button onClick={()=>document.getElementById('transfer-form')?.scrollIntoView({behavior:'smooth'})}>↑ Send BRC</button><button onClick={()=>setNotice({kind:'info',text:'Receive details will be shown here when provided by your account’s wallet API.'})}>▦ Receive</button><button onClick={()=>setArea('verified')}>◇ Membership</button></div></section><section className="panel"><PanelTitle title="Send BRC to a Blockrium member" sub="The API enforces eligibility, balance, fee and duplicate-request protection."/><form id="transfer-form" className="transfer-grid" onSubmit={sendTransfer}><label>Recipient username<input name="recipient" required autoComplete="off" /></label><label>Amount (BRC)<input name="amount" required inputMode="decimal" placeholder="0.00000050" /></label><button className="primary" disabled={busy}>{busy?'Sending…':'Review and send'}</button></form><p className="muted hint">Transfers are final after the server accepts them. Check the username and amount before confirming.</p></section><section className="panel"><PanelTitle title="Recent transfers" sub="Latest server-recorded activity"/><Records value={readData(payload['auth/brc/transfers?limit=20'])}/></section></>}
        {area === 'community' && <><section className="panel"><PanelTitle title="Top Blockrium members" sub="Community activity provided by the Blockrium API"/><Records value={readData(payload['top-miners'])}/></section><section className="panel"><PanelTitle title="Your network" sub="Referral network overview"/><Records value={readData(payload['auth/my-network?filter=all'])}/></section><section className="panel"><PanelTitle title="Referral missions"/><Records value={readData(payload['auth/referral-missions'])}/></section></>}
        {area === 'profile' && <><section className="profile-card"><div className="large-avatar">{String(textOf(profile,'username','name')).slice(0,1).toUpperCase()}</div><div><div className="eyebrow">BLOCKRIUM IDENTITY</div><h2>{textOf(profile,'username','name')}</h2><p className="muted">{textOf(profile,'email')}</p><Badge label={verification?.is_verified ? 'Blockrium Verified' : verification?.kyc_status === 'approved' ? 'KYC verified' : 'KYC not verified'} ok={Boolean(verification?.is_verified || verification?.kyc_status === 'approved')}/></div></section><section className="panel"><PanelTitle title="Account details"/><div className="details-grid"><Detail label="Username" value={textOf(profile,'username')}/><Detail label="Email" value={textOf(profile,'email')}/><Detail label="Phone" value={textOf(profile,'phone_number','phone')}/><Detail label="User ID" value={textOf(profile,'id','user_id','uid')}/></div></section><section className="panel link-panel"><button onClick={()=>setArea('verified')}>KYC and membership <span>→</span></button><button onClick={()=>setArea('promo')}>Promo redemption history <span>→</span></button><button onClick={()=>setArea('support')}>Contact support <span>→</span></button><button onClick={signOut}>Sign out <span>→</span></button></section></>}
        {area === 'verified' && <><section className="hero-panel"><div className="eyebrow">IDENTITY & MEMBERSHIP</div><h2>{verification?.is_verified?'Blockrium Verified':verification?.kyc_status==='approved'?'KYC approved · membership pending':`KYC ${textOf(verification,'kyc_status','not started')}`}</h2><p className="muted">KYC approval and Blockrium Verified membership are separate. Your KYC result does not require a membership payment. Member benefits activate only after payment confirmation from the backend.</p>{(!verification?.is_verified && (['not_started','rejected'].includes(String(verification?.kyc_status)) || verification?.kyc_retry_available))&&<button className="primary" disabled={busy} onClick={startKyc}>{busy?'Starting…':verification?.kyc_retry_available||verification?.kyc_status==='rejected'?'Retry KYC':'Start KYC'}</button>}<div className="badge-row"><Badge label={verification?.kyc_status==='approved'?'KYC Verified':'KYC not verified'} ok={verification?.kyc_status==='approved'}/><Badge label={verification?.is_verified?'Blockrium Verified':'Membership inactive'} ok={Boolean(verification?.is_verified)}/></div>{verification?.next_action_url||verification?.payment?.checkout_url?<button className="primary" style={{marginTop:14}} onClick={()=>window.location.assign(verification?.next_action_url||verification?.payment?.checkout_url)}>Continue payment</button>:verification?.kyc_status==='approved'&&!verification?.is_verified?<p className="notice info">Web membership checkout is not configured for this account. Your KYC remains approved; no payment will be assumed until the server confirms it. Use the Android app checkout or contact support.</p>:null}</section><section className="panel"><PanelTitle title="Payment confirmation support" sub="If Google Play charged you but membership did not activate, send your GPA order ID and receipt for account-linked review."/><form className="stack-form support-form" onSubmit={submitPaymentSupport}><label>Google Play order ID (GPA…)<input name="provider_reference" placeholder="GPA.1234-5678-9012-34567" required /></label><label>Google Play account email (optional)<input name="provider_account_email" type="email" placeholder="The account used for this Play purchase" /></label><label>What happened?<textarea name="message" minLength={10} required rows={4} placeholder="Describe the payment or activation issue." /></label><label>Receipt screenshot (optional)<input name="receipt" type="file" accept="image/png,image/jpeg,image/webp" /></label><button className="primary" disabled={busy}>{busy?'Submitting…':'Send payment report'}</button></form><p className="muted hint">Screenshots or typed emails do not prove payment. Blockrium must confirm the order with Google Play before enabling paid benefits.</p></section><section className="panel"><PanelTitle title="Support conversation" sub="Messages linked to your current payment support case"/><Records value={verification?.payment_support?.latest?.messages || verification?.payment_support?.latest}/>{verification?.payment_support?.latest?.id&&<form className="reply-form" onSubmit={sendSupportReply}><label>Reply to Blockrium Support<textarea name="message" required minLength={2} rows={3}/></label><button className="outline" disabled={busy}>Send reply</button></form>}</section></>}
        {area === 'promo' && <><section className="hero-panel"><div className="eyebrow">PARTNER REWARDS</div><h2>Have a promo code?</h2><p className="muted">Enter a code shared by a Blockrium partner. The server validates eligibility and records rewards.</p><form className="promo-form" onSubmit={redeem}><input name="code" autoCapitalize="characters" maxLength={40} required placeholder="ENTER PROMO CODE"/><button className="primary" disabled={busy}>{busy?'Checking…':'Redeem code'}</button></form></section><section className="panel"><PanelTitle title="Your redemption history"/><Records value={readData(payload['auth/partner-promo-codes/redemptions'])}/></section></>}
        {area === 'support' && <><section className="panel"><PanelTitle title="Blockrium Support" sub="Issue reports are tied to your signed-in account and appear in the admin App Health log."/><form className="stack-form" onSubmit={async (event)=>{event.preventDefault();const form=new FormData(event.currentTarget);const category=String(form.get('category'));const message=String(form.get('message')).trim();setBusy(true);const result=await post('app-issue-log',{severity:'warning',source:'user_report',action:'user_report',endpoint:'manual/help-center',method:'POST',message:`${category}: ${message}`,metadata:{category,user_message:message}});setBusy(false);setNotice({kind:result.success?'success':'error',text:result.success?'Your issue report was sent to Blockrium.':result.error||'Support report could not be sent.'});if(result.success)event.currentTarget.reset();}}><label>Issue type<select name="category"><option>Account and sign-in</option><option>Mining and rewards</option><option>Wallet and transfer</option><option>KYC and payment</option><option>Community and referrals</option><option>Other</option></select></label><label>Tell us what happened<textarea name="message" minLength={10} required rows={5}/></label><button className="primary" disabled={busy}>{busy?'Sending…':'Send issue report'}</button></form><p className="muted hint">This is an issue report, not a live chat. For Google Play payment issues, use the Verified Member payment report to attach the GPA order ID and keep replies in the payment case.</p></section></>}
        {area === 'tasks' && <><section className="panel"><PanelTitle title="Tasks" sub="Live task availability from your Blockrium account."/><Records value={readData(payload['auth/get-task'])}/></section><section className="panel"><PanelTitle title="Daily missions"/><Records value={readData(payload['auth/daily-missions'])}/></section><section className="panel"><PanelTitle title="Onboarding quest"/><Records value={readData(payload['auth/onboarding-quest'])}/></section><AdSlot banners={readData(payload.banners)}/></>}
        {area === 'campaigns' && <section className="panel"><PanelTitle title="Partner campaigns" sub="Campaigns and rewards are loaded from Blockrium."/>{asList(readData(payload['auth/partner-campaigns'])).length?asList(readData(payload['auth/partner-campaigns'])).map((campaign:any)=><article className="campaign-card" key={campaign.id}><div className="campaign-copy"><div className="eyebrow">{campaign.partner_name||campaign.partnerName||'BLOCKRIUM PARTNER'}</div><h2>{campaign.title||campaign.name||'Partner campaign'}</h2><p className="muted">{campaign.description||campaign.short_description||''}</p><small className="muted">{campaign.reward?.amount??campaign.reward_amount??0} {campaign.reward?.label||campaign.reward_label||'BRC'} · {campaign.user_status||campaign.userStatus||campaign.status||'Available'}</small></div><button className="primary" disabled={busy||['claimed','paused','expired'].includes(String(campaign.user_status||campaign.userStatus||campaign.status).toLowerCase())} onClick={()=>openCampaign(campaign)}>Open campaign</button></article>):<div className="empty-state">No partner campaigns are currently available.</div>}</section>}
        {area === 'squads' && <><section className="panel"><PanelTitle title="Your squad" sub="Join an existing squad with an invite code."/><form className="promo-form" onSubmit={joinSquad}><input name="invite_code" required placeholder="SQUAD INVITE CODE"/><button className="primary" disabled={busy}>{busy?'Joining…':'Join squad'}</button></form><Records value={readData(payload['auth/squads/app-home'])}/></section><section className="panel"><PanelTitle title="Discover squads"/><Records value={readData(payload['auth/squads/discover'])}/></section></>}
        {area === 'notifications' && <section className="panel"><PanelTitle title="Notifications" sub="Recent notifications for your signed-in account."/><Records value={readData(payload['auth/all-notification'])}/></section>}
        <footer className="footer"><span>© Blockrium · Web3 Network</span><div><a href="https://blockrium.com/privacy-policy" target="_blank" rel="noreferrer">Privacy</a><a href="https://blockrium.com" target="_blank" rel="noreferrer">Website</a><a href="https://t.me/blockrium" target="_blank" rel="noreferrer">Telegram</a></div></footer>
      </div>
    </main>
    <nav className="mobile-tabs">{nav.map((item)=><button key={item.id} className={area===item.id?'active':''} onClick={()=>setArea(item.id)}><span>{item.icon}</span><small>{item.title}</small></button>)}</nav>
    {iosHelp&&<div className="modal-backdrop" onClick={()=>setIosHelp(false)}><section className="install-modal" onClick={(event)=>event.stopPropagation()}><button className="modal-close" onClick={()=>setIosHelp(false)}>×</button><div className="eyebrow">INSTALL BLOCKRIUM</div><h2>Add Blockrium to your Home Screen</h2><p>In Safari, tap <b>Share</b>, then choose <b>Add to Home Screen</b>. Open the new icon to use Blockrium like an app.</p><button className="primary wide" onClick={()=>setIosHelp(false)}>Got it</button></section></div>}
  </div>;
}

function NoticeBox({notice}:{notice:Notice}) { return <div className={`notice ${notice.kind}`} role="status">{notice.text}</div>; }
function Metric({label,value,icon}:{label:string;value:any;icon:string}) { return <article className="metric"><span className="metric-icon">{icon}</span><div><small>{label}</small><b>{value}</b></div></article>; }
function PanelTitle({title,sub}:{title:string;sub?:string}) { return <header className="panel-title"><div><h2>{title}</h2>{sub&&<p className="muted">{sub}</p>}</div></header>; }
function Badge({label,ok}:{label:string;ok:boolean}) { return <span className={`badge ${ok?'good':'neutral'}`}><i/>{label}</span>; }
function Detail({label,value}:{label:string;value:any}) { return <div className="detail"><small>{label}</small><b>{value}</b></div>; }
function Records({value}:{value:any}) { const rows=asList(value); if(!rows.length) return <div className="empty-state">No recent records were returned.</div>; return <div className="record-list">{rows.slice(0,12).map((row,index)=><article className="record" key={row?.id||row?.user_id||row?.task_id||index}><span className="record-mark">{String(textOf(row,'status','state','type','event')).slice(0,1).toUpperCase()}</span><span className="record-copy"><b>{textOf(row,'title','name','username','description','task_name','reference')}</b><small>{textOf(row,'subtitle','status','created_at','date','message')}</small></span><span className="record-value">{row?.amount!==undefined?`${money(row.amount)} ${textOf(row,'label','currency','BRC')}`:''}</span></article>)}</div>; }
function AdSlot({banners}:{banners:any}) { const records=asList(banners); const item=records[0]; return <section className="ad-slot"><span className="ad-label">SPONSORED</span>{item?.image_url||item?.image?<img src={item.image_url||item.image} alt={item.title||'Blockrium partner'} />:<div className="ad-placeholder"><span className="ad-mark">B</span><div><b>{item?.title||'Blockrium partner spotlight'}</b><small>{item?.description||'Partner announcements and sponsored placements appear here.'}</small></div></div>}{item?.link&&<a className="ad-cta" href={item.link} target="_blank" rel="noreferrer">Learn more ↗</a>}</section>; }
