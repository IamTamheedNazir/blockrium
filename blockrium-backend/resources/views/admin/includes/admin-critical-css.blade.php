<style>
  :root {
    --admin-bg: #f5f7fb;
    --admin-surface: #ffffff;
    --admin-sidebar: #101828;
    --admin-sidebar-soft: #1d2939;
    --admin-text: #111827;
    --admin-muted: #667085;
    --admin-border: #dbe3ee;
    --admin-primary: #0f766e;
    --admin-primary-dark: #115e59;
    --admin-blue: #2563eb;
    --admin-green: #16a34a;
    --admin-cyan: #0891b2;
    --admin-orange: #d97706;
    --admin-radius: 8px;
  }

  html,
  body {
    font-family: Inter, Arial, sans-serif !important;
    background: var(--admin-bg) !important;
    color: var(--admin-text);
  }

  body .wrapper .main-header.admin-topbar {
    min-height: 60px;
    border-bottom: 1px solid var(--admin-border);
    background: #ffffff !important;
  }

  body .wrapper .main-sidebar.admin-sidebar {
    background: var(--admin-sidebar) !important;
    box-shadow: none !important;
  }

  body .admin-sidebar .sidebar {
    height: calc(100vh - 60px);
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.45) transparent;
  }

  body .admin-brand {
    min-height: 60px;
    padding: 13px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    color: #fff !important;
    background: var(--admin-sidebar) !important;
  }

  body .admin-brand:hover {
    color: #fff !important;
  }

  body .admin-brand-mark,
  body .admin-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: var(--admin-radius);
    color: #fff;
    background: var(--admin-primary);
    font-weight: 800;
  }

  body .admin-brand .brand-text {
    color: #fff !important;
    font-size: 17px;
    font-weight: 800;
  }

  body .admin-sidebar-search {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 14px 10px 10px;
    padding: 9px 11px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: var(--admin-radius);
    color: #98a2b3;
    background: rgba(255, 255, 255, 0.06);
  }

  body .admin-sidebar-search input {
    width: 100%;
    border: 0;
    outline: 0;
    color: #fff;
    background: transparent;
    font-size: 13px;
  }

  body .admin-sidebar-search input::placeholder {
    color: #98a2b3;
  }

  body .admin-nav.nav-sidebar .nav-link {
    min-height: 38px;
    margin: 2px 8px;
    border-radius: var(--admin-radius);
    color: #cbd5e1 !important;
    font-size: 14px;
    font-weight: 650;
  }

  body .admin-nav.nav-sidebar .nav-link:hover {
    color: #fff !important;
    background: rgba(255, 255, 255, 0.08) !important;
  }

  body .admin-nav.nav-sidebar .nav-link.active {
    color: #fff !important;
    background: var(--admin-primary) !important;
  }

  body .admin-nav.nav-sidebar .nav-link.admin-nav-group {
    color: #d9e2ef !important;
    background: transparent !important;
    box-shadow: none !important;
  }

  body .admin-nav.nav-sidebar .nav-link.admin-nav-group.is-open {
    color: #fff !important;
    background: rgba(255, 255, 255, 0.07) !important;
    box-shadow: none !important;
  }

  body .admin-nav.nav-sidebar .nav-treeview .nav-link {
    min-height: 34px;
    padding-left: 36px;
    color: #aeb8c6 !important;
    font-size: 12.5px;
  }

  body .admin-nav.nav-sidebar .nav-treeview .nav-link .nav-icon {
    width: 18px;
    font-size: 12px;
    opacity: 0.8;
  }

  body .admin-content.content-wrapper {
    background: var(--admin-bg) !important;
  }

  body .admin-page-header {
    padding: 24px 0 12px;
  }

  body .admin-page-header h1 {
    margin: 0;
    color: var(--admin-text);
    font-size: 28px;
    font-weight: 800;
  }

  body .admin-page-header p {
    max-width: 760px;
    margin: 6px 0 0;
    color: var(--admin-muted);
    font-size: 14px;
  }

  body .admin-header-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
  }

  body .btn-admin-primary,
  body .btn-admin-muted {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 40px;
    padding: 9px 14px;
    border-radius: var(--admin-radius);
    font-size: 14px;
    font-weight: 800;
    text-decoration: none !important;
  }

  body .btn-admin-primary {
    border: 1px solid var(--admin-primary);
    color: #fff !important;
    background: var(--admin-primary);
  }

  body .btn-admin-muted {
    border: 1px solid var(--admin-border);
    color: var(--admin-text) !important;
    background: #fff;
  }

  body .admin-dashboard .row:first-of-type {
    margin-bottom: 4px;
  }

  body .admin-stat-card,
  body .admin-panel {
    display: flex;
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    background: var(--admin-surface);
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  }

  body .admin-stat-card {
    align-items: center;
    gap: 14px;
    min-height: 126px;
    padding: 18px;
    color: var(--admin-text) !important;
    text-decoration: none !important;
  }

  body .admin-stat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 48px;
    width: 48px;
    height: 48px;
    border-radius: var(--admin-radius);
    color: #fff;
    font-size: 20px;
  }

  body .admin-stat-blue .admin-stat-icon { background: var(--admin-blue); }
  body .admin-stat-green .admin-stat-icon { background: var(--admin-green); }
  body .admin-stat-cyan .admin-stat-icon { background: var(--admin-cyan); }
  body .admin-stat-orange .admin-stat-icon { background: var(--admin-orange); }
  body .admin-stat-primary .admin-stat-icon { background: var(--admin-primary); }

  body .admin-stat-copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 4px;
  }

  body .admin-stat-label {
    color: var(--admin-muted);
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
  }

  body .admin-stat-copy strong {
    color: var(--admin-text);
    font-size: 23px;
    line-height: 1.1;
    overflow-wrap: anywhere;
  }

  body .admin-stat-copy small {
    color: var(--admin-muted);
    font-size: 12px;
  }

  body .admin-panel {
    display: block;
    padding: 18px;
  }

  body .admin-panel-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 14px;
  }

  body .admin-panel-header h2 {
    margin: 0;
    color: var(--admin-text);
    font-size: 18px;
    font-weight: 800;
  }

  body .admin-panel-header p {
    margin: 3px 0 0;
    color: var(--admin-muted);
    font-size: 13px;
  }

  body .admin-panel-link {
    color: var(--admin-primary) !important;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none !important;
    white-space: nowrap;
  }

  body .admin-table {
    margin: 0;
  }

  body .admin-table thead th {
    border-top: 0;
    border-bottom: 1px solid var(--admin-border);
    color: #475467;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
  }

  body .admin-table td {
    vertical-align: middle;
    border-top: 1px solid #eef2f7;
    color: var(--admin-text);
    font-size: 14px;
  }

  body .admin-user-cell {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
  }

  body .admin-user-cell > span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 36px;
    width: 36px;
    height: 36px;
    border-radius: var(--admin-radius);
    color: #fff;
    background: #344054;
    font-weight: 800;
  }

  body .admin-user-cell strong,
  body .admin-wallet-row strong {
    display: block;
    color: var(--admin-text);
    font-size: 14px;
  }

  body .admin-user-cell small {
    color: var(--admin-muted);
    font-size: 12px;
  }

  body .admin-badge {
    display: inline-flex;
    align-items: center;
    min-height: 26px;
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
  }

  body .admin-badge-success {
    color: #166534;
    background: #dcfce7;
  }

  body .admin-badge-muted {
    color: #475569;
    background: #e2e8f0;
  }

  body .admin-badge-warning {
    color: #92400e;
    background: #fef3c7;
  }

  body .admin-badge-danger {
    color: #991b1b;
    background: #fee2e2;
  }

  body .admin-action-list,
  body .admin-wallet-list,
  body .admin-mini-list,
  body .admin-health-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin: 0;
  }

  body .admin-action-item,
  body .admin-mini-row,
  body .admin-wallet-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 13px;
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    color: var(--admin-text) !important;
    background: #fbfdff;
    text-decoration: none !important;
  }

  body .admin-subscription-metric {
    min-height: 88px;
    padding: 14px;
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    background: #fbfdff;
  }

  body .admin-subscription-metric span,
  body .admin-plan-breakdown span {
    display: block;
    color: var(--admin-muted);
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
  }

  body .admin-subscription-metric strong {
    display: block;
    margin-top: 8px;
    color: var(--admin-text);
    font-size: 22px;
    line-height: 1.1;
    overflow-wrap: anywhere;
  }

  body .admin-plan-breakdown {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
  }

  body .admin-plan-breakdown span {
    min-height: 58px;
    padding: 11px 12px;
    border-radius: var(--admin-radius);
    background: #f1f5f9;
    text-transform: none;
  }

  body .admin-plan-breakdown strong {
    display: block;
    color: var(--admin-text);
    font-size: 18px;
  }

  body .admin-mini-row {
    justify-content: flex-start;
  }

  body .admin-mini-row:hover {
    border-color: rgba(15, 118, 110, 0.32);
  }

  body .admin-mini-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 36px;
    width: 36px;
    height: 36px;
    border-radius: var(--admin-radius);
    color: #fff;
    background: var(--admin-primary);
    font-weight: 800;
  }

  body .admin-mini-row span:last-child {
    min-width: 0;
  }

  body .admin-mini-row strong {
    display: block;
    color: var(--admin-text);
    font-size: 14px;
  }

  body .admin-mini-row small {
    display: block;
    color: var(--admin-muted);
    font-size: 12px;
    overflow-wrap: anywhere;
  }

  body .admin-action-item span {
    flex: 1;
    font-weight: 800;
  }

  body .admin-action-item:hover {
    color: var(--admin-primary) !important;
    border-color: rgba(15, 118, 110, 0.32);
  }

  body .admin-health-list {
    padding: 0;
    list-style: none;
  }

  body .admin-health-list li {
    display: flex;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #eef2f7;
    color: var(--admin-text);
    font-size: 14px;
    font-weight: 650;
  }

  body .admin-health-list li:last-child {
    border-bottom: 0;
  }

  body .admin-health-list i {
    color: var(--admin-primary);
    margin-top: 3px;
  }

  body .admin-empty {
    padding: 18px;
    color: var(--admin-muted);
    text-align: center;
  }

  @media (max-width: 767.98px) {
    body .admin-page-header {
      padding-top: 18px;
    }

    body .admin-header-actions {
      justify-content: flex-start;
      margin-top: 12px;
    }

    body .admin-stat-card {
      min-height: 112px;
    }

    body .admin-plan-breakdown {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
</style>
