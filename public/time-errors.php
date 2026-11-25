<?php
/**
 * Summary of time errors
 * Employees see their own summary. Managers/admins can pick any agent.
 */
require_once __DIR__ . '/../src/bootstrap.php';

$currentUser = AuthMiddleware::requireAuth();
$isManager = in_array($currentUser['role'], ['administrator', 'manager'], true);

$pageTitle = t('Summary of time errors') . ' &mdash; ' . APP_NAME;
$breadcrumbs = [
    ['label' => t('Time Clock'), 'url' => '/virtual-time-clock/public/dashboard.php'],
    ['label' => t('Time Management'), 'url' => '#'],
    ['label' => t('Summary of time errors'), 'url' => '']
];

ob_start();
?>

<div class="max-w-6xl mx-auto space-y-5" data-manager="<?= $isManager ? '1' : '0' ?>" data-self="<?= (int) $currentUser['id'] ?>" id="te-root">

    <!-- Title -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-white tracking-wide"><?= t('Summary of time errors') ?></h1>
            <p class="text-xs text-gray-400 mt-1"><?= t('Every clock problem in the period, grouped by type. Click a row to see the days.') ?></p>
        </div>
        <?php if ($isManager): ?>
            <a href="/virtual-time-clock/public/admin/time-errors.php" class="text-xs text-purple-300 hover:text-purple-200"><?= t('All agents overview &rarr;') ?></a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="bg-[#140c1d] border border-[#251738] rounded-2xl p-4 sm:p-5 shadow-xl">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-4 h-4 text-purple-400" fill="currentColor" viewBox="0 0 24 24"><path d="M3 4h18l-7 8.5V19l-4 2v-8.5L3 4z"/></svg>
            <h2 class="text-sm font-semibold text-white"><?= t('Filters') ?></h2>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-end">
            <!-- Agent -->
            <div class="lg:col-span-4">
                <label class="block text-[11px] uppercase tracking-wider text-gray-500 mb-1.5"><?= t('Agent') ?></label>
                <?php if ($isManager): ?>
                    <select id="f-agent" class="w-full bg-[#1a1226] border border-[#2d1e44] text-sm text-gray-100 rounded-xl px-3 py-2 focus:outline-none focus:border-purple-500"></select>
                <?php else: ?>
                    <div class="w-full bg-[#1a1226] border border-[#2d1e44] text-sm text-gray-300 rounded-xl px-3 py-2">
                        <?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?>
                        <span class="text-gray-500 font-mono text-xs">@<?= htmlspecialchars($currentUser['username']) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Period type -->
            <div class="lg:col-span-3">
                <label class="block text-[11px] uppercase tracking-wider text-gray-500 mb-1.5"><?= t('Period') ?></label>
                <div id="f-mode" class="grid grid-cols-3 bg-[#1a1226] border border-[#2d1e44] rounded-xl p-1 text-xs font-semibold">
                    <button data-mode="week" class="py-1.5 rounded-lg text-gray-400 hover:text-white transition"><?= t('Weekly') ?></button>
                    <button data-mode="month" class="py-1.5 rounded-lg text-gray-400 hover:text-white transition"><?= t('Monthly') ?></button>
                    <button data-mode="custom" class="py-1.5 rounded-lg text-gray-400 hover:text-white transition"><?= t('Custom') ?></button>
                </div>
            </div>

            <!-- Navigator / dates -->
            <div class="lg:col-span-5">
                <div id="f-nav" class="flex items-center gap-2">
                    <button id="f-prev" class="shrink-0 w-9 h-9 grid place-items-center rounded-xl bg-[#1a1226] border border-[#2d1e44] text-gray-300 hover:text-white hover:border-purple-500 transition" aria-label="<?= t('Previous period') ?>">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <div id="f-label" class="flex-1 text-center text-sm font-semibold text-white bg-[#1a1226] border border-[#2d1e44] rounded-xl py-2"></div>
                    <button id="f-next" class="shrink-0 w-9 h-9 grid place-items-center rounded-xl bg-[#1a1226] border border-[#2d1e44] text-gray-300 hover:text-white hover:border-purple-500 transition" aria-label="<?= t('Next period') ?>">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
                <div id="f-custom" class="hidden flex items-center gap-2">
                    <input type="date" id="f-from" class="flex-1 min-w-0 bg-[#1a1226] border border-[#2d1e44] text-sm font-mono text-gray-100 rounded-xl px-3 py-1.5 focus:outline-none focus:border-purple-500">
                    <span class="text-gray-500 text-xs"><?= t('to') ?></span>
                    <input type="date" id="f-to" class="flex-1 min-w-0 bg-[#1a1226] border border-[#2d1e44] text-sm font-mono text-gray-100 rounded-xl px-3 py-1.5 focus:outline-none focus:border-purple-500">
                    <button id="f-ok" class="shrink-0 px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold transition"><?= t('OK') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary heading -->
    <div>
        <h2 id="s-title" class="text-base sm:text-lg font-semibold text-white">&nbsp;</h2>
        <p id="s-sub" class="text-xs text-gray-500 mt-0.5"></p>
    </div>

    <!-- KPI tiles -->
    <div id="s-kpis" class="grid grid-cols-2 lg:grid-cols-5 gap-3"></div>

    <!-- Events table -->
    <div class="bg-[#140c1d] border border-[#251738] rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#160e22] text-[11px] uppercase tracking-wider text-gray-400 border-b border-[#251738]">
                    <tr>
                        <th class="py-3 px-5 text-start font-semibold"><?= t('Event') ?></th>
                        <th class="py-3 px-5 text-start font-semibold w-1/3 hidden md:table-cell"><?= t('Share') ?></th>
                        <th class="py-3 px-5 text-end font-semibold"><?= t('Total') ?></th>
                        <th class="py-3 px-5 text-end font-semibold"><?= t('Total duration') ?> <span class="normal-case text-gray-500"><?= t('(hhh:mm:ss)') ?></span></th>
                    </tr>
                </thead>
                <tbody id="s-rows" class="divide-y divide-[#231733]">
                    <tr><td colspan="4" class="py-10 text-center text-xs text-gray-500"><?= t('Loading...') ?></td></tr>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3 border-t border-[#251738] flex items-center justify-between text-xs">
            <label class="flex items-center gap-2 text-gray-400 cursor-pointer select-none">
                <input type="checkbox" id="s-all" class="accent-purple-500"> <?= t('Show event types with no errors') ?>
            </label>
            <button id="s-other" class="hidden px-3 py-1.5 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 font-semibold"><?= t('Report another problem') ?></button>
        </div>
    </div>

    <!-- Reports -->
    <div class="bg-[#140c1d] border border-[#251738] rounded-2xl overflow-hidden shadow-xl">
        <div class="px-5 py-3 bg-[#1a1226] border-b border-[#251738]">
            <h3 id="r-title" class="text-xs font-semibold text-white uppercase tracking-wider"><?= t('Reports to manager') ?></h3>
        </div>
        <div id="r-list" class="divide-y divide-[#231733]"></div>
    </div>
</div>

<!-- Report modal -->
<div id="m" class="hidden fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
    <div class="bg-[#160e22] border border-[#2d1e44] rounded-2xl w-full max-w-md p-5 space-y-3 shadow-2xl">
        <h3 class="text-sm font-semibold text-white"><?= t('Report to my manager') ?></h3>
        <div id="m-info" class="text-xs text-gray-300"></div>
        <div>
            <label class="block text-[11px] text-gray-400 mb-1"><?= t('Date') ?></label>
            <input type="date" id="m-date" class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white">
        </div>
        <div>
            <label class="block text-[11px] text-gray-400 mb-1"><?= t('What happened? (for example: "I left at 17:00 but forgot to clock out")') ?></label>
            <textarea id="m-msg" rows="4" maxlength="1000" class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white"></textarea>
        </div>
        <div class="pt-2 flex justify-end gap-2">
            <button type="button" id="m-cancel" class="px-3 py-1.5 rounded-lg bg-[#221532] text-xs text-gray-300 hover:bg-[#2c1c3f]"><?= t('Cancel') ?></button>
            <button type="button" id="m-send" class="px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-xs font-semibold text-white"><?= t('Send report') ?></button>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$pageScripts = <<<'HTML'
<script>
(function () {
    const API = "/virtual-time-clock/api/attendance/time-errors.php";
    const root = document.getElementById("te-root");
    const isManager = root.dataset.manager === "1";
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
    const pad = n => String(n).padStart(2, "0");
    const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const dmy = s => { const [y, m, d] = s.split("-"); return `${d}/${m}/${y}`; };
    const parse = s => { const [y, m, d] = s.split("-").map(Number); return new Date(y, m - 1, d); };
    const hms = sec => { sec = Math.max(0, sec | 0); return `${String(Math.floor(sec / 3600)).padStart(3, "0")}:${pad(Math.floor(sec % 3600 / 60))}:${pad(sec % 60)}`; };
    const MONTHS = ["January","February","March","April","May","June","July","August","September","October","November","December"].map(m => t(m));

    // ---- period state
    const state = { mode: "month", anchor: new Date(), from: null, to: null, agent: null, data: null, open: null };
    try { state.mode = localStorage.getItem("te-mode") || "month"; } catch (e) {}

    function computeRange() {
        const a = new Date(state.anchor.getFullYear(), state.anchor.getMonth(), state.anchor.getDate());
        if (state.mode === "week") {
            const mon = new Date(a); mon.setDate(a.getDate() - ((a.getDay() + 6) % 7));
            const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
            state.from = ymd(mon); state.to = ymd(sun);
            $("f-label").textContent = t("Week of {from} – {to}", { from: dmy(state.from), to: dmy(state.to) });
        } else if (state.mode === "month") {
            state.from = ymd(new Date(a.getFullYear(), a.getMonth(), 1));
            state.to = ymd(new Date(a.getFullYear(), a.getMonth() + 1, 0));
            $("f-label").textContent = `${MONTHS[a.getMonth()]} ${a.getFullYear()}`;
        }
    }

    function renderMode() {
        document.querySelectorAll("#f-mode button").forEach(b => {
            const on = b.dataset.mode === state.mode;
            b.className = "py-1.5 rounded-lg transition " + (on ? "bg-purple-600 text-white shadow" : "text-gray-400 hover:text-white");
        });
        $("f-nav").classList.toggle("hidden", state.mode === "custom");
        $("f-custom").classList.toggle("hidden", state.mode !== "custom");
        if (state.mode === "custom") {
            if (!state.from) { const d0 = new Date(); state.to = ymd(d0); d0.setDate(d0.getDate() - 29); state.from = ymd(d0); }
            $("f-from").value = state.from; $("f-to").value = state.to;
        } else {
            computeRange();
        }
    }

    document.querySelectorAll("#f-mode button").forEach(b => b.addEventListener("click", () => {
        state.mode = b.dataset.mode;
        try { localStorage.setItem("te-mode", state.mode); } catch (e) {}
        renderMode(); if (state.mode !== "custom") load();
    }));
    const shift = dir => {
        if (state.mode === "week") state.anchor.setDate(state.anchor.getDate() + 7 * dir);
        else state.anchor = new Date(state.anchor.getFullYear(), state.anchor.getMonth() + dir, 1);
        computeRange(); load();
    };
    $("f-prev").addEventListener("click", () => shift(-1));
    $("f-next").addEventListener("click", () => shift(1));
    $("f-ok").addEventListener("click", () => {
        if (!$("f-from").value || !$("f-to").value || $("f-from").value > $("f-to").value) { showToast(t("Pick a valid date window."), "error"); return; }
        state.from = $("f-from").value; state.to = $("f-to").value; load();
    });

    // ---- load + render
    async function load() {
        const q = `from=${state.from}&to=${state.to}` + (state.agent ? `&user_id=${state.agent}` : "");
        const res = await apiRequest(`${API}?${q}`);
        if (!res.success) {
            $("s-rows").innerHTML = `<tr><td colspan="4" class="py-10 text-center text-xs text-red-400">${esc(res.message)}</td></tr>`;
            return;
        }
        state.data = res.data;
        if (isManager && res.data.agents && !$("f-agent").options.length) {
            $("f-agent").innerHTML = res.data.agents.map(a =>
                `<option value="${a.id}">${esc(a.last_name.toUpperCase())} ${esc(a.first_name)} (@${esc(a.username)})${a.role !== "employee" ? " · " + esc(t(a.role)) : ""}</option>`).join("");
            $("f-agent").value = res.data.summary.user_id;
        }
        render();
    }

    function kpi(label, value, tone) {
        return `<div class="bg-[#140c1d] border border-[#251738] rounded-2xl p-4">
            <div class="text-[11px] uppercase tracking-wider text-gray-500">${label}</div>
            <div class="mt-1 text-xl font-bold font-mono ${tone}">${value}</div></div>`;
    }

    const leaveDays = s => { const n = Object.values(s.leave_counts || {}).reduce((a, b) => a + b, 0); return Number.isInteger(n) ? n : n.toFixed(1); };

    // Leave rows: not errors, shown under the error types (one row per leave type)
    function leaveRows(d, s) {
        const types = Object.keys(d.leave_labels || {}).filter(lt => (s.leave_counts || {})[lt] > 0);
        if (!types.length) return "";
        let html = `<tr class="bg-[#120b1a]"><td colspan="4" class="py-2 px-5 text-[11px] uppercase tracking-wider text-emerald-400 font-semibold">${t("Leave &amp; PTO")} <span class="normal-case font-normal text-gray-500">${t("(excused, not counted as errors)")}</span></td></tr>`;
        types.forEach(lt => {
            const key = "LEAVE:" + lt, open = state.open === key;
            const items = s.leave_days.filter(l => l.type === lt);
            const paid = items[0] && items[0].paid;
            html += `
                <tr class="cursor-pointer hover:bg-[#1a1226]/60 transition" data-code="${key}">
                    <td class="py-3.5 px-5">
                        <div class="flex items-center gap-2 text-emerald-200 font-medium">
                            <svg class="w-3 h-3 text-gray-500 transition-transform ${open ? "rotate-90" : ""}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                            ${esc(d.leave_labels[lt])} <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded ${paid ? "bg-emerald-950 text-emerald-300 border border-emerald-800" : "bg-amber-950 text-amber-300 border border-amber-800"}">${paid ? t("PAID") : t("UNPAID")}</span>
                        </div>
                    </td>
                    <td class="py-3.5 px-5 hidden md:table-cell text-[11px] text-gray-500">${t("days on leave")}</td>
                    <td class="py-3.5 px-5 text-end font-mono font-semibold text-emerald-300">${s.leave_counts[lt]}</td>
                    <td class="py-3.5 px-5 text-end font-mono text-gray-300">${hms(s.leave_seconds[lt])}</td>
                </tr>`;
            if (open) {
                html += `<tr class="bg-[#110a19]"><td colspan="4" class="px-5 py-2"><div class="divide-y divide-[#231733]">${items.map(l => `
                    <div class="py-2.5 flex items-start justify-between gap-4">
                        <div class="flex gap-4 min-w-0">
                            <span class="font-mono text-xs text-gray-400 shrink-0 pt-0.5">${dmy(l.work_date)}</span>
                            <span class="text-xs text-gray-300">${esc(l.part_label)}${l.planned ? " " + t("(planned)") : ""}${l.note ? " · " + esc(l.note) : ""}</span>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="font-mono text-xs text-gray-500">${hms(l.seconds)}</span>
                            ${isManager && !d.is_self ? `<a href="/virtual-time-clock/public/admin/leaves.php?user_id=${s.user_id}" class="px-3 py-1 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-emerald-200 text-[11px] font-semibold">${t("Leave &amp; PTO")}</a>` : ""}
                        </div></div>`).join("")}</div></td></tr>`;
            }
        });
        return html;
    }

    function render() {
        const d = state.data, s = d.summary, self = d.is_self;
        const totalDur = Object.values(s.durations).reduce((a, b) => a + b, 0);
        const openReports = d.reports.filter(r => r.status === "OPEN");

        $("s-title").innerHTML = t("Summary of {name}", { name: `<span class="text-purple-300">${esc(s.name)}</span>` }) + ` <span class="text-gray-500 font-mono text-sm">@${esc(s.username)}</span>`;
        $("s-sub").textContent = t("Period from {from} to {to}. Today is checked once the workday is finished.", { from: dmy(d.from), to: dmy(d.to) });
        $("s-kpis").innerHTML =
            kpi(t("Total events"), s.error_count, s.error_count ? "text-red-400" : "text-emerald-400") +
            kpi(t("Total duration"), hms(totalDur), "text-white") +
            kpi(t("Days with errors"), `${s.days_with_errors}`, "text-amber-300") +
            kpi(t("Reports waiting"), openReports.length, openReports.length ? "text-amber-300" : "text-gray-300") +
            kpi(leaveDays(s) == 1 ? t("Leave · 1 day") : t("Leave · {n} days", { n: leaveDays(s) }), hms((s.paid_leave_seconds || 0) + (s.unpaid_leave_seconds || 0)), (s.leave_days || []).length ? "text-emerald-300" : "text-gray-300");

        const showAll = $("s-all").checked;
        const max = Math.max(1, ...Object.values(s.counts));
        const codes = Object.keys(d.labels)
            .filter(c => showAll || s.counts[c] > 0)
            .sort((a, b) => s.counts[b] - s.counts[a] || s.durations[b] - s.durations[a]);

        $("s-rows").innerHTML = codes.length ? codes.map(code => {
            const n = s.counts[code], open = state.open === code;
            const pct = Math.round(n / max * 100);
            let html = `
                <tr class="${n ? "cursor-pointer hover:bg-[#1a1226]/60" : "opacity-50"} transition" data-code="${code}">
                    <td class="py-3.5 px-5">
                        <div class="flex items-center gap-2 text-gray-100 font-medium">
                            ${n ? `<svg class="w-3 h-3 text-gray-500 transition-transform ${open ? "rotate-90" : ""}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>` : `<span class="w-3"></span>`}
                            ${esc(d.labels[code])}
                        </div>
                    </td>
                    <td class="py-3.5 px-5 hidden md:table-cell">
                        <div class="h-2 rounded-full bg-[#221532] overflow-hidden"><div class="h-full rounded-full bg-gradient-to-r from-purple-500 to-fuchsia-400" style="width:${pct}%"></div></div>
                    </td>
                    <td class="py-3.5 px-5 text-end font-mono font-semibold ${n ? "text-white" : "text-gray-500"}">${n}</td>
                    <td class="py-3.5 px-5 text-end font-mono text-gray-300">${hms(s.durations[code])}</td>
                </tr>`;
            if (open && n) {
                const items = s.incidents.filter(i => i.code === code);
                html += `<tr class="bg-[#110a19]"><td colspan="4" class="px-5 py-2">
                    <div class="divide-y divide-[#231733]">${items.map(i => {
                        const reported = openReports.some(r => r.work_date === i.work_date && r.error_code === i.code);
                        const leaveLink = i.code === "ABSENCE" ? `<a href="/virtual-time-clock/public/admin/leaves.php?user_id=${s.user_id}&date=${esc(i.work_date)}" class="px-3 py-1 rounded-lg bg-emerald-950/60 hover:bg-emerald-900/60 border border-emerald-800 text-emerald-200 text-[11px] font-semibold">${t("Record leave")}</a> ` : "";
                        const action = !self ? (isManager ? `${leaveLink}<a href="/virtual-time-clock/public/admin/time-corrections.php?user_id=${s.user_id}&date=${esc(i.work_date)}" class="px-3 py-1 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-purple-200 text-[11px] font-semibold">${t("Correct times")}</a>` : "") : reported
                            ? `<span class="text-[11px] text-amber-300 font-semibold">${t("Reported")}</span>`
                            : `<button class="px-3 py-1 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-[11px] font-semibold" data-report="${esc(i.work_date)}|${esc(i.code)}">${t("Report to manager")}</button>`;
                        return `<div class="py-2.5 flex items-start justify-between gap-4">
                            <div class="flex gap-4 min-w-0">
                                <span class="font-mono text-xs text-gray-400 shrink-0 pt-0.5">${dmy(i.work_date)}</span>
                                <span class="text-xs text-gray-300">${esc(i.detail)}</span>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="font-mono text-xs text-gray-500">${i.impact_seconds != null ? hms(Math.abs(i.impact_seconds)) : ""}</span>${action}
                            </div></div>`;
                    }).join("")}</div></td></tr>`;
            }
            return html;
        }).join("") : `<tr><td colspan="4" class="py-10 text-center text-sm text-emerald-400">${t("No time errors in this period.")}</td></tr>`;
        $("s-rows").innerHTML += leaveRows(d, s);

        $("s-rows").querySelectorAll("tr[data-code]").forEach(tr => tr.addEventListener("click", () => {
            if (!tr.dataset.code.startsWith("LEAVE:") && !s.counts[tr.dataset.code]) return;
            state.open = state.open === tr.dataset.code ? null : tr.dataset.code; render();
        }));
        $("s-rows").querySelectorAll("[data-report]").forEach(b => b.addEventListener("click", e => {
            e.stopPropagation();
            const [date, code] = b.dataset.report.split("|");
            openModal(date, code, d.labels[code]);
        }));

        $("s-other").classList.toggle("hidden", !self);
        $("r-title").textContent = self ? t("My reports to manager") : t("Reports from {name}", { name: s.name });
        $("r-list").innerHTML = d.reports.length ? d.reports.map(r => {
            const badge = r.status === "OPEN"
                ? `<span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-amber-950 text-amber-300 border border-amber-800">${t("WAITING")}</span>`
                : `<span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-950 text-emerald-300 border border-emerald-800">${t("RESOLVED")}</span>`;
            const res = r.status === "RESOLVED"
                ? `<div class="text-[11px] text-emerald-300 mt-1">${t("Resolved by {name}", { name: esc(r.resolver_first_name || "") + " " + esc(r.resolver_last_name || "") })}${r.resolution_note ? ": " + esc(r.resolution_note) : ""}</div>` : "";
            return `<div class="px-5 py-3">
                <div class="flex items-center gap-2 text-xs">${badge}<span class="font-mono text-gray-400">${dmy(r.work_date)}</span><span class="text-gray-200">${esc(r.error_label)}</span></div>
                <div class="text-[11px] text-gray-400 mt-1">${esc(r.message)}</div>${res}</div>`;
        }).join("") : `<div class="py-6 text-center text-xs text-gray-500">${t("No reports.")}</div>`;
    }
    $("s-all").addEventListener("change", () => state.data && render());
    if (isManager) $("f-agent").addEventListener("change", e => { state.agent = e.target.value; state.open = null; load(); });

    // ---- report modal
    let modalCode = null;
    function openModal(date, code, label) {
        modalCode = code || null;
        $("m-date").value = date || ymd(new Date());
        $("m-date").disabled = !!code;
        $("m-msg").value = "";
        $("m-info").className = "text-xs text-gray-300";
        $("m-info").textContent = label ? t("Error: {label}", { label }) : t("Describe a problem the checks did not catch.");
        $("m").classList.remove("hidden");
        $("m-msg").focus();
    }
    $("m-cancel").addEventListener("click", () => $("m").classList.add("hidden"));
    $("s-other").addEventListener("click", () => openModal(null, null, null));
    $("m-send").addEventListener("click", async () => {
        const res = await apiRequest(API, "POST", { work_date: $("m-date").value, error_code: modalCode, message: $("m-msg").value });
        if (res.success) { $("m").classList.add("hidden"); showToast(res.message, "success"); load(); }
        else { $("m-info").className = "text-xs text-red-300"; $("m-info").textContent = res.message; }
    });

    renderMode();
    document.addEventListener("DOMContentLoaded", load);
})();
</script>
HTML;

require_once ROOT_PATH . '/views/layouts/main.php';
