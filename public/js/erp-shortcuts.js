/**
 * ============================================================
 *  RNZ ERP — BUSY-Style Keyboard Shortcut System
 * ============================================================
 *
 *  Two-tier shortcut architecture:
 *    TIER 1: General Hotkeys — active everywhere except input fields
 *    TIER 2: Voucher Feeding Hotkeys — active only inside voucher pages
 *
 *  Context detection:
 *    - 'home'      — blank home screen
 *    - 'voucher'   — any voucher/form page (sales, purchase, receipt, etc.)
 *    - 'display'   — report / display pages
 *    - 'list'      — list / table pages
 *    - 'general'   — everything else
 *
 *  Usage:
 *    In your layout:  <script src="{{ asset('js/erp-shortcuts.js') }}"></script>
 *    The <body> element should have `data-erp-context="voucher"` (etc.) to set context.
 *    If not set, defaults to 'general'.
 *
 *  Extensibility:
 *    window.erpShortcuts.register({ key, ctrl, alt, shift, context, handler, label })
 *    window.erpShortcuts.unregister(id)
 *
 *  Alpine.js integration:
 *    Dispatches 'erp-shortcut' custom event when a shortcut fires,
 *    so Alpine components can listen via @erp-shortcut.window="...".
 * ============================================================
 */
