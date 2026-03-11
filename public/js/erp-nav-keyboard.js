/**
 * ============================================================
 *  RNZ ERP — Top Navigation Keyboard Controller
 * ============================================================
 *
 *  Arrow Key Behaviour:
 *    Left / Right   → move between top-level menu items
 *    Down / Enter   → open dropdown and focus first item
 *    Up / Down      → move through open dropdown items
 *    Up (first row) → close dropdown, return to menu button
 *    Escape         → close dropdown, return to menu button
 *    Left / Right   → (inside dropdown) close and move to prev/next menu
 *
 *  Auto-focus:
 *    On pages with data-erp-context="home" the first menu button
 *    is focused automatically after page load.
 *
 *  Works alongside Alpine.js — opens/closes via the button click
 *  so Alpine's x-show state stays in sync.
 * ============================================================
 */
(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var nav = document.querySelector(".erp-topnav .erp-menu");
        if (!nav) return;

        /* ── helpers ─────────────────────────────────────── */

        function getTopItems() {
            return Array.from(nav.querySelectorAll(":scope > .erp-menu-item"));
        }

        /** Button or link that represents the top-level menu trigger */
        function getItemBtn(item) {
            return item.querySelector("button.erp-menu-link, a.erp-menu-link");
        }

        /** The dropdown panel inside this top item */
        function getDropdownEl(item) {
            return item.querySelector(".erp-dropdown");
        }

        /** True if the dropdown is currently visible */
        function isOpen(item) {
            var dd = getDropdownEl(item);
            if (!dd) return false;
            return window.getComputedStyle(dd).display !== "none";
        }

        /** Focusable link/button rows inside an open dropdown (excludes headers, dividers & hidden sub-panel items) */
        function getDropdownLinks(item) {
            var dd = getDropdownEl(item);
            if (!dd) return [];
            return Array.from(
                dd.querySelectorAll(
                    "a.erp-dropdown-item, button.erp-dropdown-item",
                ),
            ).filter(function (el) {
                // Skip items inside collapsed sub-panels
                var subPanel = el.closest(".erp-sub-panel");
                if (subPanel && !subPanel.classList.contains("show"))
                    return false;
                return true;
            });
        }

        /* ── roving tabindex on top-level buttons ─────────── */

        function setRovingFocus(items, activeIdx) {
            items.forEach(function (item, i) {
                var btn = getItemBtn(item);
                if (btn)
                    btn.setAttribute("tabindex", i === activeIdx ? "0" : "-1");
            });
        }

        /* Initialise: first item is tab-reachable, rest are -1 so arrow keys manage them */
        var items = getTopItems();
        items.forEach(function (item, i) {
            var btn = getItemBtn(item);
            if (btn) btn.setAttribute("tabindex", i === 0 ? "0" : "-1");
        });

        /* Remove dropdown links from natural tab order — arrow keys handle them */
        items.forEach(function (item) {
            getDropdownLinks(item).forEach(function (link) {
                link.setAttribute("tabindex", "-1");
            });
        });

        /* ── open / close helpers (via click to keep Alpine in sync) ── */

        function openItem(item) {
            if (!isOpen(item)) {
                var btn = getItemBtn(item);
                if (btn) btn.click();
            }
        }

        function closeItem(item) {
            if (isOpen(item)) {
                var btn = getItemBtn(item);
                if (btn) btn.click();
            }
        }

        function closeAll() {
            getTopItems().forEach(function (item) {
                closeItem(item);
            });
        }

        /* Focus first dropdown link after a brief tick so x-transition finishes */
        function focusFirstLink(item) {
            setTimeout(function () {
                var links = getDropdownLinks(item);
                if (links[0]) links[0].focus();
            }, 60);
        }

        /* ── keyboard handler on the whole nav menu ─────── */

        nav.addEventListener("keydown", function (e) {
            if (
                [
                    "ArrowLeft",
                    "ArrowRight",
                    "ArrowUp",
                    "ArrowDown",
                    "Escape",
                    "Enter",
                    " ",
                ].indexOf(e.key) === -1
            )
                return;

            var items = getTopItems();
            var focused = document.activeElement;

            /* Which top item contains the currently focused element? */
            var curItem = null;
            var curIdx = -1;
            items.forEach(function (item, i) {
                if (item.contains(focused)) {
                    curItem = item;
                    curIdx = i;
                }
            });
            if (!curItem) return;

            var inDropdown =
                getDropdownEl(curItem) &&
                getDropdownEl(curItem).contains(focused);

            /* ── top-level button is focused ─────────────── */
            if (!inDropdown) {
                switch (e.key) {
                    case "ArrowRight": {
                        e.preventDefault();
                        closeItem(curItem);
                        var ni = (curIdx + 1) % items.length;
                        setRovingFocus(items, ni);
                        var nb = getItemBtn(items[ni]);
                        if (nb) nb.focus();
                        break;
                    }
                    case "ArrowLeft": {
                        e.preventDefault();
                        closeItem(curItem);
                        var pi = (curIdx - 1 + items.length) % items.length;
                        setRovingFocus(items, pi);
                        var pb = getItemBtn(items[pi]);
                        if (pb) pb.focus();
                        break;
                    }
                    case "ArrowDown":
                    case "Enter":
                    case " ": {
                        e.preventDefault();
                        openItem(curItem);
                        focusFirstLink(curItem);
                        break;
                    }
                    case "ArrowUp": {
                        /* Up on top item: open and jump to LAST item in dropdown */
                        e.preventDefault();
                        openItem(curItem);
                        setTimeout(function () {
                            var links = getDropdownLinks(curItem);
                            if (links.length) links[links.length - 1].focus();
                        }, 60);
                        break;
                    }
                    case "Escape": {
                        var anyOpen = getTopItems().some(function (it) {
                            return isOpen(it);
                        });
                        closeAll();
                        if (anyOpen) {
                            /* A dropdown was closed — don't also go back in history */
                            e.stopPropagation();
                        }
                        break;
                    }
                }
                return;
            }

            /* ── inside an open dropdown ─────────────────── */
            var links = getDropdownLinks(curItem);
            var ddIdx = links.indexOf(focused);

            switch (e.key) {
                case "ArrowDown": {
                    e.preventDefault();
                    var nextLink = links[(ddIdx + 1) % links.length];
                    if (nextLink) nextLink.focus();
                    break;
                }
                case "ArrowUp": {
                    e.preventDefault();
                    if (ddIdx <= 0) {
                        /* First item → close and return focus to menu button */
                        closeItem(curItem);
                        var btn = getItemBtn(curItem);
                        if (btn) btn.focus();
                    } else {
                        links[ddIdx - 1].focus();
                    }
                    break;
                }
                case "ArrowRight": {
                    e.preventDefault();
                    closeItem(curItem);
                    var nxi = (curIdx + 1) % items.length;
                    setRovingFocus(items, nxi);
                    var nxb = getItemBtn(items[nxi]);
                    if (nxb) {
                        nxb.focus();
                        openItem(items[nxi]);
                        focusFirstLink(items[nxi]);
                    }
                    break;
                }
                case "ArrowLeft": {
                    e.preventDefault();
                    closeItem(curItem);
                    var pri = (curIdx - 1 + items.length) % items.length;
                    setRovingFocus(items, pri);
                    var prb = getItemBtn(items[pri]);
                    if (prb) {
                        prb.focus();
                        openItem(items[pri]);
                        focusFirstLink(items[pri]);
                    }
                    break;
                }
                case "Escape": {
                    e.preventDefault();
                    e.stopPropagation(); /* handled here — don't also go back in history */

                    /* ── Level 3 → Level 2: focused inside a sub-panel ── */
                    var subPanel = focused.closest(".erp-sub-panel");
                    if (subPanel) {
                        /* Find the toggle button whose data-sub matches this panel's id */
                        var subToggle = curItem.querySelector(
                            '[data-sub="' + subPanel.id + '"]',
                        );
                        if (subToggle) {
                            subToggle.click(); /* closes the sub-panel */
                            setTimeout(function () {
                                subToggle.focus();
                            }, 20);
                        }
                    } else {
                        /* ── Level 2 → Level 1: at a plain dropdown item ── */
                        closeItem(curItem);
                        var rb = getItemBtn(curItem);
                        if (rb) rb.focus();
                    }
                    break;
                }
                case "Enter": {
                    /* Enter on link will follow it naturally; let browser handle */
                    break;
                }
            }
        });

        /* ── update roving tabindex when focusing a button directly ── */
        items.forEach(function (item, i) {
            var btn = getItemBtn(item);
            if (btn) {
                btn.addEventListener("focus", function () {
                    setRovingFocus(getTopItems(), i);
                });
            }
        });

        /* ── auto-focus first menu item on home screen ───── */
        var context = (
            document.body.getAttribute("data-erp-context") || ""
        ).toLowerCase();
        if (context === "home") {
            setTimeout(function () {
                var first = getItemBtn(getTopItems()[0]);
                if (first) first.focus();
            }, 400);
        }
    }); /* end DOMContentLoaded */

    /* ── ESC = go back one page (global, except on home) ────
     *
     *  Fires only when:
     *   - Not typing in an input / textarea / select
     *   - No Bootstrap modal is open
     *   - Help overlay is NOT visible (erp-shortcuts.js handles that
     *     via a capture listener that calls stopPropagation, so this
     *     listener will never see that keydown)
     *   - Current page is NOT the home/dashboard screen
     *      (data-erp-context="home")
     *
     *  When a nav dropdown is open, the nav keydown listener (above)
     *  calls e.stopPropagation() so this global handler is skipped —
     *  one ESC press closes the dropdown without also navigating back.
     * ────────────────────────────────────────────────────── */
    document.addEventListener(
        "keydown",
        function (e) {
            if (e.key !== "Escape") return;

            /* skip if user is typing */
            var active = document.activeElement;
            if (active) {
                var tag = active.tagName.toLowerCase();
                if (tag === "input" || tag === "textarea" || tag === "select")
                    return;
                if (active.isContentEditable) return;
            }

            /* skip if a Bootstrap modal is open */
            if (document.querySelector(".modal.show")) return;

            /* skip on the home / dashboard screen */
            var ctx = (
                document.body.getAttribute("data-erp-context") || ""
            ).toLowerCase();
            if (ctx === "home") return;

            e.preventDefault();
            window.history.back();
        },
        false,
    );
})();
