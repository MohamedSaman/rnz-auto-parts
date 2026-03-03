<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Purchase Create' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/hardmenicon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* === Admin theme tokens (mirrors admin.blade.php) === */
        :root {
            --page-bg: #f8fafc;
            --surface: #ffffff;
            --primary: #e11d48;
            --primary-600: #be123c;
            --primary-700: #9f1239;
            --primary-50: #fff1f2;
            --primary-100: #ffe4e6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #0ea5e9;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --sidebar-bg: #0f172a;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / .05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / .10), 0 2px 4px -2px rgb(0 0 0 / .10);
            --radius-md: 10px;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--page-bg);
            color: var(--text-main);
            letter-spacing: -0.01em;
            -webkit-font-smoothing: antialiased;
            margin: 0; padding: 0;
            min-height: 100vh;
            font-size: 13px;
        }
        .container-fluid, .card, .modal-content { font-size: 13px !important; }
        .table th, .table td { font-size: 12px !important; padding: 0.35rem 0.5rem !important; }
        .form-control, .form-select { font-size: 12px !important; padding: 0.35rem 0.5rem !important; }
        .btn, .btn-sm { font-size: 12px !important; padding: 0.25rem 0.6rem !important; }
        .badge { font-size: 11px !important; padding: 0.25em 0.5em !important; }
        .card { border-radius: 8px !important; box-shadow: 0 2px 6px rgba(0,0,0,.06) !important; border: 1px solid var(--border) !important; }
        .btn-primary { background-color: var(--primary) !important; border-color: var(--primary) !important; color: #fff !important; }
        .btn-primary:hover { background-color: var(--primary-600) !important; border-color: var(--primary-600) !important; }
        .btn-primary:disabled { background-color: var(--primary) !important; border-color: var(--primary) !important; opacity: 0.65; }
        .text-primary { color: var(--primary) !important; }
        .modal-header { padding-top: 0.6rem !important; padding-bottom: 0.6rem !important; }
    </style>
    @stack('styles')
    @livewireStyles
</head>
<body>
    {{ $slot }}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    @livewireScripts
    @stack('scripts')
</body>
</html>