(function () {
    "use strict";

    /* ─── helpers ──────────────────────────────────────────── */
    function isTyping(e) {
        var t = e.target || e.srcElement;
        if (!t) return false;
        var tag = t.tagName.toLowerCase();
        return (
            tag === "input" ||
            tag === "textarea" ||
            tag === "select" ||
            t.isContentEditable
        );
    }

    function getContext() {
        return (
            document.body.getAttribute("data-erp-context") || "general"
        ).toLowerCase();
    }

    function makeId(def) {
        var parts = [];
        if (def.ctrl) parts.push("ctrl");
        if (def.alt) parts.push("alt");
        if (def.shift) parts.push("shift");
        parts.push(String(def.key).toLowerCase());
        if (def.context && def.context !== "*") parts.push("@" + def.context);
        return parts.join("+");
    }

    /* ─── Normalise e.key for cross-browser ────────────────── */
    // On Windows, Ctrl+Alt may act as AltGr and produce odd e.key values.
    // Use e.code as fallback for function keys and known letter keys.
    var codeToKey = {
        F1: "f1",
        F2: "f2",
        F3: "f3",
        F4: "f4",
        F5: "f5",
        F6: "f6",
        F7: "f7",
        F8: "f8",
        F9: "f9",
        F10: "f10",
        F11: "f11",
        F12: "f12",
        KeyA: "a",
        KeyB: "b",
        KeyC: "c",
        KeyD: "d",
        KeyE: "e",
        KeyF: "f",
        KeyG: "g",
        KeyH: "h",
        KeyI: "i",
        KeyJ: "j",
        KeyK: "k",
        KeyL: "l",
        KeyM: "m",
        KeyN: "n",
        KeyO: "o",
        KeyP: "p",
        KeyQ: "q",
        KeyR: "r",
        KeyS: "s",
        KeyT: "t",
        KeyU: "u",
        KeyV: "v",
        KeyW: "w",
        KeyX: "x",
        KeyY: "y",
        KeyZ: "z",
        PageUp: "pageup",
        PageDown: "pagedown",
        Escape: "escape",
        Enter: "enter",
        Backspace: "backspace",
        Delete: "delete",
        Tab: "tab",
        Space: "space",
        ArrowUp: "arrowup",
        ArrowDown: "arrowdown",
        ArrowLeft: "arrowleft",
        ArrowRight: "arrowright",
    };

    function normaliseKey(e) {
        // Prefer e.code mapping when modifiers are held (avoids AltGr issues)
        if (
            (e.ctrlKey || e.altKey || e.shiftKey) &&
            e.code &&
            codeToKey[e.code]
        ) {
            return codeToKey[e.code];
        }
        var k = e.key.toLowerCase();
        if (k === " ") return "space";
        return k;
    }

    /* ─── registry ────────────────────────────────────────── */
    var shortcuts = []; // array of { id, key, ctrl, alt, shift, contexts[], handler, label, tier }
    var helpOverlayVisible = false;

    function register(def) {
        var entry = {
            id: def.id || makeId(def),
            key: String(def.key).toLowerCase(),
            ctrl: !!def.ctrl,
            alt: !!def.alt,
            shift: !!def.shift,
            contexts:
                !def.context || def.context === "*"
                    ? ["*"]
                    : Array.isArray(def.context)
                      ? def.context
                      : [def.context],
            handler: def.handler || function () {},
            label: def.label || "",
            tier: def.tier || 1, // 1 = general, 2 = voucher feeding
        };
        // prevent duplicates
        for (var i = 0; i < shortcuts.length; i++) {
            if (shortcuts[i].id === entry.id) {
                shortcuts[i] = entry;
                return entry.id;
            }
        }
        shortcuts.push(entry);
        return entry.id;
    }

    function unregister(id) {
        shortcuts = shortcuts.filter(function (s) {
            return s.id !== id;
        });
    }

    /* ─── matcher ─────────────────────────────────────────── */
    function matchShortcut(e) {
        var ctx = getContext();
        var eventKey = normaliseKey(e);

        for (var i = 0; i < shortcuts.length; i++) {
            var s = shortcuts[i];
            if (s.key !== eventKey) continue;
            if (s.ctrl !== (!!e.ctrlKey || !!e.metaKey)) continue;
            if (s.alt !== !!e.altKey) continue;
            if (s.shift !== !!e.shiftKey) continue;
            // context check
            var contextOk = false;
            for (var j = 0; j < s.contexts.length; j++) {
                if (s.contexts[j] === "*" || s.contexts[j] === ctx) {
                    contextOk = true;
                    break;
                }
            }
            if (!contextOk) continue;
            return s;
        }
        return null;
    }

    /* ─── main handler ────────────────────────────────────── */
    function onKeyDown(e) {
        // Always allow help overlay toggle
        if (e.ctrlKey && (e.key === "/" || e.code === "Slash")) {
            e.preventDefault();
            toggleHelp();
            return;
        }
        if (helpOverlayVisible) {
            if (e.key === "Escape") {
                e.preventDefault();
                e.stopPropagation(); // prevent global ESC→history.back from also firing
                hideHelp();
            }
            return;
        }

        var match = matchShortcut(e);
        if (!match) return;

        // Tier-2 voucher shortcuts should NOT fire when user is typing (except F2=Save)
        if (match.tier === 2 && match.key !== "f2" && isTyping(e)) return;

        // Tier-1 general shortcuts should NOT fire when user is typing in fields
        if (match.tier === 1 && isTyping(e)) return;

        // preventDefault MUST happen immediately to stop the browser from
        // intercepting F-keys (F1 opens help, F5 refreshes, etc.) or
        // Alt+letter activating the browser menu bar on Windows.
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        // Dispatch custom event for Alpine.js listeners
        document.dispatchEvent(
            new CustomEvent("erp-shortcut", {
                detail: { id: match.id, label: match.label, key: match.key },
            }),
        );

        match.handler(e);
    }

    document.addEventListener("keydown", onKeyDown, true);

    /* ─── help overlay ────────────────────────────────────── */
    function toggleHelp() {
        helpOverlayVisible ? hideHelp() : showHelp();
    }

    function showHelp() {
        helpOverlayVisible = true;
        var existing = document.getElementById("erp-shortcut-help");
        if (existing) {
            existing.style.display = "flex";
            return;
        }
        buildHelpOverlay();
    }

    function hideHelp() {
        helpOverlayVisible = false;
        var el = document.getElementById("erp-shortcut-help");
        if (el) el.style.display = "none";
    }

    function buildHelpOverlay() {
        var overlay = document.createElement("div");
        overlay.id = "erp-shortcut-help";
        overlay.setAttribute(
            "style",
            "position:fixed;inset:0;background:rgba(15,23,42,0.88);backdrop-filter:blur(8px);" +
                "z-index:99999;display:flex;align-items:center;justify-content:center;animation:erpHelpIn 0.2s ease;",
        );
        overlay.addEventListener("click", function (ev) {
            if (ev.target === overlay) hideHelp();
        });

        var panel = document.createElement("div");
        panel.setAttribute(
            "style",
            "background:#1e293b;border:1px solid #334155;border-radius:16px;padding:2rem 2.5rem;" +
                "max-width:740px;width:95%;max-height:85vh;overflow-y:auto;color:#e2e8f0;" +
                "box-shadow:0 25px 60px rgba(0,0,0,0.5);",
        );

        var html =
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">' +
            '<h4 style="margin:0;color:#fff;font-weight:700;display:flex;align-items:center;gap:0.5rem;">' +
            '<i class="bi bi-keyboard" style="color:#e11d48;"></i> Keyboard Shortcuts</h4>' +
            '<button onclick="window.erpShortcuts.hideHelp()" style="background:rgba(255,255,255,0.1);border:none;color:#fff;border-radius:6px;padding:4px 8px;cursor:pointer;">' +
            '<i class="bi bi-x-lg"></i></button></div>';

        /* Group by tier */
        var tier1 = [],
            tier2 = [];
        for (var i = 0; i < shortcuts.length; i++) {
            if (shortcuts[i].tier === 2) tier2.push(shortcuts[i]);
            else tier1.push(shortcuts[i]);
        }

        html += renderSection("General Hotkeys (System-wide)", tier1);
        if (tier2.length)
            html += renderSection(
                "Voucher Feeding Hotkeys (Inside Forms)",
                tier2,
            );

        html +=
            '<div style="margin-top:1rem;font-size:0.72rem;color:#64748b;text-align:center;">' +
            'Press <kbd style="background:#0f172a;border:1px solid #475569;border-radius:4px;padding:1px 6px;font-size:0.7rem;color:#94a3b8;">Ctrl + /</kbd> to toggle &bull; ' +
            '<kbd style="background:#0f172a;border:1px solid #475569;border-radius:4px;padding:1px 6px;font-size:0.7rem;color:#94a3b8;">Esc</kbd> to close</div>';

        panel.innerHTML = html;
        overlay.appendChild(panel);
        document.body.appendChild(overlay);
    }

    function renderSection(title, items) {
        var h =
            '<div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#e11d48;margin:1.25rem 0 0.5rem;opacity:0.85;">' +
            title +
            "</div>";
        for (var i = 0; i < items.length; i++) {
            var s = items[i];
            if (!s.label) continue;
            var keys = [];
            if (s.ctrl) keys.push("Ctrl");
            if (s.alt) keys.push("Alt");
            if (s.shift) keys.push("Shift");
            keys.push(prettifyKey(s.key));
            var kbdHtml = keys
                .map(function (k) {
                    return (
                        '<kbd style="background:#0f172a;border:1px solid #475569;border-bottom:3px solid #334155;border-radius:5px;padding:2px 8px;font-size:0.72rem;font-family:monospace;color:#94a3b8;white-space:nowrap;">' +
                        k +
                        "</kbd>"
                    );
                })
                .join(" ");
            h +=
                '<div style="display:flex;align-items:center;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.82rem;">' +
                '<span style="color:#94a3b8;">' +
                s.label +
                "</span>" +
                '<span style="display:flex;gap:4px;flex-shrink:0;">' +
                kbdHtml +
                "</span></div>";
        }
        return h;
    }

    function prettifyKey(k) {
        var map = {
            f1: "F1",
            f2: "F2",
            f3: "F3",
            f4: "F4",
            f5: "F5",
            f6: "F6",
            f7: "F7",
            f8: "F8",
            f9: "F9",
            f10: "F10",
            f11: "F11",
            f12: "F12",
            pageup: "PgUp",
            pagedown: "PgDn",
            escape: "Esc",
            enter: "Enter",
            backspace: "Bksp",
            delete: "Del",
            tab: "Tab",
            arrowup: "↑",
            arrowdown: "↓",
            arrowleft: "←",
            arrowright: "→",
            " ": "Space",
            space: "Space",
        };
        return map[k] || k.toUpperCase();
    }

    /* ─── navigation helper ───────────────────────────────── */
    function goTo(url) {
        if (url) window.location.href = url;
    }
    function openInNewTab(url) {
        if (url) window.open(url, "_blank");
    }

    /* ─── public API ──────────────────────────────────────── */
    window.erpShortcuts = {
        register: register,
        unregister: unregister,
        getContext: getContext,
        showHelp: showHelp,
        hideHelp: hideHelp,
        toggleHelp: toggleHelp,
        goTo: goTo,
        openInNewTab: openInNewTab,
        getAll: function () {
            return shortcuts.slice();
        },
    };

    /* ============================================================
     *  TIER 1: GENERAL HOTKEYS (System-wide)
     * ============================================================
     *  Route URLs are injected from Blade via data attributes on <body>.
     *  Fallback: if data-erp-route-* not set, shortcut is a no-op.
     * ============================================================ */
    function route(name) {
        return document.body.getAttribute("data-erp-route-" + name) || "";
    }

    // F1 → Help
    register({
        key: "f1",
        label: "Help / Shortcut Reference",
        context: "*",
        handler: function () {
            showHelp();
        },
    });

    // Alt+Ctrl+F1 → Add New Account (Account Master)
    register({
        key: "f1",
        ctrl: true,
        alt: true,
        label: "Add New Account",
        context: "*",
        handler: function () {
            goTo(route("manage-customer"));
        },
    });

    // Alt+Ctrl+F2 → Add Item (Product / Item Master)
    register({
        key: "f2",
        ctrl: true,
        alt: true,
        label: "Add Item",
        context: "*",
        handler: function () {
            goTo(route("products"));
        },
    });

    // Alt+Ctrl+F3 → Add Voucher (generic)
    register({
        key: "f3",
        ctrl: true,
        alt: true,
        label: "Add Voucher",
        context: "*",
        handler: function () {
            goTo(route("sales-system"));
        },
    });

    // Alt+Ctrl+F5 → Add Payment Voucher
    register({
        key: "f5",
        ctrl: true,
        alt: true,
        label: "Add Payment Voucher",
        context: "*",
        handler: function () {
            goTo(route("add-supplier-receipt"));
        },
    });

    // Alt+Ctrl+F6 → Add Receipt Voucher
    register({
        key: "f6",
        ctrl: true,
        alt: true,
        label: "Add Receipt Voucher",
        context: "*",
        handler: function () {
            goTo(route("add-customer-receipt"));
        },
    });

    // Alt+Ctrl+F7 → Add Journal Voucher
    register({
        key: "f7",
        ctrl: true,
        alt: true,
        label: "Add Journal Voucher",
        context: "*",
        handler: function () {
            goTo(route("expenses"));
        },
    });

    // Alt+Ctrl+F8 → Add Sales Voucher
    register({
        key: "f8",
        ctrl: true,
        alt: true,
        label: "Add Sales Voucher",
        context: "*",
        handler: function () {
            goTo(route("sales-voucher-add"));
        },
    });

    // Alt+Ctrl+F9 → Add Purchase Voucher
    register({
        key: "f9",
        ctrl: true,
        alt: true,
        label: "Add Purchase Voucher",
        context: "*",
        handler: function () {
            goTo(route("purchase-create"));
        },
    });

    // Alt+Ctrl+A → Accounts Monthly Summary
    register({
        key: "a",
        ctrl: true,
        alt: true,
        label: "Accounts Monthly Summary",
        context: "*",
        handler: function () {
            goTo(route("reports"));
        },
    });

    // Alt+Ctrl+B → Balance Sheet (Profit & Loss)
    register({
        key: "b",
        ctrl: true,
        alt: true,
        label: "Balance Sheet",
        context: "*",
        handler: function () {
            goTo(route("profit-loss"));
        },
    });

    // Alt+Ctrl+L → Account Ledger
    register({
        key: "l",
        ctrl: true,
        alt: true,
        label: "Account Ledger",
        context: "*",
        handler: function () {
            goTo(route("reports"));
        },
    });

    // Alt+Ctrl+T → Trial Balance
    register({
        key: "t",
        ctrl: true,
        alt: true,
        label: "Trial Balance",
        context: "*",
        handler: function () {
            goTo(route("reports"));
        },
    });

    // Alt+Ctrl+S → Stock Status
    register({
        key: "s",
        ctrl: true,
        alt: true,
        label: "Stock Status",
        context: "*",
        handler: function () {
            goTo(route("products"));
        },
    });

    // Alt+V → VAT Summary
    register({
        key: "v",
        alt: true,
        label: "VAT Summary",
        context: "*",
        handler: function () {
            goTo(route("reports"));
        },
    });

    // F5 → Open POS Terminal (general context)
    register({
        key: "f5",
        label: "Open POS Terminal",
        context: ["general", "home", "list", "display"],
        handler: function () {
            if (typeof handlePOSClick === "function") handlePOSClick();
            else goTo(route("store-billing"));
        },
    });

    // Ctrl+/ → Toggle help (handled in main handler above, but register for help display)
    // Not registered as shortcut since it's handled specially.

    /* ============================================================
     *  TIER 2: VOUCHER FEEDING HOTKEYS (Inside Voucher Forms)
     * ============================================================ */

    // F2 → Save Voucher
    register({
        key: "f2",
        tier: 2,
        label: "Save Voucher",
        context: "voucher",
        handler: function () {
            // Find primary submit button and click it
            var btn = document.querySelector(
                '[data-erp-save], form button[type="submit"], .btn-primary[wire\\:click]',
            );
            if (btn) btn.click();
        },
    });

    // F4 → Standard Narration Help
    register({
        key: "f4",
        tier: 2,
        label: "Standard Narration Help",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-narration-help"));
        },
    });

    // F5 → List of Records (inside voucher)
    register({
        key: "f5",
        tier: 2,
        label: "List of Records",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-list-records"));
        },
    });

    // F6 → Change Voucher Type
    register({
        key: "f6",
        tier: 2,
        label: "Change Voucher Type",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-change-voucher-type"));
        },
    });

    // F7 / Alt+R → Repeat Last Value
    register({
        key: "f7",
        tier: 2,
        label: "Repeat Last Value",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-repeat-value"));
        },
    });
    register({
        key: "r",
        alt: true,
        tier: 2,
        label: "Repeat Last Value (Alt+R)",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-repeat-value"));
        },
    });

    // F8 → Delete Selected Voucher
    register({
        key: "f8",
        tier: 2,
        label: "Delete Voucher",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-delete-voucher"));
        },
    });

    // F9 → Delete Selected Row
    register({
        key: "f9",
        tier: 2,
        label: "Delete Selected Row",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-delete-row"));
        },
    });

    // F11 → Pick Data from Challans
    register({
        key: "f11",
        tier: 2,
        label: "Pick from Challans",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-pick-challan"));
        },
    });

    // F12 → Copy Voucher
    register({
        key: "f12",
        tier: 2,
        label: "Copy Voucher",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-copy-voucher"));
        },
    });

    // Alt+M → Modify Master
    register({
        key: "m",
        alt: true,
        tier: 2,
        label: "Modify Master",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-modify-master"));
        },
    });

    // Alt+P → Show MRP-wise Stock
    register({
        key: "p",
        alt: true,
        tier: 2,
        label: "MRP-wise Stock",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-mrp-stock"));
        },
    });

    // Alt+O → Show Pending Orders
    register({
        key: "o",
        alt: true,
        tier: 2,
        label: "Pending Orders",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-pending-orders"));
        },
    });

    // Page Up → Previous Record
    register({
        key: "pageup",
        tier: 2,
        label: "Previous Record",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-prev-record"));
        },
    });

    // Page Down → Next Record
    register({
        key: "pagedown",
        tier: 2,
        label: "Next Record",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-next-record"));
        },
    });

    // Alt+Ctrl+P → Print Voucher
    register({
        key: "p",
        ctrl: true,
        alt: true,
        tier: 2,
        label: "Print Voucher",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-print-voucher"));
        },
    });

    /* ─── BUSY-style Sales Voucher Navigation (Global) ─── */

    // Alt+A → Add Sales Voucher (from home/general context)
    register({
        key: "a",
        alt: true,
        tier: 1,
        label: "Add Sales Voucher",
        context: ["home", "general", "list"],
        handler: function () {
            var url = document.body.getAttribute(
                "data-erp-route-sales-voucher-add",
            );
            if (url) window.location.href = url;
        },
    });

    // Alt+M → Modify Sales Voucher (from home/general/list context)
    register({
        key: "m",
        alt: true,
        tier: 1,
        label: "Modify Sales Voucher",
        context: ["home", "general", "list"],
        handler: function () {
            var url = document.body.getAttribute(
                "data-erp-route-sales-voucher-modify",
            );
            if (url) window.location.href = url;
        },
    });

    // Alt+L → List Sales Vouchers (from home/general/voucher context)
    register({
        key: "l",
        alt: true,
        tier: 1,
        label: "List Sales Vouchers",
        context: ["home", "general", "voucher"],
        handler: function () {
            var url = document.body.getAttribute(
                "data-erp-route-sales-voucher-list",
            );
            if (url) window.location.href = url;
        },
    });

    // Alt+S → Save Voucher (dispatches event for Livewire to handle)
    register({
        key: "s",
        alt: true,
        tier: 2,
        label: "Save Voucher",
        context: "voucher",
        handler: function () {
            document.dispatchEvent(new CustomEvent("erp-save-voucher"));
        },
    });

    /* ─── BUSY-style Purchase Voucher Navigation (Global) ─── */

    // Alt+Shift+A → Add Purchase Voucher
    register({
        key: "a",
        alt: true,
        shift: true,
        tier: 1,
        label: "Add Purchase Voucher",
        context: ["home", "general", "list"],
        handler: function () {
            var url = document.body.getAttribute(
                "data-erp-route-purchase-create",
            );
            if (url) window.location.href = url;
        },
    });

    // Alt+Shift+M → Modify Purchase Voucher
    register({
        key: "m",
        alt: true,
        shift: true,
        tier: 1,
        label: "Modify Purchase Voucher",
        context: ["home", "general", "list"],
        handler: function () {
            var url = document.body.getAttribute(
                "data-erp-route-purchase-voucher-modify",
            );
            if (url) window.location.href = url;
        },
    });

    // Alt+Shift+L → List Purchase Vouchers
    register({
        key: "l",
        alt: true,
        shift: true,
        tier: 1,
        label: "List Purchase Vouchers",
        context: ["home", "general", "voucher"],
        handler: function () {
            var url = document.body.getAttribute(
                "data-erp-route-purchase-voucher-list",
            );
            if (url) window.location.href = url;
        },
    });
})();
