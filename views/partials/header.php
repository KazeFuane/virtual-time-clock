<?php
$currentUser = AuthMiddleware::getCurrentUser();
$searchPeople = $currentUser && in_array($currentUser['role'], ['administrator', 'manager'], true);

// Pages the header search can open. Each has its label, plus extra words
// (in English and in the user's language) that also find it.
$searchPages = [];
if ($currentUser) {
    $base = '/virtual-time-clock/public';
    $page = fn(string $label, string $url, string $words = '') => [
        'label' => html_entity_decode($label, ENT_QUOTES, 'UTF-8'),
        'url'   => $url,
        'words' => html_entity_decode($words, ENT_QUOTES, 'UTF-8'),
    ];
    $searchPages = [
        $page(t('Virtual Time Clock'), "$base/dashboard.php", 'clock in out start end workday break dashboard home ' . t('clock in out start end workday break dashboard home')),
        $page(t('My History'), "$base/history.php", 'history past days hours worked ' . t('history past days hours worked')),
        $page(t('Summary of time errors'), "$base/time-errors.php", 'time errors mistakes report ' . t('time errors mistakes report')),
        $page($searchPeople ? t('Schedules') : t('My Schedule'), "$base/schedule.php", 'schedule shifts planning roster ' . t('schedule shifts planning roster')),
    ];
    if ($searchPeople) {
        array_push($searchPages,
            $page(t('Admin Console'), "$base/admin/index.php", 'admin users accounts staff attendance ' . t('admin users accounts staff attendance')),
            $page(t('Time Errors'), "$base/admin/time-errors.php", 'time errors agents reports mistakes ' . t('time errors agents reports mistakes')),
            $page(t('Correct Times'), "$base/admin/time-corrections.php", 'correct fix edit times corrections ' . t('correct fix edit times corrections')),
            $page(t('Leave &amp; PTO'), "$base/admin/leaves.php", 'leave pto vacation holiday sick absence ' . t('leave pto vacation holiday sick absence')),
            $page(t('Plan Schedules'), "$base/schedule.php", 'plan schedules shifts team roster ' . t('plan schedules shifts team roster')),
        );
    }
    array_push($searchPages,
        $page(t('Quality Knowledge'), "$base/placeholder.php?module=Quality%20Knowledge"),
        $page(t('Quality Grid'), "$base/placeholder.php?module=Quality%20Grid"),
        $page(t('Communication'), "$base/placeholder.php?module=Communication"),
        $page(t('Back Office Tools'), "$base/placeholder.php?module=Back%20Office%20Tools"),
        $page(t('Production Monitoring'), "$base/placeholder.php?module=Production%20Monitoring"),
        $page(t('Help'), "$base/placeholder.php?module=Help", 'help support ' . t('help support')),
        $page(t('Privacy & Compliance'), "$base/placeholder.php?module=Privacy%20%26%20Compliance"),
    );
}
?>
<header class="h-14 border-b border-[#261936] bg-[#140c1d] px-4 md:px-6 flex items-center justify-between shrink-0">
    <!-- Breadcrumb Area -->
    <div class="flex items-center gap-2 text-xs md:text-sm text-gray-400">
        <button id="mobile-sidebar-toggle" class="md:hidden me-2 p-1 text-gray-400 hover:text-white focus:outline-none">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <?php if (!empty($breadcrumbs) && is_array($breadcrumbs)): ?>
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php if ($index > 0): ?>
                    <span class="text-gray-600 font-semibold rtl-flip">&rsaquo;</span>
                <?php endif; ?>
                <?php if (!empty($crumb['url']) && $index < count($breadcrumbs) - 1): ?>
                    <a href="<?= htmlspecialchars($crumb['url']) ?>" class="hover:text-purple-400 transition font-medium whitespace-nowrap"><?= htmlspecialchars($crumb['label']) ?></a>
                <?php else: ?>
                    <span class="text-gray-200 font-medium whitespace-nowrap"><?= htmlspecialchars($crumb['label']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <span class="text-gray-200 font-medium"><?= htmlspecialchars(APP_NAME) ?></span>
        <?php endif; ?>
    </div>

    <!-- Right Controls: Search & User Profile -->
    <div class="flex items-center gap-3 md:gap-4">
        <!-- Search: pages for everyone, plus agents for managers and administrators -->
        <?php if ($currentUser): ?>
        <div class="relative hidden sm:block" id="gs-root">
            <input type="text" id="gs-input" autocomplete="off" spellcheck="false"
                   role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="gs-panel"
                   placeholder="<?= $searchPeople ? t('Search agents or pages...') : t('Search pages...') ?>"
                   aria-label="<?= $searchPeople ? t('Search agents or pages...') : t('Search pages...') ?>"
                   title="<?= t('Press / to search') ?>"
                   class="w-40 md:w-56 bg-[#1f152b] text-xs text-gray-200 placeholder-gray-500 rounded-full ps-3 pe-8 py-1.5 border border-[#312246] focus:outline-none focus:border-purple-500 transition">
            <svg class="w-3.5 h-3.5 text-gray-400 absolute end-2.5 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <div id="gs-panel" role="listbox" class="hidden absolute end-0 mt-2 w-80 max-w-[calc(100vw-2rem)] max-h-[70vh] overflow-y-auto bg-[#1a1228] border border-[#2d1e44] rounded-lg shadow-xl text-xs z-50"></div>
        </div>
        <script>
        (function () {
            const PAGES = <?= json_encode($searchPages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const PEOPLE = <?= $searchPeople ? 'true' : 'false' ?>;
            const API = "/virtual-time-clock/api/search.php";
            const BASE = "/virtual-time-clock/public";
            const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
            // Lowercase and drop accents, so "cong" finds "Congés" and "jose" finds "José"
            const norm = s => String(s ?? "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();

            const isId = q => /^#?\d+$/.test(q);
            let input, panel, items = [], active = -1, people = [], timer = null, seq = 0, loading = false;

            function matchPages(q) {
                if (!q) return [];
                const words = q.split(/\s+/);
                return PAGES.map(p => {
                    const label = norm(p.label), all = label + " " + norm(p.words);
                    if (!words.every(w => all.includes(w))) return null;
                    return { p, score: label.startsWith(q) ? 0 : label.includes(q) ? 1 : 2 };
                }).filter(Boolean).sort((a, b) => a.score - b.score).slice(0, 6).map(x => x.p);
            }

            // Where each person result can go. Time errors and the default
            // click only apply to employees (the report covers employees only).
            function links(u) {
                const out = [];
                if (u.role === "employee") out.push({ label: t("Time errors"), url: `${BASE}/admin/time-errors.php?search=${encodeURIComponent(u.username)}` });
                out.push({ label: t("Schedule"), url: `${BASE}/schedule.php?search=${encodeURIComponent(u.username)}` });
                out.push({ label: t("Correct Times"), url: `${BASE}/admin/time-corrections.php?user_id=${u.id}` });
                out.push({ label: t("Leave &amp; PTO").replace(/&amp;/g, "&"), url: `${BASE}/admin/leaves.php?user_id=${u.id}` });
                return out;
            }

            const ROW = "flex justify-between gap-2 px-3 py-2 rounded-md cursor-pointer";
            const HEAD = "px-3 pt-2.5 pb-1 text-[10px] uppercase font-bold tracking-wider text-purple-400";

            function render() {
                const q = norm(input.value);
                if (!q) { close(); return; }
                const pages = matchPages(q);
                let html = "";
                if (PEOPLE) {
                    html += `<div class="${HEAD}">${esc(t("Agents"))}</div>`;
                    if (q.length < 2 && !isId(q)) {
                        html += `<div class="px-3 py-2 text-gray-500">${esc(t("Type 2 letters or an ID to find an agent."))}</div>`;
                    } else if (loading && !people.length) {
                        html += `<div class="px-3 py-2 text-gray-500">${esc(t("Searching..."))}</div>`;
                    } else if (!people.length) {
                        html += `<div class="px-3 py-2 text-gray-500">${esc(t("No agents found."))}</div>`;
                    } else {
                        html += `<div class="px-1 pb-1">` + people.map(u => {
                            const l = links(u);
                            const initials = (u.name.split(" ").map(w => w[0] || "").join("").slice(0, 2) || "?").toUpperCase();
                            return `<div data-gs-item data-url="${esc(l[0].url)}" class="${ROW}" role="option">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <div class="w-7 h-7 shrink-0 rounded-full bg-gradient-to-tr from-purple-600 to-indigo-500 text-white flex items-center justify-center font-bold text-[10px]">${esc(initials)}</div>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-white truncate">${esc(u.name)}</div>
                                        <div class="text-[11px] text-gray-500"><span class="font-mono inline-block">@${esc(u.username)}</span> · <span class="font-mono inline-block">${esc(t("ID {id}", { id: u.id }))}</span>${u.role !== "employee" ? ` · ${esc(t(u.role))}` : ""}</div>
                                        <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1">${l.map(x => `<a href="${esc(x.url)}" class="text-purple-300 hover:text-purple-200 hover:underline">${esc(x.label)}</a>`).join("")}</div>
                                    </div>
                                </div>
                            </div>`;
                        }).join("") + `</div>`;
                    }
                }
                // Managers searching a name don't need an empty "Pages" section
                if (pages.length || !PEOPLE) html += `<div class="${HEAD} ${PEOPLE ? "border-t border-[#2d1e44]" : ""}">${esc(t("Pages"))}</div>`;
                html += pages.length
                    ? `<div class="px-1 pb-1">` + pages.map(p => `<div data-gs-item data-url="${esc(p.url)}" class="${ROW} items-center" role="option">
                            <span class="text-gray-200 truncate">${esc(p.label)}</span>
                            <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>`).join("") + `</div>`
                    : PEOPLE ? "" : `<div class="px-3 pb-2.5 pt-1 text-gray-500">${esc(t("No pages found."))}</div>`;
                panel.innerHTML = html;
                items = [...panel.querySelectorAll("[data-gs-item]")];
                items.forEach((el, i) => {
                    el.addEventListener("mouseenter", () => highlight(i));
                    el.addEventListener("click", e => { if (!e.target.closest("a")) go(el.dataset.url); });
                });
                highlight(items.length ? 0 : -1);
                panel.classList.remove("hidden");
                input.setAttribute("aria-expanded", "true");
            }

            function highlight(i) {
                active = i;
                items.forEach((el, n) => {
                    el.classList.toggle("bg-[#26173a]", n === i);
                    el.setAttribute("aria-selected", n === i ? "true" : "false");
                });
                if (items[i]) items[i].scrollIntoView({ block: "nearest" });
            }

            function close() {
                panel.classList.add("hidden");
                input.setAttribute("aria-expanded", "false");
                items = []; active = -1;
            }

            function go(url) { if (url) window.location.href = url; }

            async function findPeople() {
                const q = input.value.trim();
                const mine = ++seq;
                if (!PEOPLE || (q.length < 2 && !isId(q))) { people = []; loading = false; render(); return; }
                loading = true; render();
                try {
                    const res = await fetch(`${API}?q=${encodeURIComponent(q)}`, { credentials: "same-origin", headers: { "Accept": "application/json" } });
                    const json = await res.json();
                    if (mine !== seq) return; // a newer search is on its way
                    people = json.success ? json.data.people : [];
                } catch (e) {
                    if (mine !== seq) return;
                    people = [];
                }
                loading = false;
                render();
            }

            document.addEventListener("DOMContentLoaded", () => {
                input = document.getElementById("gs-input");
                panel = document.getElementById("gs-panel");

                input.addEventListener("input", () => {
                    people = [];
                    render();
                    clearTimeout(timer);
                    timer = setTimeout(findPeople, 200);
                });
                input.addEventListener("focus", () => { if (input.value.trim()) render(); });
                input.addEventListener("keydown", e => {
                    if (e.key === "ArrowDown" && items.length) { e.preventDefault(); highlight((active + 1) % items.length); }
                    else if (e.key === "ArrowUp" && items.length) { e.preventDefault(); highlight((active - 1 + items.length) % items.length); }
                    else if (e.key === "Enter") { e.preventDefault(); if (items[active]) go(items[active].dataset.url); }
                    else if (e.key === "Escape") { if (!panel.classList.contains("hidden")) close(); else { input.value = ""; input.blur(); } }
                });
                document.addEventListener("click", e => {
                    if (!document.getElementById("gs-root").contains(e.target)) close();
                });
                // "/" (or Ctrl+K) jumps to the search bar from anywhere on the page
                document.addEventListener("keydown", e => {
                    const tag = (document.activeElement && document.activeElement.tagName) || "";
                    const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(tag) || (document.activeElement && document.activeElement.isContentEditable);
                    if ((e.key === "/" && !typing) || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k")) {
                        if (input.offsetParent === null) return; // hidden on small screens
                        e.preventDefault();
                        input.focus();
                        input.select();
                    }
                });
            });
        })();
        </script>
        <?php endif; ?>

        <!-- Light / dark mode switch -->
        <button type="button" data-theme-toggle class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-[#221530] transition focus:outline-none" title="<?= t('Switch to light mode') ?>" aria-label="<?= t('Switch to light mode') ?>" data-label-light="<?= t('Switch to light mode') ?>" data-label-dark="<?= t('Switch to dark mode') ?>">
            <svg data-theme-icon="sun" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <svg data-theme-icon="moon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
        </button>

        <!-- Language picker -->
        <?php include ROOT_PATH . '/views/partials/language-picker.php'; ?>

        <?php if ($currentUser && in_array($currentUser['role'], ['administrator', 'manager'], true)): ?>
            <!-- Notifications: time error reports sent by agents -->
            <div class="relative" id="notif-root">
                <button type="button" id="notif-btn" class="relative p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-[#221530] transition focus:outline-none" title="<?= t('Reports from agents') ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <span id="notif-badge" class="hidden absolute -top-0.5 -end-0.5 min-w-[16px] h-4 px-1 rounded-full bg-red-600 text-white text-[10px] font-bold leading-4 text-center"></span>
                </button>
                <div id="notif-panel" class="hidden absolute end-0 mt-2 w-80 max-w-[calc(100vw-2rem)] bg-[#1a1228] border border-[#2d1e44] rounded-lg shadow-xl text-xs z-50">
                    <div class="px-4 py-2.5 border-b border-[#2d1e44] flex items-center justify-between">
                        <span class="font-semibold text-white"><?= t('Reports from agents') ?></span>
                        <span id="notif-count" class="text-gray-400"></span>
                    </div>
                    <div id="notif-list" class="max-h-96 overflow-y-auto divide-y divide-[#2d1e44]">
                        <div class="px-4 py-6 text-center text-gray-500"><?= t('Loading...') ?></div>
                    </div>
                    <a href="/virtual-time-clock/public/admin/time-errors.php" class="block px-4 py-2.5 border-t border-[#2d1e44] text-center text-purple-300 hover:bg-[#26173a] rounded-b-lg"><?= t('See all reports') ?></a>
                </div>
            </div>
            <script>
            (function () {
                const API = "/virtual-time-clock/api/admin/time-error-reports.php?status=OPEN";
                const EDIT = "/virtual-time-clock/public/admin/time-corrections.php";
                const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
                const ago = ts => {
                    const when = new Date(String(ts).replace(" ", "T"));
                    const m = Math.max(0, Math.round((Date.now() - when) / 60000));
                    if (isNaN(m)) return "";
                    if (m < 1) return t("just now");
                    if (m < 60) return t("{n} min ago", { n: m });
                    const h = Math.round(m / 60);
                    return h < 24 ? t("{n} h ago", { n: h }) : t("{n} d ago", { n: Math.round(h / 24) });
                };
                const store = {
                    get() { try { return parseInt(localStorage.getItem("notif-seen") || "0", 10) || 0; } catch (e) { return 0; } },
                    set(v) { try { localStorage.setItem("notif-seen", String(v)); } catch (e) {} },
                };
                let reports = [], lastMax = null;

                function render() {
                    const seen = store.get();
                    const unseen = reports.filter(r => +r.id > seen).length;
                    const badge = document.getElementById("notif-badge");
                    badge.textContent = unseen > 9 ? "9+" : unseen;
                    badge.classList.toggle("hidden", unseen === 0);
                    document.getElementById("notif-count").textContent = reports.length ? t("{n} waiting", { n: reports.length }) : "";
                    document.getElementById("notif-list").innerHTML = reports.length ? reports.slice(0, 20).map(r => `
                        <div class="px-4 py-3 ${+r.id > seen ? "bg-purple-950/30" : ""}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-white truncate">${esc(r.first_name)} ${esc(r.last_name)}</span>
                                <span class="text-[10px] text-gray-500 shrink-0">${esc(ago(r.created_at))}</span>
                            </div>
                            <div class="text-[11px] mt-0.5"><span class="text-red-300">${esc(r.error_label)}</span> <span class="text-gray-500 font-mono">${esc(r.work_date)}</span></div>
                            <div class="text-[11px] text-gray-300 mt-1 line-clamp-2">"${esc(r.message)}"</div>
                            <a href="${EDIT}?user_id=${r.user_id}&date=${esc(r.work_date)}&report_id=${r.id}" class="inline-block mt-1.5 text-purple-300 hover:underline">${t("Correct times")} &rarr;</a>
                        </div>`).join("")
                        : `<div class="px-4 py-6 text-center text-gray-500">${t("No reports waiting.")}</div>`;
                }

                async function load() {
                    try {
                        const res = await fetch(API, { credentials: "same-origin", headers: { "Accept": "application/json" } });
                        const json = await res.json();
                        if (!json.success) return;
                        reports = json.data.reports;
                        const max = reports.reduce((m, r) => Math.max(m, +r.id), 0);
                        if (lastMax !== null && max > lastMax && typeof showToast === "function") {
                            showToast(t("New report from an agent."), "info");
                        }
                        lastMax = max;
                        render();
                    } catch (e) { /* offline or signed out: try again later */ }
                }

                document.addEventListener("DOMContentLoaded", () => {
                    const btn = document.getElementById("notif-btn");
                    const panel = document.getElementById("notif-panel");
                    btn.addEventListener("click", e => {
                        e.stopPropagation();
                        panel.classList.toggle("hidden");
                        if (!panel.classList.contains("hidden")) {
                            // Opening the panel marks everything shown as seen
                            const max = reports.reduce((m, r) => Math.max(m, +r.id), 0);
                            if (max > store.get()) { render(); store.set(max); setTimeout(render, 4000); }
                        }
                    });
                    document.addEventListener("click", e => {
                        if (!document.getElementById("notif-root").contains(e.target)) panel.classList.add("hidden");
                    });
                    load();
                    setInterval(load, 60000);
                });
            })();
            </script>
        <?php endif; ?>

        <!-- User profile widget -->
        <?php if ($currentUser): ?>
            <div class="relative">
                <button type="button" id="user-menu-btn" class="flex items-center gap-2.5 hover:opacity-90 focus:outline-none py-1">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-purple-600 to-indigo-500 text-white flex items-center justify-center font-bold text-xs uppercase shadow-inner">
                        <?= strtoupper(substr($currentUser['first_name'] ?? $currentUser['username'], 0, 1) . substr($currentUser['last_name'] ?? '', 0, 1)) ?>
                    </div>
                    <div class="text-start hidden md:block">
                        <div class="text-xs font-semibold text-white leading-tight">
                            <?= htmlspecialchars(($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?: $currentUser['username']) ?>
                        </div>
                        <div class="text-[10px] text-purple-400 capitalize leading-tight">
                            <?= htmlspecialchars(t($currentUser['role'] ?? 'Agent')) ?>
                        </div>
                    </div>
                    <svg class="w-3.5 h-3.5 text-gray-400 hidden md:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7 7"/></svg>
                </button>

                <!-- Dropdown Menu -->
                <div id="user-menu-dropdown" class="hidden absolute end-0 mt-2 w-48 bg-[#1a1228] border border-[#2d1e44] rounded-lg shadow-xl py-1 text-xs z-50">
                    <div class="px-4 py-2 border-b border-[#2d1e44] md:hidden">
                        <p class="font-medium text-white"><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></p>
                        <p class="text-[11px] text-gray-400"><?= htmlspecialchars($currentUser['email']) ?></p>
                    </div>
                    <a href="/virtual-time-clock/public/history.php" class="block px-4 py-2 text-gray-300 hover:bg-[#26173a] hover:text-white transition"><?= t('My History') ?></a>
                    <?php if (in_array($currentUser['role'], ['administrator', 'manager'])): ?>
                        <a href="/virtual-time-clock/public/admin/index.php" class="block px-4 py-2 text-gray-300 hover:bg-[#26173a] hover:text-white transition"><?= t('Admin Panel') ?></a>
                    <?php endif; ?>
                    <button type="button" class="w-full text-start px-4 py-2 text-red-400 hover:bg-[#26173a] hover:text-red-300 transition border-t border-[#2d1e44] btn-trigger-logout"><?= t('Sign Out') ?></button>
                </div>
            </div>

            <!-- Prominent Quick Sign Out button in header -->
            <button type="button" class="btn-trigger-logout hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-900/60 bg-red-950/40 text-red-300 hover:bg-red-900/60 hover:text-white text-xs font-medium transition" title="<?= t('Log out from session') ?>">
                <svg class="w-3.5 h-3.5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                <span class="whitespace-nowrap"><?= t('Sign Out') ?></span>
            </button>
        <?php endif; ?>
    </div>
</header>
