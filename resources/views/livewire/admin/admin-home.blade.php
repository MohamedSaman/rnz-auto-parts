<div data-erp-page="home">
    {{-- BUSY-style Blank Home Screen --}}
    <div class="erp-home-screen">
        <div class="erp-home-center">
            {{-- Logo --}}
            <div class="erp-home-logo">
                <img src="{{ asset('images/RNZ.png') }}" alt="RNZ Auto Parts" style="max-height:80px; opacity:0.7;">
            </div>

            {{-- Company Name --}}
            <h1 class="erp-home-title">RNZ Auto Parts</h1>
            <p class="erp-home-subtitle">Enterprise Resource Management System</p>

            {{-- Quick-access info --}}
            <div class="erp-home-date">
                <i class="bi bi-calendar3"></i>
                {{ now()->format('l, d F Y') }}
            </div>

            {{-- Shortcut Hints --}}
            <div class="erp-home-hints">
                <div class="erp-home-hint-row">
                    <kbd>F5</kbd> <span>POS / Sale Voucher</span>
                </div>
                <div class="erp-home-hint-row">
                    <kbd>Alt+Ctrl+F1</kbd> <span>Customers</span>
                </div>
                <div class="erp-home-hint-row">
                    <kbd>Alt+Ctrl+F2</kbd> <span>Item Master</span>
                </div>
                <div class="erp-home-hint-row">
                    <kbd>Alt+Ctrl+F6</kbd> <span>Receipt Voucher</span>
                </div>
                <div class="erp-home-hint-row">
                    <kbd>Ctrl+/</kbd> <span>All Keyboard Shortcuts</span>
                </div>
            </div>
        </div>
    </div>

    <style>
        .erp-home-screen {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 48px);
            background: #f8fafc;
        }
        .erp-home-center {
            text-align: center;
            padding: 40px;
        }
        .erp-home-logo {
            margin-bottom: 20px;
        }
        .erp-home-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 4px;
            letter-spacing: -0.02em;
        }
        .erp-home-subtitle {
            font-size: 0.85rem;
            color: #94a3b8;
            margin: 0 0 24px;
            font-weight: 400;
        }
        .erp-home-date {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 32px;
        }
        .erp-home-date i { margin-right: 6px; }
        .erp-home-hints {
            display: inline-flex;
            flex-direction: column;
            gap: 8px;
            text-align: left;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px 28px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .erp-home-hint-row {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.78rem;
            color: #475569;
        }
        .erp-home-hint-row kbd {
            display: inline-block;
            min-width: 90px;
            text-align: center;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 2px 8px;
            font-size: 0.68rem;
            font-family: monospace;
            color: #334155;
        }
    </style>
</div>
