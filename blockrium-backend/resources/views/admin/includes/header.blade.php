<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="csrf_token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Blockrium Admin')</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="{{ asset('css/pagination_style.css') }}">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.css">
  <link rel="stylesheet" href="{{ asset('css/admin-panel.css') }}?v=20260713-nav">
  @include('admin.includes.admin-critical-css')
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <nav class="main-header navbar navbar-expand admin-topbar">
    <ul class="navbar-nav align-items-center">
      <li class="nav-item">
        <a class="nav-link admin-icon-link" data-widget="pushmenu" href="#" role="button" aria-label="Toggle navigation">
          <i class="fas fa-bars"></i>
        </a>
      </li>
      <li class="nav-item d-none d-md-block">
        <span class="admin-topbar-title">Blockrium Operations</span>
      </li>
    </ul>

    <ul class="navbar-nav ml-auto align-items-center">
      <li class="nav-item d-none d-lg-flex admin-topbar-chip">
        <i class="fa-solid fa-shield-halved"></i>
        <span>Secure admin</span>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link admin-user-toggle" data-toggle="dropdown" href="#" aria-haspopup="true" aria-expanded="false">
          <span class="admin-avatar">{{ strtoupper(substr(session('ad_name', 'A'), 0, 1)) }}</span>
          <span class="d-none d-sm-inline">Admin</span>
          <i class="fa-solid fa-chevron-down"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-right admin-dropdown">
          <a class="dropdown-item" href="{{ url('/admin/change_password') }}">
            <i class="fa-solid fa-key"></i>
            Change Password
          </a>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item text-danger" href="{{ url('/admin/logout') }}">
            <i class="fa-solid fa-right-from-bracket"></i>
            Logout
          </a>
        </div>
      </li>
    </ul>
  </nav>
