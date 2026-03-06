<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'Page Title' }}</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Barcode scanner library -->
    <script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Inter font from Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Theme tokens: Orange & White theme - MATCHING ADMIN */
        :root {
            /* Clean Page Background */
            --page-bg: #f3f4f6;
            --surface: #ffffff;

            /* Professional Brand Palette - Vibrant Orange & Neutral Accents */
            --primary: #e11d48;
            --primary-600: #e07010;
            --primary-700: #c66008;
            --primary-50: #fff7ed;
            --primary-100: #ffedd5;

            /* Functional Colors */
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;

            /* Refined Neutral Palette */
            --text-main: #111827;
            --text-muted: #6b7280;
            --text-light: #9ca3af;
            --border: #e5e7eb;
            --border-light: #f3f4f6;

            /* Effects */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --radius-md: 10px;
            --radius-lg: 16px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--page-bg);
            color: var(--text-main);
            letter-spacing: -0.01em;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .fw-800 { font-weight: 800 !important; }
        .text-orange { color: #e11d48 !important; }

        /* Ensure dropdowns in table are not clipped */
        .table-responsive {
            overflow: visible !important;
        }

        .dropdown-menu {
            position: absolute !important;
            left: auto !important;
            right: 0 !important;
            top: 30% !important;
            margin-top: 0.2rem;
            min-width: 160px;
            z-index: 9999 !important;
            background: #fff !important;
            box-shadow: 0 12px 32px 0 rgba(0, 0, 0, 0.22), 0 2px 8px 0 rgba(0, 0, 0, 0.10);
            border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important;
            overflow: visible !important;
            filter: none !important;
        }

        .dropdown-menu>li>.dropdown-item {
            background: #fff !important;
            z-index: 9999 !important;
        }

        .dropdown-menu>li>.dropdown-item:active,
        .dropdown-menu>li>.dropdown-item:focus {
            background: #f0f7ff !important;
            color: #222 !important;
        }

        .dropdown {
            position: relative !important;
        }

        .container-fluid,
        .card,
        .modal-content {
            font-size: 13px !important;
        }

        .table th,
        .table td {
            font-size: 12px !important;
            padding: 0.35rem 0.5rem !important;
        }

        .modal-header {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
            margin-bottom: 0.25rem !important;
        }

        .modal-footer,
        .card-header,
        .card-body,
        .row,
        .col-md-6,
        .col-md-4,
        .col-md-2,
        .col-md-12 {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
            margin-top: 0.25rem !important;
            margin-bottom: 0.25rem !important;
        }

        .form-control,
        .form-select {
            font-size: 12px !important;
            padding: 0.35rem 0.5rem !important;
        }

        .btn,
        .btn-sm,
        .btn-primary,
        .btn-secondary,
        .btn-outline-danger,
        .btn-outline-secondary {
            font-size: 12px !important;
            padding: 0.25rem 0.5rem !important;
        }

        .badge {
            font-size: 11px !important;
            padding: 0.25em 0.5em !important;
        }

        .list-group-item,
        .dropdown-item {
            font-size: 12px !important;
            padding: 0.35rem 0.5rem !important;
        }

        .summary-card,
        .card {
            border-radius: 8px !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06) !important;
        }

        .icon-container {
            width: 36px !important;
            height: 36px !important;
            font-size: 1.1rem !important;
        }

        /* Sidebar styles */
        .sidebar {
            width: 270px;
            height: 100vh;
            background: #ffffff;
            color: var(--text-main);
            padding: 0;
            position: fixed;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1040;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 1px 0 0 var(--border);
        }

        /* Add custom scrollbar styling for sidebar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        /* Add padding to the bottom of sidebar to ensure last items are visible */
        .sidebar .nav {
            padding-bottom: 50px;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar.collapsed .sidebar-title,
        .sidebar.collapsed .nav-link span {
            display: none;
        }

        .sidebar.collapsed .nav-link.dropdown-toggle::after {
            display: flex !important;
            margin-left: 0;
            width: 100%;
            justify-content: center;
        }

        .sidebar.collapsed .nav-link i {
            margin-right: 0;
            font-size: 1.25rem;
        }

        .sidebar.collapsed .nav-link {
            text-align: center;
            padding: 10px;
        }

        .sidebar.collapsed .nav-link.dropdown-toggle::after {
            display: none;
        }

        .sidebar-header {
            padding: 20px 20px 0;
            margin-bottom: 5px;
        }

        .sidebar-title {
            font-weight: 600;
            font-size: 1.2rem;
            color: #1e293b;
            letter-spacing: -0.02em;
        }

        /* Navigation styles */
        .nav-item {
            margin: 2px 0;
        }

        .nav-link {
            color: var(--text-muted);
            padding: 6px 24px;
            margin: 4px 12px;
            border-radius: 8px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            font-weight: 500;
        }

        .nav-link.active {
            background: var(--primary-50);
            color: var(--primary);
            border-left: none;
        }

        .nav-link:hover:not(.active) {
            color: var(--primary);
            background: var(--border-light);
            outline: none;
        }

        .nav-link i {
            margin-right: 12px;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            transition: transform 0.2s;
        }

        .nav-link:hover i {
            transform: scale(1.1);
        }

        .nav-link.dropdown-toggle::after {
            margin-left: auto;
            border: none;
            content: "\F282";
            font-family: "bootstrap-icons";
            font-size: 0.95rem;
            transition: transform 0.3s ease;
            display: flex !important;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            color: var(--text-muted);
        }

        .nav-link.dropdown-toggle[aria-expanded="true"]::after {
            transform: rotate(180deg);
            color: var(--primary);
        }

        .nav-link.dropdown-toggle:hover::after {
            color: var(--primary);
        }

        #inventorySubmenu .nav-link,
        #hrSubmenu .nav-link,
        #salesSubmenu .nav-link,
        #stockSubmenu .nav-link,
        #purchaseSubmenu .nav-link,
        #returnSubmenu .nav-link,
        #banksSubmenu .nav-link,
        #paymentSubmenu .nav-link,
        #staffSubmenu .nav-link,
        #peopleSubmenu .nav-link,
        #expensesSubmenu .nav-link {
            padding: 8px 20px;
            font-size: 0.9rem;
            margin: 2px 8px;
        }

        /* Add these styles to further improve submenu spacing */
        .collapse .nav-item {
            margin: 3px 0;
        }

        .collapse .nav.flex-column {
            padding-bottom: 0;
            padding-top: 5px;
            padding-left: 12px;
        }

        .collapse .nav-item:last-child {
            margin-bottom: 5px;
        }

        /* Disabled menu item styles */
        .nav-link.disabled {
            color: rgba(255, 255, 255, 0.4) !important;
            cursor: not-allowed !important;
            opacity: 0.6;
            pointer-events: none;
        }

        .nav-link.disabled i {
            color: rgba(255, 255, 255, 0.4) !important;
        }

        .nav-link.disabled:hover {
            background-color: transparent !important;
            color: rgba(255, 255, 255, 0.4) !important;
        }

        /* Top bar styles */
        .top-bar {
            height: 72px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            padding: 0 24px;
            position: fixed;
            top: 0;
            right: 0;
            left: 270px;
            z-index: 1000;
            display: flex;
            align-items: center;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            border-bottom: 1px solid var(--border);
        }

        .top-bar.collapsed {
            left: 80px;
        }

        /* Main content area */
        .main-content {
            margin-left: 270px;
            margin-top: 72px;
            padding: 24px;
            background-color: var(--page-bg);
            min-height: calc(100vh - 72px);
            width: calc(100% - 270px);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-content.collapsed {
            margin-left: 70px;
            width: calc(100% - 70px);
        }

        /* Top bar items styling */
        .topbar-brand {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: -0.02em;
            margin-right: auto;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 8px;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s;
        }

        .admin-info:hover {
            background: var(--border-light);
        }

        .admin-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .admin-name {
            font-weight: 500;
            font-size: 14px;
        }

        .sidebar-toggle {
            background: transparent;
            border: none;
            color: var(--text-main);
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-toggle:hover {
            background: var(--border-light);
            color: var(--primary);
        }

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .sidebar {
                width: 250px;
            }

            .top-bar {
                left: 250px;
            }

            .main-content {
                margin-left: 250px;
                width: calc(100% - 250px);
            }

            .top-bar.collapsed {
                left: 70px;
            }
        }

        @media (max-width: 767.98px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                transform: translateX(-100%);
                transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 1030;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .top-bar {
                left: 0;
                padding: 0 16px;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                margin-top: 72px;
                padding: 16px;
            }

            .topbar-brand {
                display: none;
            }
        }
    </style>

    {{-- ===== ERP TOP NAVIGATION STYLES ===== --}}
    <style>
        /* Hide old sidebar & top-bar — replaced by ERP top navigation */
        .sidebar { display: none !important; }
        .top-bar { display: none !important; }

        /* Override main content for full-width top-nav layout */
        .main-content {
            margin-left: 0 !important;
            margin-top: 54px !important;
            width: 100% !important;
            padding: 24px !important;
            min-height: calc(100vh - 54px) !important;
        }
        .main-content.collapsed {
            margin-left: 0 !important;
            width: 100% !important;
        }

        /* ===== ERP TOP NAVIGATION BAR ===== */
        .erp-topnav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 54px;
            background: var(--sidebar-bg, #0f172a);
            z-index: 1050;
            border-bottom: 2px solid var(--primary, #e11d48);
            box-shadow: 0 2px 8px rgba(0,0,0,0.18);
        }
        .erp-topnav-inner {
            display: flex;
            align-items: center;
            height: 100%;
            padding: 0 12px;
        }
        .erp-brand {
            display: flex;
            align-items: center;
            padding: 0 16px 0 4px;
            margin-right: 8px;
            border-right: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
            height: 100%;
        }
        .erp-brand a { display: flex; align-items: center; }
        .erp-brand img { height: 28px; width: auto; }

        .erp-menu {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            align-items: center;
            height: 100%;
            flex: 1;
            min-width: 0;
        }
        .erp-menu-item {
            position: relative;
            height: 100%;
        }
        .erp-menu-link {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 0 13px;
            height: 100%;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 500;
            background: none;
            border: none;
            cursor: pointer;
            white-space: nowrap;
            transition: color 0.15s, background 0.15s;
            font-family: inherit;
            line-height: 1;
        }
        .erp-menu-link:hover { color: #ffffff; background: rgba(255,255,255,0.06); text-decoration: none; }
        .erp-menu-link.active { color: #ffffff; background: rgba(255,255,255,0.08); box-shadow: inset 0 -2px 0 var(--primary, #e11d48); }
        .erp-menu-link i:first-child { font-size: 0.88rem; opacity: 0.8; }
        .erp-chevron { font-size: 0.5rem; opacity: 0.45; margin-left: 2px; }

        .erp-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 260px;
            max-height: 80vh;
            overflow-y: auto;
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.06);
            border-top: 2px solid var(--primary, #e11d48);
            border-radius: 0 0 8px 8px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.35);
            padding: 6px 0;
            z-index: 1060;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.15) transparent;
        }
        .erp-dropdown::-webkit-scrollbar { width: 5px; }
        .erp-dropdown::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 3px; }
        .erp-dropdown-right { left: auto; right: 0; }
        .erp-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 0.78rem;
            transition: color 0.12s, background 0.12s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-family: inherit;
        }
        .erp-dropdown-item:hover { color: #ffffff; background: rgba(255,255,255,0.06); text-decoration: none; }
        .erp-dropdown-item.active { color: var(--primary, #e11d48); background: rgba(225,29,72,0.08); }
        .erp-dropdown-item i { width: 18px; text-align: center; font-size: 0.85rem; opacity: 0.6; flex-shrink: 0; }
        .erp-dropdown-danger { color: #f87171 !important; }
        .erp-dropdown-danger:hover { background: rgba(248,113,113,0.1) !important; }
        .erp-dropdown-divider { border-top: 1px solid rgba(255,255,255,0.06); margin: 4px 0; }
        .erp-dropdown-header { padding: 8px 16px 4px; font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--primary, #e11d48); opacity: 0.85; }
        .erp-kbd { margin-left: auto; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1); border-radius: 3px; padding: 1px 6px; font-size: 0.58rem; font-family: monospace; color: rgba(255,255,255,0.4); line-height: 1.6; }

        .erp-right {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            flex-shrink: 0;
            height: 100%;
            padding-left: 12px;
        }
        .erp-user-dropdown { position: relative; height: 100%; display: flex; align-items: center; }
        .erp-user-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 10px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            color: rgba(255,255,255,0.7);
            cursor: pointer;
            transition: all 0.15s;
            font-family: inherit;
            font-size: inherit;
        }
        .erp-user-btn:hover { background: rgba(255,255,255,0.1); color: #ffffff; }
        .erp-user-avatar { width: 26px; height: 26px; border-radius: 50%; background: var(--primary, #e11d48); color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.7rem; }
        .erp-user-menu { min-width: 180px; }

        .erp-hamburger {
            display: none;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: none;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 6px;
            color: rgba(255,255,255,0.8);
            cursor: pointer;
            font-size: 1.15rem;
            margin-right: 8px;
            flex-shrink: 0;
        }
        .erp-hamburger:hover { background: rgba(255,255,255,0.06); }

        /* ===== KEYBOARD NAVIGATION FOCUS RINGS ===== */
        .erp-menu-link:focus-visible,
        .erp-menu-link:focus {
            outline: 2px solid var(--primary, #e11d48) !important;
            outline-offset: 2px;
            background: rgba(255,255,255,0.07) !important;
        }
        .erp-dropdown-item:focus-visible,
        .erp-dropdown-item:focus {
            outline: none !important;
            background: rgba(255,255,255,0.09) !important;
            color: #fff !important;
            box-shadow: inset 3px 0 0 var(--primary, #e11d48);
        }
        .erp-menu-link[aria-expanded="true"] .erp-chevron {
            transform: rotate(180deg);
        }
            .erp-hamburger { display: flex; }
            .erp-menu {
                position: fixed; top: 54px; left: 0; right: 0; bottom: 0;
                background: var(--sidebar-bg, #0f172a);
                flex-direction: column; align-items: stretch; overflow-y: auto;
                padding: 8px 0; z-index: 1049; height: auto;
                border-top: 1px solid rgba(255,255,255,0.06);
            }
            .erp-menu:not(.show) { display: none !important; }
            .erp-menu.show { display: flex !important; }
            .erp-menu-item { height: auto; }
            .erp-menu-link { height: auto; padding: 12px 20px; justify-content: flex-start; }
            .erp-dropdown { position: static; box-shadow: none; border: none; border-radius: 0; background: rgba(0,0,0,0.15); min-width: 100%; }
            .erp-dropdown-item { padding: 10px 32px; }
            .main-content { padding: 16px !important; }
        }
        @media (max-width: 575.98px) {
            .erp-topnav-inner { padding: 0 8px; }
            .erp-brand { padding: 0 8px 0 0; margin-right: 4px; }
            .erp-brand img { height: 22px; }
            .erp-right { gap: 6px; padding-left: 6px; }
        }
    </style>

    @stack('styles')
    @livewireStyles
</head>

<body data-erp-context="{{ $erpContext ?? 'general' }}">
    <div>
        {{-- ============================================= --}}
        {{-- BUSY-STYLE ERP TOP NAVIGATION BAR (Staff)     --}}
        {{-- ============================================= --}}
        <nav class="erp-topnav">
            <div class="erp-topnav-inner">
                {{-- Mobile Hamburger --}}
                <button class="erp-hamburger d-lg-none" onclick="document.getElementById('staffErpMenu').classList.toggle('show');" type="button" aria-label="Toggle menu">
                    <i class="bi bi-list"></i>
                </button>

                {{-- Logo / Brand --}}
                <div class="erp-brand">
                    <a href="{{ route('staff.dashboard') }}">
                        <img src="{{ asset('images/RNZ.png') }}" alt="RNZ Auto Parts" height="30">
                    </a>
                </div>

                {{-- ── BUSY-style 5-Menu Structure (Staff) ── --}}
                <ul class="erp-menu d-none d-lg-flex" id="staffErpMenu">

                    {{-- ╔══════════════════════════════════════╗ --}}
                    {{-- ║  1. ACCOUNTS                         ║ --}}
                    {{-- ╚══════════════════════════════════════╝ --}}
                    @if(auth()->user()->hasPermission('menu_customer') || auth()->user()->hasPermission('menu_supplier') || auth()->user()->hasPermission('menu_people') || auth()->user()->hasPermission('menu_banks'))
                    <x-erp-nav-dropdown label="Accounts" icon="journal-bookmark-fill" :active="request()->routeIs('staff.manage-customers', 'staff.supplier-management', 'staff.manage-staff', 'staff.income', 'staff.cheque-list', 'staff.return-cheque')">
                        @if(auth()->user()->hasPermission('menu_people') || auth()->user()->hasPermission('menu_customer') || auth()->user()->hasPermission('menu_supplier'))
                        <div class="erp-dropdown-header">Account Master</div>
                        @if(auth()->user()->hasPermission('menu_people_customers') || auth()->user()->hasPermission('menu_customer'))
                        <a href="{{ route('staff.manage-customers') }}" class="erp-dropdown-item {{ request()->routeIs('staff.manage-customers') ? 'active' : '' }}">
                            <i class="bi bi-person-lines-fill"></i> Customers (Debtors)
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_people_suppliers') || auth()->user()->hasPermission('menu_supplier'))
                        <a href="{{ route('staff.supplier-management') }}" class="erp-dropdown-item {{ request()->routeIs('staff.supplier-management') ? 'active' : '' }}">
                            <i class="bi bi-people"></i> Suppliers (Creditors)
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_people_staff'))
                        <a href="{{ route('staff.manage-staff') }}" class="erp-dropdown-item {{ request()->routeIs('staff.manage-staff') ? 'active' : '' }}">
                            <i class="bi bi-person-badge"></i> Staff Accounts
                        </a>
                        @endif
                        @endif

                        @if(auth()->user()->hasPermission('menu_banks'))
                        <div class="erp-dropdown-divider"></div>
                        <div class="erp-dropdown-header">Finance Accounts</div>
                        @if(auth()->user()->hasPermission('menu_banks_deposit'))
                        <a href="{{ route('staff.income') }}" class="erp-dropdown-item {{ request()->routeIs('staff.income') ? 'active' : '' }}">
                            <i class="bi bi-cash-stack"></i> Cash &amp; Bank Deposit
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_banks_cheque_list'))
                        <a href="{{ route('staff.cheque-list') }}" class="erp-dropdown-item {{ request()->routeIs('staff.cheque-list') ? 'active' : '' }}">
                            <i class="bi bi-card-text"></i> Cheque Register
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_banks_return_cheque'))
                        <a href="{{ route('staff.return-cheque') }}" class="erp-dropdown-item {{ request()->routeIs('staff.return-cheque') ? 'active' : '' }}">
                            <i class="bi bi-arrow-left-right"></i> Return Cheque
                        </a>
                        @endif
                        @endif
                    </x-erp-nav-dropdown>
                    @endif

                    {{-- ╔══════════════════════════════════════╗ --}}
                    {{-- ║  2. TRANSACTIONS                     ║ --}}
                    {{-- ╚══════════════════════════════════════╝ --}}
                    @if(auth()->user()->hasPermission('menu_sales') || auth()->user()->hasPermission('menu_purchase') || auth()->user()->hasPermission('menu_payment') || auth()->user()->hasPermission('menu_return') || auth()->user()->hasPermission('menu_quotation') || auth()->user()->hasPermission('menu_expenses'))
                    <x-erp-nav-dropdown label="Transactions" icon="arrow-left-right" :active="request()->routeIs('staff.sales-system', 'staff.sales-list', 'staff.pos-sales', 'staff.quotation-system', 'staff.quotation-list', 'staff.purchase-order-list', 'staff.grn', 'staff.add-customer-receipt', 'staff.list-customer-receipt', 'staff.add-supplier-receipt', 'staff.list-supplier-receipt', 'staff.return-add', 'staff.return-list', 'staff.return-supplier', 'staff.list-supplier-return', 'staff.due-payments', 'staff.payments-list', 'staff.expenses')">
                        @if(auth()->user()->hasPermission('menu_sales'))
                        <div class="erp-dropdown-header">Sales Voucher</div>
                        @if(auth()->user()->hasPermission('menu_sales_add'))
                        <a href="{{ route('staff.sales-system') }}" class="erp-dropdown-item {{ request()->routeIs('staff.sales-system') ? 'active' : '' }}">
                            <i class="bi bi-plus-circle"></i> Add
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_sales_list'))
                        <a href="{{ route('staff.sales-list') }}" class="erp-dropdown-item {{ request()->routeIs('staff.sales-list') ? 'active' : '' }}">
                            <i class="bi bi-list-ul"></i> List
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_sales_pos'))
                        <a href="{{ route('staff.pos-sales') }}" class="erp-dropdown-item {{ request()->routeIs('staff.pos-sales') ? 'active' : '' }}">
                            <i class="bi bi-shop"></i> POS Sales
                        </a>
                        @endif
                        @endif

                        @if(auth()->user()->hasPermission('menu_quotation'))
                        <div class="erp-dropdown-divider"></div>
                        <div class="erp-dropdown-header">Quotation</div>
                        @if(auth()->user()->hasPermission('menu_quotation_add'))
                        <a href="{{ route('staff.quotation-system') }}" class="erp-dropdown-item {{ request()->routeIs('staff.quotation-system') ? 'active' : '' }}">
                            <i class="bi bi-file-plus"></i> Add Quotation
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_quotation_list'))
                        <a href="{{ route('staff.quotation-list') }}" class="erp-dropdown-item {{ request()->routeIs('staff.quotation-list') ? 'active' : '' }}">
                            <i class="bi bi-card-list"></i> List Quotation
                        </a>
                        @endif
                        @endif

                        @if(auth()->user()->hasPermission('menu_purchase'))
                        <div class="erp-dropdown-divider"></div>
                        <div class="erp-dropdown-header">Purchase Voucher</div>
                        @if(auth()->user()->hasPermission('menu_purchase_order'))
                        <a href="{{ route('staff.purchase-order-list') }}" class="erp-dropdown-item {{ request()->routeIs('staff.purchase-order-list') ? 'active' : '' }}">
                            <i class="bi bi-list-ul"></i> Purchase Order List
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_purchase_grn'))
                        <a href="{{ route('staff.grn') }}" class="erp-dropdown-item {{ request()->routeIs('staff.grn') ? 'active' : '' }}">
                            <i class="bi bi-boxes"></i> GRN (Goods Receive)
                        </a>
                        @endif
                        @endif

                        @if(auth()->user()->hasPermission('menu_payment'))
                        <div class="erp-dropdown-divider"></div>
                        <div class="erp-dropdown-header">Receipt &amp; Payment Voucher</div>
                        <a href="{{ route('staff.due-payments') }}" class="erp-dropdown-item {{ request()->routeIs('staff.due-payments') ? 'active' : '' }}">
                            <i class="bi bi-cash-coin"></i> Add Payment
                        </a>
                        <a href="{{ route('staff.payments-list') }}" class="erp-dropdown-item {{ request()->routeIs('staff.payments-list') ? 'active' : '' }}">
                            <i class="bi bi-list-check"></i> Payment List
                        </a>
                        @if(auth()->user()->hasPermission('menu_payment_customer_receipt_add'))
                        <a href="{{ route('staff.add-customer-receipt') }}" class="erp-dropdown-item {{ request()->routeIs('staff.add-customer-receipt') ? 'active' : '' }}">
                            <i class="bi bi-plus-circle"></i> Add Customer Receipt
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_payment_customer_receipt_list'))
                        <a href="{{ route('staff.list-customer-receipt') }}" class="erp-dropdown-item {{ request()->routeIs('staff.list-customer-receipt') ? 'active' : '' }}">
                            <i class="bi bi-list-ul"></i> List Customer Receipt
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_payment_supplier_add'))
                        <a href="{{ route('staff.add-supplier-receipt') }}" class="erp-dropdown-item {{ request()->routeIs('staff.add-supplier-receipt') ? 'active' : '' }}">
                            <i class="bi bi-plus-circle"></i> Add Supplier Payment
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_payment_supplier_list'))
                        <a href="{{ route('staff.list-supplier-receipt') }}" class="erp-dropdown-item {{ request()->routeIs('staff.list-supplier-receipt') ? 'active' : '' }}">
                            <i class="bi bi-list-ul"></i> List Supplier Payment
                        </a>
                        @endif
                        @endif

                        @if(auth()->user()->hasPermission('menu_expenses'))
                        <div class="erp-dropdown-divider"></div>
                        <div class="erp-dropdown-header">Journal / Expense Voucher</div>
                        @if(auth()->user()->hasPermission('menu_expenses_list'))
                        <a href="{{ route('staff.expenses') }}" class="erp-dropdown-item {{ request()->routeIs('staff.expenses') ? 'active' : '' }}">
                            <i class="bi bi-wallet2"></i> Expenses
                        </a>
                        @endif
                        @endif

                        @if(auth()->user()->hasPermission('menu_return'))
                        <div class="erp-dropdown-divider"></div>
                        <div class="erp-dropdown-header">Returns (Credit / Debit Note)</div>
                        @if(auth()->user()->hasPermission('menu_return_customer_add'))
                        <a href="{{ route('staff.return-add') }}" class="erp-dropdown-item {{ request()->routeIs('staff.return-add') ? 'active' : '' }}">
                            <i class="bi bi-arrow-return-left"></i> Sales Return (Credit Note)
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_return_customer_list'))
                        <a href="{{ route('staff.return-list') }}" class="erp-dropdown-item {{ request()->routeIs('staff.return-list') ? 'active' : '' }}">
                            <i class="bi bi-list-check"></i> List Sales Returns
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_return_supplier_add'))
                        <a href="{{ route('staff.return-supplier') }}" class="erp-dropdown-item {{ request()->routeIs('staff.return-supplier') ? 'active' : '' }}">
                            <i class="bi bi-arrow-return-left"></i> Purchase Return (Debit Note)
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_return_supplier_list'))
                        <a href="{{ route('staff.list-supplier-return') }}" class="erp-dropdown-item {{ request()->routeIs('staff.list-supplier-return') ? 'active' : '' }}">
                            <i class="bi bi-list-check"></i> List Purchase Returns
                        </a>
                        @endif
                        @endif
                    </x-erp-nav-dropdown>
                    @endif

                    {{-- ╔══════════════════════════════════════╗ --}}
                    {{-- ║  3. INVENTORY                        ║ --}}
                    {{-- ╚══════════════════════════════════════╝ --}}
                    @if(auth()->user()->hasPermission('menu_products'))
                    <x-erp-nav-dropdown label="Inventory" icon="box-seam" :active="request()->routeIs('staff.Productes', 'staff.Product-brand', 'staff.Product-category', 'staff.Product-model')">
                        <div class="erp-dropdown-header">Item Master</div>
                        @if(auth()->user()->hasPermission('menu_products_list'))
                        <a href="{{ route('staff.Productes') }}" class="erp-dropdown-item {{ request()->routeIs('staff.Productes') ? 'active' : '' }}">
                            <i class="bi bi-card-list"></i> Item List
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_products_brand'))
                        <a href="{{ route('staff.Product-brand') }}" class="erp-dropdown-item {{ request()->routeIs('staff.Product-brand') ? 'active' : '' }}">
                            <i class="bi bi-tags"></i> Brand Master
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_products_category'))
                        <a href="{{ route('staff.Product-category') }}" class="erp-dropdown-item {{ request()->routeIs('staff.Product-category') ? 'active' : '' }}">
                            <i class="bi bi-tags-fill"></i> Category Master
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_products_model'))
                        <a href="{{ route('staff.Product-model') }}" class="erp-dropdown-item {{ request()->routeIs('staff.Product-model') ? 'active' : '' }}">
                            <i class="bi bi-cpu"></i> Model Master
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_products_variant'))
                        <a href="{{ route('admin.manage-variants') }}" class="erp-dropdown-item">
                            <i class="bi bi-layers"></i> Item Variants
                        </a>
                        @endif
                    </x-erp-nav-dropdown>
                    @endif

                    {{-- ╔══════════════════════════════════════╗ --}}
                    {{-- ║  4. DISPLAY                          ║ --}}
                    {{-- ╚══════════════════════════════════════╝ --}}
                    @if(auth()->user()->hasPermission('menu_reports') || auth()->user()->hasPermission('menu_analytics'))
                    <x-erp-nav-dropdown label="Display" icon="bar-chart-line" :active="request()->routeIs('staff.reports', 'staff.analytics')">
                        <div class="erp-dropdown-header">Reports</div>
                        @if(auth()->user()->hasPermission('menu_reports'))
                        <a href="{{ route('staff.reports') }}" class="erp-dropdown-item {{ request()->routeIs('staff.reports') ? 'active' : '' }}">
                            <i class="bi bi-file-earmark-bar-graph"></i> Account Reports
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('menu_analytics'))
                        <a href="{{ route('staff.analytics') }}" class="erp-dropdown-item {{ request()->routeIs('staff.analytics') ? 'active' : '' }}">
                            <i class="bi bi-bar-chart"></i> Analytics
                        </a>
                        @endif
                    </x-erp-nav-dropdown>
                    @endif

                    {{-- ╔══════════════════════════════════════╗ --}}
                    {{-- ║  5. ADMINISTRATION                   ║ --}}
                    {{-- ╚══════════════════════════════════════╝ --}}
                    @if(auth()->user()->hasPermission('menu_settings'))
                    <x-erp-nav-dropdown label="Administration" icon="gear-wide-connected" :active="request()->routeIs('staff.settings')">
                        <div class="erp-dropdown-header">Configuration</div>
                        <a href="{{ route('staff.settings') }}" class="erp-dropdown-item {{ request()->routeIs('staff.settings') ? 'active' : '' }}">
                            <i class="bi bi-gear"></i> System Settings
                        </a>
                    </x-erp-nav-dropdown>
                    @endif

                </ul>

                {{-- Right Side Controls --}}
                <div class="erp-right">
                    {{-- Shortcut Help Toggle --}}
                    <button class="erp-user-btn" onclick="window.erpShortcuts && window.erpShortcuts.toggleHelp()" title="Keyboard Shortcuts (Ctrl+/)" type="button" style="padding:4px 8px;">
                        <i class="bi bi-keyboard" style="font-size:0.85rem;"></i>
                    </button>

                    {{-- User Dropdown --}}
                    <div class="erp-user-dropdown" x-data="{ userOpen: false }" @click.away="userOpen = false">
                        <button class="erp-user-btn" @click="userOpen = !userOpen" type="button">
                            <div class="erp-user-avatar">S</div>
                            <span class="d-none d-xl-inline" style="font-size:0.78rem;">Staff</span>
                            <i class="bi bi-chevron-down" style="font-size:0.5rem;opacity:0.5;"></i>
                        </button>
                        <div class="erp-dropdown erp-dropdown-right erp-user-menu" x-show="userOpen" x-transition x-cloak style="display:none;">
                            <a href="{{ route('profile.show') }}" class="erp-dropdown-item">
                                <i class="bi bi-person"></i> My Profile
                            </a>
                            <a href="{{ route('staff.settings') }}" class="erp-dropdown-item">
                                <i class="bi bi-gear"></i> Settings
                            </a>
                            <div class="erp-dropdown-divider"></div>
                            <form method="POST" action="{{ route('logout') }}" class="m-0">
                                @csrf
                                <button type="submit" class="erp-dropdown-item erp-dropdown-danger">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        {{-- Old sidebar (hidden by CSS — preserved for rollback) --}}
        <div class="sidebar">
            <div class="sidebar-header d-flex justify-content-center">
                <div class="sidebar-title">
                    <img src="{{ asset('images/RNZ.png') }}" alt="Logo" width="250">
                </div>
            </div>
            <hr style="border-color: var(--border); margin: 10px 0;">

            <ul class="nav flex-column">
                {{-- Dashboard --}}
                @if(auth()->user()->hasPermission('menu_dashboard'))
                <li>
                    <a class="nav-link {{ request()->routeIs('staff.dashboard') ? 'active' : '' }}" href="{{ route('staff.dashboard') }}">
                        <i class="bi bi-speedometer2"></i> <span>Overview</span>
                    </a>
                </li>
                @endif

                {{-- Products Menu --}}
                @if(auth()->user()->hasPermission('menu_products'))
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" href="#inventorySubmenu" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="inventorySubmenu">
                        <i class="bi bi-basket3"></i> <span>Products</span>
                    </a>
                    <div class="collapse" id="inventorySubmenu">
                        <ul class="nav flex-column ms-3">
                            @if(auth()->user()->hasPermission('menu_products_list'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.Productes') }}">
                                    <i class="bi bi-card-list"></i> <span>List Product</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_products_brand'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.Product-brand') }}">
                                    <i class="bi bi-tags"></i> <span>Product Brand</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_products_category'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.Product-category') }}">
                                    <i class="bi bi-tags-fill"></i> <span>Product Category</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_products_model'))
                            <li class="nav-item">
                                <a class="nav-link py-2 {{ request()->routeIs('staff.Product-model') ? 'active' : '' }}"
                                   href="{{ route('staff.Product-model') }}">
                                    <i class="bi bi-cpu"></i> <span>Product Model</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_products_variant'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('admin.manage-variants') }}">
                                    <i class="bi bi-layers"></i> <span>Product Variant</span>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </li>
                @endif

                {{-- Sales Menu --}}
                @if(auth()->user()->hasPermission('menu_sales'))
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" href="#salesSubmenu" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="salesSubmenu">
                        <i class="bi bi-cash-stack"></i> <span>Sales</span>
                    </a>
                    <div class="collapse" id="salesSubmenu">
                        <ul class="nav flex-column ms-3">
                            @if(auth()->user()->hasPermission('menu_sales_add'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.sales-system') }}">
                                    <i class="bi bi-plus-circle"></i> <span>Add Sales</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_sales_list'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.sales-list') }}">
                                    <i class="bi bi-table"></i> <span>List Sales</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_sales_pos'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.pos-sales') }}">
                                    <i class="bi bi-shop"></i> <span>POS Sales</span>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </li>
                @endif

                {{-- Quotation Menu --}}
                @if(auth()->user()->hasPermission('menu_quotation'))
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" href="#stockSubmenu" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="stockSubmenu">
                        <i class="bi bi-file-earmark-text"></i> <span>Quotation</span>
                    </a>
                    <div class="collapse" id="stockSubmenu">
                        <ul class="nav flex-column ms-3">
                            @if(auth()->user()->hasPermission('menu_quotation_add'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.quotation-system') }}">
                                    <i class="bi bi-file-plus"></i> <span>Add Quotation</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_quotation_list'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.quotation-list') }}">
                                    <i class="bi bi-card-list"></i> <span>List Quotation</span>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </li>
                @endif

                {{-- Purchase Menu --}}
                @if(auth()->user()->hasPermission('menu_purchase'))
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" href="#purchaseSubmenu" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="purchaseSubmenu">
                        <i class="bi bi-truck"></i><span>Purchase</span>
                    </a>
                    <div class="collapse" id="purchaseSubmenu">
                        <ul class="nav flex-column ms-3">
                            @if(auth()->user()->hasPermission('menu_purchase_order'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.purchase-order-list') }}">
                                    <i class="bi bi-journal-bookmark"></i> <span>Purchase Order</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_purchase_grn'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.grn') }}">
                                    <i class="bi bi-boxes"></i><span>GRN</span>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </li>
                @endif

                {{-- Return Menu --}}
                @if(auth()->user()->hasPermission('menu_return'))
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" href="#returnSubmenu" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="returnSubmenu">
                        <i class="bi bi-arrow-counterclockwise"></i> <span>Return</span>
                    </a>
                    <div class="collapse" id="returnSubmenu">
                        <ul class="nav flex-column ms-3">
                            @if(auth()->user()->hasPermission('menu_return_customer_add'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.return-add') }}">
                                    <i class="bi bi-arrow-return-left"></i> <span>Add Customer Return</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_return_customer_list'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.return-list') }}">
                                    <i class="bi bi-list-check"></i> <span>List Customer Return</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_return_supplier_add'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.return-supplier') }}">
                                    <i class="bi bi-arrow-return-left"></i> <span>Add Supplier Return</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_return_supplier_list'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.list-supplier-return') }}">
                                    <i class="bi bi-list-check"></i> <span>List Supplier Return</span>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </li>
                @endif

                {{-- Customer Menu --}}
                @if(auth()->user()->hasPermission('menu_customer'))
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" href="#customerSubmenu" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="customerSubmenu">
                        <i class="bi bi-people"></i> <span>Customer</span>
                    </a>
                    <div class="collapse" id="customerSubmenu">
                        <ul class="nav flex-column ms-3">
                            @if(auth()->user()->hasPermission('menu_customer_add') || auth()->user()->hasPermission('menu_customer_list'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.manage-customers') }}">
                                    <i class="bi bi-people-fill"></i> <span>Manage Customers</span>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </li>
                @endif

                {{-- Supplier Menu --}}
                @if(auth()->user()->hasPermission('menu_supplier'))
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" href="#supplierSubmenu" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="supplierSubmenu">
                        <i class="bi bi-truck"></i> <span>Supplier</span>
                    </a>
                    <div class="collapse" id="supplierSubmenu">
                        <ul class="nav flex-column ms-3">
                            @if(auth()->user()->hasPermission('menu_supplier_add') || auth()->user()->hasPermission('menu_supplier_list'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.supplier-management') }}">
                                    <i class="bi bi-truck"></i> <span>Supplier Management</span>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </li>
                @endif

                {{-- Cheque/Banks Menu --}}
                @if(auth()->user()->hasPermission('menu_banks'))
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" href="#banksSubmenu" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="banksSubmenu">
                        <i class="bi bi-bank"></i> <span>Cheque / Banks</span>
                    </a>
                    <div class="collapse" id="banksSubmenu">
                        <ul class="nav flex-column ms-3">
                            @if(auth()->user()->hasPermission('menu_banks_deposit'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.income') }}">
                                    <i class="bi bi-cash-stack"></i> <span>Deposit By Cash</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_banks_cheque_list'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.cheque-list') }}">
                                    <i class="bi bi-card-text"></i> <span>Cheque List</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_banks_return_cheque'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.return-cheque') }}">
                                    <i class="bi bi-arrow-left-right"></i> <span>Return Cheque</span>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </li>
                @endif

                {{-- Expenses Menu --}}
                @if(auth()->user()->hasPermission('menu_expenses'))
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" href="#expensesSubmenu" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="expensesSubmenu">
                        <i class="bi bi-wallet2"></i> <span>Expenses</span>
                    </a>
                    <div class="collapse" id="expensesSubmenu">
                        <ul class="nav flex-column ms-3">
                            @if(auth()->user()->hasPermission('menu_expenses_list'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.expenses') }}">
                                    <i class="bi bi-wallet2"></i> <span>List Expenses</span>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </li>
                @endif

                {{-- Payment Management Menu --}}
                @if(auth()->user()->hasPermission('menu_payment'))
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" href="#paymentSubmenu" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="paymentSubmenu">
                        <i class="bi bi-receipt-cutoff"></i> <span>Payment Management</span>
                    </a>
                    <div class="collapse" id="paymentSubmenu">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.due-payments') }}">
                                    <i class="bi bi-cash-coin"></i> <span>Add Payment</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.payments-list') }}">
                                    <i class="bi bi-list-check"></i> <span>Payment List</span>
                                </a>
                            </li>
                            @if(auth()->user()->hasPermission('menu_payment_customer_receipt_add'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.add-customer-receipt') }}">
                                    <i class="bi bi-person-plus"></i> <span>Add Customer Receipt</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_payment_customer_receipt_list'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.list-customer-receipt') }}">
                                    <i class="bi bi-people-fill"></i> <span>List Customer Receipt</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_payment_supplier_add'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.add-supplier-receipt') }}">
                                    <i class="bi bi-truck-flatbed"></i> <span>Add Supplier Payment</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_payment_supplier_list'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.list-supplier-receipt') }}">
                                    <i class="bi bi-clipboard-data"></i> <span>List Supplier Payment</span>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </li>
                @endif

                {{-- People Menu --}}
                @if(auth()->user()->hasPermission('menu_people'))
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" href="#peopleSubmenu" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="peopleSubmenu">
                        <i class="bi bi-people-fill"></i> <span>People</span>
                    </a>
                    <div class="collapse" id="peopleSubmenu">
                        <ul class="nav flex-column ms-3">
                            @if(auth()->user()->hasPermission('menu_people_suppliers'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.supplier-management') }}">
                                    <i class="bi bi-people"></i> <span>List Suppliers</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_people_customers'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.manage-customers') }}">
                                    <i class="bi bi-person-lines-fill"></i> <span>List Customer</span>
                                </a>
                            </li>
                            @endif
                            @if(auth()->user()->hasPermission('menu_people_staff'))
                            <li class="nav-item">
                                <a class="nav-link py-2" href="{{ route('staff.manage-staff') }}">
                                    <i class="bi bi-person-badge"></i> <span>List Staff</span>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </li>
                @endif

                {{-- POS 
                @if(auth()->user()->hasPermission('menu_pos'))
                <li>
                    <a class="nav-link" href="{{ route('staff.billing') }}">
                        <i class="bi bi-cash"></i> <span>POS</span>
                    </a>
                </li>
                @endif
                --}}

                {{-- Reports --}}
                @if(auth()->user()->hasPermission('menu_reports'))
                <li>
                    <a class="nav-link" href="{{ route('staff.reports') }}">
                        <i class="bi bi-file-earmark-bar-graph"></i> <span>Reports</span>
                    </a>
                </li>
                @endif

                {{-- Analytics --}}
                @if(auth()->user()->hasPermission('menu_analytics'))
                <li>
                    <a class="nav-link" href="{{ route('staff.analytics') }}">
                        <i class="bi bi-bar-chart"></i> <span>Analytics</span>
                    </a>
                </li>
                @endif

                {{-- <li>
                    <a class="nav-link" href="{{ route('staff.settings') }}">
                        <i class="bi bi-gear"></i> <span>Settings</span>
                    </a>
                </li> --}}
            </ul>
        </div>

        <!-- Top Navigation Bar -->
        <nav class="top-bar">
            <button id="sidebarToggler" class="sidebar-toggle ms-n3">
                <i class="bi bi-list fs-5"></i>
            </button>

            <div class="topbar-brand">
                RNZ
            </div>

            <div class="dropdown ms-auto">
                <div class="admin-info dropdown-toggle" id="adminDropdown" role="button" data-bs-toggle="dropdown"
                    aria-expanded="false">
                    <div class="admin-avatar">S</div>
                    <div class="admin-name">Staff</div>
                </div>

                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.show') }}">
                            <i class="bi bi-person me-2"></i>My Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('staff.settings') }}">
                            <i class="bi bi-gear me-2"></i>Settings
                        </a>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}" class="mb-0">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </nav>
        <!-- Main Content -->
        <main class="main-content">
            {{ $slot }}
        </main>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 from CDN (only need this one line) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Include jQuery (required by Bootstrap 4 modal) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Define all elements once
            const sidebarToggler = document.getElementById('sidebarToggler');
            const sidebar = document.querySelector('.sidebar');
            const topBar = document.querySelector('.top-bar');
            const mainContent = document.querySelector('.main-content');

            // Tab Switching Functionality
            const tabs = document.querySelectorAll('.content-tab');
            if (tabs.length > 0) {
                tabs.forEach(tab => {
                    tab.addEventListener('click', function() {
                        // Remove active class from all tabs
                        tabs.forEach(t => t.classList.remove('active'));

                        // Add active class to clicked tab
                        this.classList.add('active');

                        // Hide all tab contents
                        document.querySelectorAll('.tab-content').forEach(content => {
                            content.classList.remove('active');
                        });

                        // Show the selected tab content
                        const tabId = this.getAttribute('data-tab');
                        document.getElementById(tabId).classList.add('active');
                    });
                });
            }

            // Improved menu activation logic
            function setActiveMenu() {
                const currentPath = window.location.pathname;
                let activeSubmenuFound = false;

                // First, check all menu links in the sidebar
                document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                    // Reset all links to inactive state first
                    link.classList.remove('active');

                    // Get the link's href attribute
                    const href = link.getAttribute('href');
                    if (href && href !== '#' && !href.startsWith('#')) {
                        // Extract just the path portion of the href
                        const hrefPath = href.replace(/^(https?:\/\/[^\/]+)/, '').split('?')[0];

                        // Use more precise path matching logic
                        const isActive = currentPath === hrefPath ||
                            (currentPath.startsWith(hrefPath + '/') && hrefPath !== '/') ||
                            (currentPath === hrefPath + '.php');

                        if (isActive) {
                            // This link is active
                            link.classList.add('active');

                            // If this is a submenu link, expand and highlight the parent menu
                            const submenu = link.closest('.collapse');
                            if (submenu) {
                                activeSubmenuFound = true;

                                // Add 'show' class to submenu to keep it expanded
                                submenu.classList.add('show');

                                // Find and activate the parent dropdown toggle
                                const parentToggle = document.querySelector(`[data-bs-toggle="collapse"][href="#${submenu.id}"]`);
                                if (parentToggle) {
                                    parentToggle.classList.add('active');
                                    parentToggle.setAttribute('aria-expanded', 'true');
                                }
                            }
                        }
                    }
                });

                // If no submenu item is active, check if we need to activate a main nav item
                if (!activeSubmenuFound) {
                    // Get the route base path segments (e.g., /staff/billing → ["staff", "billing"])
                    const pathSegments = currentPath.split('/').filter(Boolean);

                    // Only check main items if we have path segments
                    if (pathSegments.length > 0) {
                        document.querySelectorAll('.sidebar > .sidebar-content > .nav > .nav-item > .nav-link:not(.dropdown-toggle)').forEach(link => {
                            const href = link.getAttribute('href');
                            if (href && href !== '#') {
                                const hrefPath = href.replace(/^(https?:\/\/[^\/]+)/, '').split('?')[0];
                                const hrefSegments = hrefPath.split('/').filter(Boolean);

                                // Only match exact routes or next level child routes
                                const isActive = hrefPath === currentPath ||
                                    (hrefSegments.length > 0 &&
                                        pathSegments.length > 0 &&
                                        hrefSegments[hrefSegments.length - 1] === pathSegments[pathSegments.length - 1]);

                                if (isActive) {
                                    link.classList.add('active');
                                }
                            }
                        });
                    }
                }
            }

            // Call the improved function instead of the old ones
            setActiveMenu();

            // Initialize sidebar state based on screen size
            function initializeSidebar() {
                // Existing code...
            }

            // Toggle sidebar function - unified for mobile and desktop
            function toggleSidebar(event) {
                if (event) {
                    event.stopPropagation();
                }

                const isMobile = window.innerWidth < 768;

                if (isMobile) {
                    // Mobile behavior - toggle show class
                    sidebar.classList.toggle('show');

                    // Ensure no collapsed classes are present on mobile
                    sidebar.classList.remove('collapsed');
                    topBar.classList.remove('collapsed');
                    mainContent.classList.remove('collapsed');
                } else {
                    // Desktop behavior - toggle collapsed classes
                    sidebar.classList.toggle('collapsed');
                    topBar.classList.toggle('collapsed');
                    mainContent.classList.toggle('collapsed');

                    // Save state to localStorage
                    localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
                }
            }

            // Adjust sidebar height
            function adjustSidebarHeight() {
                if (sidebar) {
                    // Ensure sidebar takes full viewport height
                    sidebar.style.height = `${window.innerHeight}px`;

                    // Check if content is taller than viewport
                    const sidebarNav = sidebar.querySelector('.nav.flex-column');
                    if (sidebarNav) {
                        const needsScroll = sidebarNav.scrollHeight > window.innerHeight;
                        if (needsScroll) {
                            sidebar.classList.add('scrollable');
                        } else {
                            sidebar.classList.remove('scrollable');
                        }
                    }
                }
            }

            // Initialize sidebar
            if (sidebar) {
                initializeSidebar();

                // Attach toggle event listener (single source of truth)
                if (sidebarToggler) {
                    sidebarToggler.addEventListener('click', toggleSidebar);
                }

                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function(event) {
                    const isMobile = window.innerWidth < 768;
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggler = sidebarToggler && sidebarToggler.contains(event.target);

                    if (isMobile &&
                        sidebar.classList.contains('show') &&
                        !isClickInsideSidebar &&
                        !isClickOnToggler) {
                        sidebar.classList.remove('show');
                    }
                });

                // Handle window resize - switch between mobile and desktop modes
                window.addEventListener('resize', function() {
                    const wasMobile = mainContent.style.marginLeft === '0px' || mainContent.style.marginLeft === '';
                    const isMobile = window.innerWidth < 768;

                    // Only run when crossing the mobile/desktop threshold
                    if (wasMobile !== isMobile) {
                        initializeSidebar();
                    }
                });

                // Adjust sidebar height initially and on resize
                adjustSidebarHeight();
                window.addEventListener('resize', adjustSidebarHeight);

                // Fix submenu scroll visibility
                const dropdownToggles = document.querySelectorAll('.nav-link.dropdown-toggle');
                dropdownToggles.forEach(toggle => {
                    toggle.addEventListener('click', function(event) {
                        // Wait for submenu to fully appear
                        setTimeout(() => {
                            const submenu = this.nextElementSibling;
                            if (submenu && submenu.classList.contains('show')) {
                                // Check if submenu bottom is out of view
                                const submenuRect = submenu.getBoundingClientRect();
                                const sidebarRect = sidebar.getBoundingClientRect();

                                if (submenuRect.bottom > sidebarRect.bottom) {
                                    // Scroll to make submenu visible
                                    submenu.scrollIntoView({
                                        behavior: 'smooth',
                                        block: 'end'
                                    });
                                }
                            }
                        }, 300);
                    });
                });
            }
        });
    </script>

    {{-- ERP Top Navigation: Mobile menu logic --}}
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const staffMenu = document.getElementById('staffErpMenu');
        if (!staffMenu) return;

        staffMenu.querySelectorAll('.erp-menu-link[href], .erp-dropdown-item[href]').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) staffMenu.classList.remove('show');
            });
        });

        document.addEventListener('click', function(e) {
            if (window.innerWidth < 992 && staffMenu.classList.contains('show')) {
                const nav = document.querySelector('.erp-topnav');
                if (nav && !nav.contains(e.target)) staffMenu.classList.remove('show');
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992 && staffMenu.classList.contains('show')) staffMenu.classList.remove('show');
        });
    });
    </script>

    {{-- ===== BUSY-style ERP Shortcut System ===== --}}
    <script src="{{ asset('js/erp-shortcuts.js') }}"></script>

    {{-- ===== ERP Top Navigation Keyboard Controller ===== --}}
    <script src="{{ asset('js/erp-nav-keyboard.js') }}"></script>

    @stack('scripts')
</body>

</html>