<?php
/**
 * Correct Times (managers and administrators)
 *
 * Open with ?user_id=N&date=YYYY-MM-DD[&report_id=N] to jump to a day,
 * e.g. from the Time Errors page.
 */
require_once __DIR__ . '/../../src/bootstrap.php';

$currentUser = AdminMiddleware::requireManager();

$pageTitle = t('Correct Times') . ' &mdash; ' . APP_NAME;
$breadcrumbs = [
    ['label' => t('Time Clock'), 'url' => '/virtual-time-clock/public/dashboard.php'],
    ['label' => t('Administration'), 'url' => '/virtual-time-clock/public/admin/index.php'],
    ['label' => t('Correct Times'), 'url' => '']
];

ob_start();
?>

<div class="max-w-5xl mx-auto space-y-6" id="tc-root"
     data-self="<?= (int) $currentUser['id'] ?>"
     data-user="<?= (int) ($_GET['user_id'] ?? 0) ?>"
     data-date="<?= htmlspecialchars(preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : '', ENT_QUOTES) ?>"
     data-report="<?= (int) ($_GET['report_id'] ?? 0) ?>">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-[#251738] gap-4">
        <div>
            <h1 class="text-xl font-bold text-white tracking-wide"><?= t('Correct Times') ?></h1>
            <p class="text-xs text-gray-400 mt-1"><?= t('Fix an agent\'s clock-in, break and clock-out times for a day, for example when they were on time but forgot to clock in. Every change is saved with your name and the reason.') ?></p>
        </div>
        <a href="/virtual-time-clock/public/admin/time-errors.php" class="px-3 py-1.5 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 text-xs font-semibold whitespace-nowrap"><?= t('&larr; Time Errors') ?></a>
    </div>

    <!-- Filters (same as Summary of time errors) -->
    <div class="bg-[#140c1d] border border-[#251738] rounded-2xl p-4 sm:p-5 shadow-xl">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-4 h-4 text-purple-400" fill="currentColor" viewBox="0 0 24 24"><path d="M3 4h18l-7 8.5V19l-4 2v-8.5L3 4z"/></svg>
            <h2 class="text-sm font-semibold text-white"><?= t('Filters') ?></h2>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-end">
            <div class="lg:col-span-4">
                <label class="block text-[11px] uppercase tracking-wider text-gray-500 mb-1.5"><?= t('Agent') ?></label>
                <select id="tc-agent" class="w-full bg-[#1a1226] border border-[#2d1e44] text-sm text-gray-100 rounded-xl px-3 py-2 focus:outline-none focus:border-purple-500"></select>
            </div>
            <div class="lg:col-span-3">
                <label class="block text-[11px] uppercase tracking-wider text-gray-500 mb-1.5"><?= t('Period') ?></label>
                <div id="f-mode" class="grid grid-cols-3 bg-[#1a1226] border border-[#2d1e44] rounded-xl p-1 text-xs font-semibold">
                    <button data-mode="week" class="py-1.5 rounded-lg text-gray-400 hover:text-white transition"><?= t('Weekly') ?></button>
                    <button data-mode="month" class="py-1.5 rounded-lg text-gray-400 hover:text-white transition"><?= t('Monthly') ?></button>
                    <button data-mode="custom" class="py-1.5 rounded-lg text-gray-400 hover:text-white transition"><?= t('Custom') ?></button>
                </div>
            </div>
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

    <!-- Days in the period -->
    <div class="bg-[#140c1d] border border-[#251738] rounded-2xl overflow-hidden shadow-xl">
        <div class="px-5 py-3 bg-[#1a1226] border-b border-[#251738] flex flex-wrap items-center justify-between gap-3">
            <h3 id="d-title" class="text-xs font-semibold text-white uppercase tracking-wider"><?= t('Days to correct') ?></h3>
            <div class="flex items-center gap-3 text-[11px] text-gray-400">
                <label class="flex items-center gap-1.5 cursor-pointer select-none"><input type="checkbox" id="d-all" class="accent-purple-500"> <?= t('Show days without errors') ?></label>
                <label class="flex items-center gap-1.5"><?= t('Other day') ?>
                    <input type="date" id="tc-date" class="bg-[#120a1c] border border-[#2d1e44] text-[11px] font-mono text-gray-200 rounded-lg px-2 py-1">
                </label>
            </div>
        </div>
        <div id="d-list" class="divide-y divide-[#231733] max-h-[360px] overflow-y-auto">
            <div class="py-6 text-center text-xs text-gray-500"><?= t('Loading...') ?></div>
        </div>
        <div id="tc-report-note" class="hidden px-5 py-2 border-t border-[#251738] text-[11px] text-amber-300"><?= t('Saving will also resolve the agent\'s report.') ?></div>
    </div>

    <div id="tc-editor" class="hidden bg-[#140c1d] border border-[#251738] rounded-xl overflow-hidden shadow-xl">
        <div class="px-4 py-3 bg-[#1a1226] border-b border-[#251738] flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-xs font-semibold text-white uppercase tracking-wider"><?= t('Clock actions') ?> <span id="tc-current" class="ms-2 normal-case font-normal text-gray-400"></span></h3>
            <div class="flex flex-wrap gap-2">
                <button data-add="START_WORKDAY" class="px-2.5 py-1 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 text-[11px]"><?= t('+ Clock in') ?></button>
                <button data-add="END_WORKDAY" class="px-2.5 py-1 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 text-[11px]"><?= t('+ Clock out') ?></button>
                <button data-add="START_BREAK" class="px-2.5 py-1 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 text-[11px]"><?= t('+ Start break') ?></button>
                <button data-add="END_BREAK" class="px-2.5 py-1 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 text-[11px]"><?= t('+ End break') ?></button>
                <button data-preset="full" class="px-2.5 py-1 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 text-[11px]" title="<?= t('Adds whatever is missing from the agent\'s planned shifts for this day. Existing lines and breaks are kept.') ?>"><?= t('Fill missing in/out') ?></button>
                <button id="tc-clear" class="px-2.5 py-1 rounded-lg bg-red-950/60 hover:bg-red-900/60 border border-red-900 text-red-300 text-[11px]"><?= t('Remove all (did not work)') ?></button>
                <a id="tc-leave" href="/virtual-time-clock/public/admin/leaves.php" class="px-2.5 py-1 rounded-lg bg-emerald-950/60 hover:bg-emerald-900/60 border border-emerald-800 text-emerald-200 text-[11px]" title="<?= t('The agent was on leave or PTO: record it instead of clock times') ?>"><?= t('Record leave') ?></a>
            </div>
        </div>
        <div class="p-4 space-y-2" id="tc-rows"></div>
        <div class="px-4 pb-2 text-[11px] text-gray-400" id="tc-preview"></div>
        <div class="px-4 py-3 border-t border-[#251738] flex flex-col sm:flex-row gap-2">
            <input type="text" id="tc-reason" maxlength="1000" placeholder="<?= t('Reason (required), e.g. Agent was on time, forgot to clock in. Confirmed by team lead.') ?>"
                   class="flex-1 bg-[#120a1c] border border-[#2d1e44] text-xs text-gray-200 rounded-lg px-3 py-1.5">
            <button id="tc-save" class="px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold"><?= t('Save correction') ?></button>
        </div>
    </div>

    <div class="bg-[#140c1d] border border-[#251738] rounded-xl overflow-hidden shadow-xl">
        <div class="px-4 py-3 bg-[#1a1226] border-b border-[#251738]">
            <h3 class="text-xs font-semibold text-white uppercase tracking-wider" id="tc-history-title"><?= t('Latest corrections') ?></h3>
        </div>
        <div id="tc-history" class="divide-y divide-[#231733]">
            <div class="py-6 text-center text-xs text-gray-500"><?= t('Loading...') ?></div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$pageScripts = <<<'HTML'
<script>
(function () {
    const API = "/virtual-time-clock/api/admin/time-corrections.php";
    const root = document.getElementById("tc-root");
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
    const hm = t => t ? String(t).slice(0, 5) : "--:--";
    const dur = s => { s = Math.max(0, s | 0); return Math.floor(s / 3600) + "h " + String(Math.floor(s % 3600 / 60)).padStart(2, "0") + "m"; };
    const TE_API = "/virtual-time-clock/api/attendance/time-errors.php";
    const pad = n => String(n).padStart(2, "0");
    const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const dmy = s => { const [y, m, d] = s.split("-"); return `${d}/${m}/${y}`; };
    const parse = s => { const [y, m, d] = s.split("-").map(Number); return new Date(y, m - 1, d); };
    const hms = sec => { sec = Math.max(0, sec | 0); return `${String(Math.floor(sec / 3600)).padStart(3, "0")}:${pad(Math.floor(sec % 3600 / 60))}:${pad(sec % 60)}`; };
    const MONTHS = ["January","February","March","April","May","June","July","August","September","October","November","December"].map(m => t(m));
    const DAYS = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"].map(d => t(d));
    const period = { mode: "month", anchor: new Date(), from: null, to: null, data: null };
    try { period.mode = localStorage.getItem("te-mode") || "month"; } catch (e) {}
    const LABEL = { START_WORKDAY: t("Clock in"), START_BREAK: t("Start break"), END_BREAK: t("End break"), END_WORKDAY: t("Clock out") };
    // The agent's planned shifts for the day being edited (minus leave),
    // loaded with the day: [{start, end}, ...]. Empty = no shift planned.
    let shifts = [];
    const secOf = t => { const p = String(t || "").split(":").map(Number); return (p[0] || 0) * 3600 + (p[1] || 0) * 60 + (p[2] || 0); };
    const has = (type, from, to) => rows.some(r => r.action_type === type && r.action_time && secOf(r.action_time) >= secOf(from) && secOf(r.action_time) < secOf(to));
    const hasBreaks = () => rows.some(r => r.action_type === "START_BREAK" || r.action_type === "END_BREAK");
    // Clock-in for shift i: between the end of the previous shift and the end of this one.
    // Clock-out for shift i: between the start of this shift and the start of the next one.
    const inWindow  = i => [i === 0 ? "00:00:00" : shifts[i - 1].end, shifts[i].end];
    const outWindow = i => [shifts[i].start, i === shifts.length - 1 ? "24:00:00" : shifts[i + 1].start];
    const shiftsText = list => list.length ? list.map(x => hm(x.start) + "-" + hm(x.end)).join(", ") : t("no shift planned");
    // Default time for a new line, following the planned shifts: the start of
    // the first shift without a clock-in, the end of the first shift without
    // a clock-out (with a break taken, only the end of the last shift).
    function defaultTime(type) {
        for (let i = 0; i < shifts.length; i++) {
            if (type === "START_WORKDAY" && !has("START_WORKDAY", ...inWindow(i))) return shifts[i].start;
            if (type === "END_WORKDAY") {
                if (hasBreaks() && i < shifts.length - 1) continue;
                if (!has("END_WORKDAY", ...outWindow(i))) return shifts[i].end;
            }
        }
        return "";
    }

    let rows = [];
    let today = "";
    let reportId = parseInt(root.dataset.report, 10) || 0;

    // Each line is built once and bound to its own row object. Adding or
    // removing a line never redraws the other lines, so whatever is typed in
    // them (even a half-typed time) stays as it is.
    function rowElement(r) {
        const div = document.createElement("div");
        div.className = "flex items-center gap-2";
        div.innerHTML = `
            <span data-num class="w-6 text-end text-[11px] text-gray-500 font-mono"></span>
            <select class="bg-[#1a1226] border border-[#2d1e44] text-xs text-gray-200 rounded-lg px-2 py-1.5 w-40">
                ${Object.keys(LABEL).map(k => `<option value="${k}" ${k === r.action_type ? "selected" : ""}>${LABEL[k]}</option>`).join("")}
            </select>
            <input type="time" value="${esc(r.action_time)}" class="bg-[#1a1226] border border-[#2d1e44] text-xs font-mono text-gray-200 rounded-lg px-2 py-1.5">
            <button type="button" class="px-2 py-1 rounded text-red-400 hover:bg-red-950 text-xs" title="${t("Remove line")}">&times;</button>
            <span data-changed class="text-[10px] text-amber-300 ${r.changed ? "" : "hidden"}">${t("changed")}</span>`;
        const sel = div.querySelector("select"), inp = div.querySelector("input");
        const update = () => {
            r.action_type = sel.value;
            r.action_time = inp.value;
            r.changed = true;
            div.querySelector("[data-changed]").classList.remove("hidden");
            preview();
        };
        ["input", "change"].forEach(ev => { sel.addEventListener(ev, update); inp.addEventListener(ev, update); });
        // Once a time is entered, move the line to its place in time order
        inp.addEventListener("blur", () => { if (inp.value) sortRows(); });
        div.querySelector("button").addEventListener("click", () => {
            rows.splice(rows.indexOf(r), 1);
            div.remove();
            afterRowsChanged();
        });
        r.el = div;
        return div;
    }

    function afterRowsChanged() {
        const box = $("tc-rows");
        box.querySelector("[data-empty]")?.remove();
        if (!rows.length) {
            box.innerHTML = `<div data-empty class="text-xs text-gray-500">${esc(t("No clock times for this day. Use \"+ Clock in\" and \"+ Clock out\" (or \"Fill missing in/out\") to enter them. Saving with no lines means the agent did not work this day."))}</div>`;
        }
        rows.forEach((r, i) => { r.el.querySelector("[data-num]").textContent = i + 1; });
        preview();
    }

    // Add a line at position `at` (default: the end) without redrawing the others
    function appendRow(r, at = rows.length) {
        const box = $("tc-rows");
        box.querySelector("[data-empty]")?.remove();
        const el = rowElement(r);
        const before = rows[at];
        rows.splice(at, 0, r);
        if (before) box.insertBefore(el, before.el); else box.appendChild(el);
        afterRowsChanged();
        return r;
    }

    // Put lines with a time in time order (stable, so equal times keep their
    // order). Moves the existing elements, so nothing typed is lost.
    function sortRows() {
        const sec = t => { const p = String(t).split(":").map(Number); return (p[0] || 0) * 3600 + (p[1] || 0) * 60 + (p[2] || 0); };
        const timed = rows.map((r, i) => ({ r, i })).filter(x => x.r.action_time);
        const sorted = [...timed].sort((a, b) => sec(a.r.action_time) - sec(b.r.action_time) || a.i - b.i);
        const slots = timed.map(x => x.i);
        const next = rows.slice();
        sorted.forEach((x, k) => { next[slots[k]] = x.r; });
        if (next.every((r, i) => r === rows[i])) return;
        rows = next;
        rows.forEach(r => $("tc-rows").appendChild(r.el));
        afterRowsChanged();
    }

    // Where a new line of this type goes: clock-in at the top, clock-out at
    // the end, a break line before a final clock-out.
    function insertAt(type) {
        if (type === "START_WORKDAY") return 0;
        const last = rows[rows.length - 1];
        if (type !== "END_WORKDAY" && last && last.action_type === "END_WORKDAY") return rows.length - 1;
        return rows.length;
    }

    // Replace every line (used when loading a day, "Fill normal day", "Remove all")
    function setRows(list) {
        rows = [];
        $("tc-rows").innerHTML = "";
        list.forEach(appendRow);
        afterRowsChanged();
    }

    // Live preview of what the day will look like (the server checks again on save)
    function preview() {
        const sec = t => { const p = String(t || "").split(":").map(Number); return (p[0] || 0) * 3600 + (p[1] || 0) * 60 + (p[2] || 0); };
        let work = 0, brk = 0, seg = null, bs = null, problem = "";
        let state = "NOT_STARTED", prev = -1;
        const ok = { NOT_STARTED: ["START_WORKDAY"], WORKING: ["START_BREAK","END_WORKDAY"], ON_BREAK: ["END_BREAK"], FINISHED: ["START_WORKDAY"] };
        rows.forEach((r, i) => {
            if (problem) return;
            const tm = sec(r.action_time);
            if (!r.action_time) { problem = t("Line {n}: enter a time.", { n: i + 1 }); return; }
            if (tm < prev) { problem = t("Line {n}: earlier than the line before it.", { n: i + 1 }); return; }
            if (!ok[state].includes(r.action_type)) { problem = t("Line {n}: {action} is not possible here.", { n: i + 1, action: LABEL[r.action_type] }); return; }
            prev = tm;
            if (r.action_type === "START_WORKDAY") { seg = tm; state = "WORKING"; }
            if (r.action_type === "START_BREAK") { work += tm - seg; bs = tm; state = "ON_BREAK"; }
            if (r.action_type === "END_BREAK") { brk += tm - bs; seg = tm; state = "WORKING"; }
            if (r.action_type === "END_WORKDAY") { work += tm - seg; state = "FINISHED"; }
        });
        if (!problem && rows.length && $("tc-date").value < today && state !== "FINISHED") problem = t("A past day must end with a clock-out.");
        $("tc-preview").innerHTML = problem
            ? `<span class="text-red-400">${esc(problem)}</span>`
            : rows.length ? t("After saving: worked {work}, break {break}", { work: `<span class="text-white font-mono">${dur(work)}</span>`, break: `<span class="text-white font-mono">${dur(brk)}</span>` }) + (state !== "FINISHED" ? " " + t("(still open, the live clock adds the rest)") : "") + "." : "";
    }

    function renderHistory(list, title) {
        $("tc-history-title").textContent = title;
        const line = s => s && s.actions && s.actions.length
            ? s.actions.map(a => `${LABEL[a.action_type] || a.action_type} ${hm(a.action_time)}`).join(", ")
            : t("no clock actions");
        $("tc-history").innerHTML = list.length ? list.map(c => `
            <div class="px-4 py-3 text-xs">
                <div><span class="font-mono text-gray-400">${esc(c.created_at)}</span>
                    <span class="ms-2 text-white font-semibold">${esc(c.editor_first_name || "?")} ${esc(c.editor_last_name || "")}</span>
                    <span class="text-gray-400">${t("corrected")}</span>
                    <a href="?user_id=${c.user_id}&date=${esc(c.work_date)}" class="text-purple-300 hover:underline">${esc(c.first_name)} ${esc(c.last_name)}, ${esc(c.work_date)}</a></div>
                <div class="text-[11px] text-gray-300 mt-1">${t("Reason:")} "${esc(c.reason)}"</div>
                <div class="text-[11px] text-gray-500 mt-1">${t("Before:")} ${esc(line(c.before))}</div>
                <div class="text-[11px] text-emerald-300">${t("After:")} ${esc(line(c.after))}</div>
            </div>`).join("") : `<div class="py-6 text-center text-xs text-gray-500">${t("No corrections yet.")}</div>`;
    }

    async function loadDay() {
        const uid = $("tc-agent").value, date = $("tc-date").value;
        if (!uid || !date) { $("tc-editor").classList.add("hidden"); return; }
        history.replaceState(null, "", `?user_id=${uid}&date=${date}${reportId ? "&report_id=" + reportId : ""}`);
        const res = await apiRequest(`${API}?user_id=${uid}&date=${date}`);
        if (!res.success) { showToast(res.message, "error"); return; }
        const d = res.data;
        setRows(d.actions.map(a => ({ action_type: a.action_type, action_time: a.action_time })));
        shifts = d.expected_shifts || [];
        const planned = d.planned_shifts || [];
        const plan = t("Planned: {time}", { time: shiftsText(planned) })
            + (planned.length && shifts.length < planned.length ? " (" + t("on leave:") + " " + (planned.length - shifts.length === planned.length ? t("whole day") : t("part of the day")) + ")" : "") + ". ";
        $("tc-current").textContent = plan + (d.day
            ? t("Now: {status}, in {in}, out {out}", { status: t(String(d.day.status).replace("_", " ")), in: hm(d.day.start_time), out: hm(d.day.end_time) })
              + (d.derived ? ". " + t("No clock actions were saved, so these lines come from the day's start and end times. Save to store them.") : "")
            : t("Now: no attendance record for this day"));
        $("tc-leave").href = `/virtual-time-clock/public/admin/leaves.php?user_id=${uid}&date=${date}`;
        $("tc-editor").classList.remove("hidden");
        $("tc-report-note").classList.toggle("hidden", !reportId);
        renderHistory(d.history, t("History for this day"));
    }

    document.querySelectorAll("[data-add]").forEach(b => b.addEventListener("click", () => {
        const type = b.dataset.add;
        const time = defaultTime(type);
        const r = appendRow({ action_type: type, action_time: time, changed: true }, insertAt(type));
        if (time) sortRows();
        r.el.querySelector("input").focus();
    }));
    // Only adds what is missing from the planned shifts; existing lines stay
    // as they are. If the agent took a break instead of clocking out between
    // shifts, only the first clock-in and last clock-out are added.
    document.querySelector('[data-preset="full"]').addEventListener("click", () => {
        if (!shifts.length) { showToast(t("No shift is planned for this agent on this day, so there is nothing to fill. Add lines by hand."), "error"); return; }
        const add = (type, time) => appendRow({ action_type: type, action_time: time, changed: true });
        const first = shifts[0], last = shifts[shifts.length - 1];
        if (hasBreaks()) {
            if (!has("START_WORKDAY", "00:00:00", "24:00:00")) add("START_WORKDAY", first.start);
            sortRows();
            const lastRow = rows[rows.length - 1];
            if (!lastRow || lastRow.action_type !== "END_WORKDAY") add("END_WORKDAY", last.end);
        } else {
            const missing = [];
            shifts.forEach((sh, i) => {
                if (!has("START_WORKDAY", ...inWindow(i))) missing.push(["START_WORKDAY", sh.start]);
                if (!has("END_WORKDAY", ...outWindow(i)))  missing.push(["END_WORKDAY", sh.end]);
            });
            missing.forEach(([t, h]) => add(t, h));
        }
        sortRows();
    });
    $("tc-clear").addEventListener("click", () => setRows([]));
    $("tc-agent").addEventListener("change", () => { reportId = 0; $("tc-date").value = ""; loadDay(); loadPeriod(); });
    $("tc-date").addEventListener("change", () => { reportId = 0; loadDay(); renderDays(); });

    // ---- period filter (same behaviour as Summary of time errors)
    function computeRange() {
        const a = new Date(period.anchor.getFullYear(), period.anchor.getMonth(), period.anchor.getDate());
        if (period.mode === "week") {
            const mon = new Date(a); mon.setDate(a.getDate() - ((a.getDay() + 6) % 7));
            const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
            period.from = ymd(mon); period.to = ymd(sun);
            $("f-label").textContent = t("Week of {from} – {to}", { from: dmy(period.from), to: dmy(period.to) });
        } else if (period.mode === "month") {
            period.from = ymd(new Date(a.getFullYear(), a.getMonth(), 1));
            period.to = ymd(new Date(a.getFullYear(), a.getMonth() + 1, 0));
            $("f-label").textContent = `${MONTHS[a.getMonth()]} ${a.getFullYear()}`;
        }
    }
    function renderMode() {
        document.querySelectorAll("#f-mode button").forEach(b => {
            const on = b.dataset.mode === period.mode;
            b.className = "py-1.5 rounded-lg transition " + (on ? "bg-purple-600 text-white shadow" : "text-gray-400 hover:text-white");
        });
        $("f-nav").classList.toggle("hidden", period.mode === "custom");
        $("f-custom").classList.toggle("hidden", period.mode !== "custom");
        if (period.mode === "custom") {
            if (!period.from) { const d0 = new Date(period.anchor); period.to = ymd(d0); d0.setDate(d0.getDate() - 29); period.from = ymd(d0); }
            $("f-from").value = period.from; $("f-to").value = period.to;
        } else {
            computeRange();
        }
    }
    document.querySelectorAll("#f-mode button").forEach(b => b.addEventListener("click", () => {
        period.mode = b.dataset.mode;
        try { localStorage.setItem("te-mode", period.mode); } catch (e) {}
        renderMode(); if (period.mode !== "custom") loadPeriod();
    }));
    const shift = dir => {
        if (period.mode === "week") period.anchor.setDate(period.anchor.getDate() + 7 * dir);
        else period.anchor = new Date(period.anchor.getFullYear(), period.anchor.getMonth() + dir, 1);
        computeRange(); loadPeriod();
    };
    $("f-prev").addEventListener("click", () => shift(-1));
    $("f-next").addEventListener("click", () => shift(1));
    $("f-ok").addEventListener("click", () => {
        if (!$("f-from").value || !$("f-to").value || $("f-from").value > $("f-to").value) { showToast(t("Pick a valid date window."), "error"); return; }
        period.from = $("f-from").value; period.to = $("f-to").value; loadPeriod();
    });
    $("d-all").addEventListener("change", renderDays);

    async function loadPeriod() {
        const uid = $("tc-agent").value;
        period.data = null;
        if (!uid) { $("d-list").innerHTML = `<div class="py-6 text-center text-xs text-gray-500">${t("Choose an agent.")}</div>`; return; }
        $("d-list").innerHTML = `<div class="py-6 text-center text-xs text-gray-500">${t("Loading...")}</div>`;
        const res = await apiRequest(`${TE_API}?from=${period.from}&to=${period.to}&user_id=${uid}`);
        if (uid !== $("tc-agent").value) return;
        period.data = res.success ? res.data.summary : { incidents: [], error_count: 0, name: $("tc-agent").selectedOptions[0]?.dataset.name || "" };
        renderDays();
    }

    function renderDays() {
        const s = period.data;
        if (!s) return;
        const byDay = {};
        (s.incidents || []).forEach(i => (byDay[i.work_date] = byDay[i.work_date] || []).push(i));
        const leaveByDay = {};
        (s.leave_days || []).forEach(l => (leaveByDay[l.work_date] = leaveByDay[l.work_date] || []).push(l));
        let dates = Object.keys(byDay);
        if ($("d-all").checked) {
            dates = [];
            const last = period.to < today ? period.to : today;
            for (let d = parse(period.from); ymd(d) <= last; d.setDate(d.getDate() + 1)) dates.push(ymd(d));
        }
        dates.sort().reverse();
        const errDays = Object.keys(byDay).length;
        $("d-title").innerHTML = `${t("Days to correct")} <span class="normal-case font-normal text-gray-400 ms-2">${esc(s.name || "")} · ${errDays === 1 ? t("1 day with errors") : t("{n} days with errors", { n: errDays })}, ${(s.error_count || 0) === 1 ? t("1 error") : t("{n} errors", { n: s.error_count || 0 })}</span>`;
        const sel = $("tc-date").value;
        $("d-list").innerHTML = dates.length ? dates.map(date => {
            const items = byDay[date] || [];
            const wd = DAYS[parse(date).getDay()];
            const leaveChips = (leaveByDay[date] || []).map(l => `<span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-950/60 text-emerald-300 border border-emerald-800" title="${t("Recorded leave")}">${esc(l.label)}${l.part !== "FULL" ? " · " + esc(l.part_label.toLowerCase()) : ""}</span>`).join(" ");
            const chips = (items.length ? items.map(i => `<span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-red-950/60 text-red-300 border border-red-900" title="${esc(i.detail)}">${esc(i.label)}${i.impact_seconds != null && i.impact_seconds !== 0 ? " " + hms(Math.abs(i.impact_seconds)) : ""}</span>`).join(" ")
                : (leaveChips ? "" : `<span class="text-[11px] text-gray-500">${t("No errors")}</span>`)) + (leaveChips ? " " + leaveChips : "");
            const on = date === sel;
            return `<button type="button" data-day="${date}" class="w-full text-start px-5 py-2.5 flex items-center gap-4 transition ${on ? "bg-[#221532]" : "hover:bg-[#1a1226]/60"}">
                <span class="font-mono text-xs ${on ? "text-white" : "text-gray-400"} shrink-0 w-28">${wd} ${dmy(date)}</span>
                <span class="flex-1 flex flex-wrap gap-1.5">${chips}</span>
                <span class="text-[11px] font-semibold ${on ? "text-white" : "text-purple-300"} shrink-0">${on ? t("Editing") : t("Correct")}</span>
            </button>`;
        }).join("") : `<div class="py-6 text-center text-sm text-emerald-400">${t("No time errors in this period.")}</div>`;
        const cur = $("d-list").querySelector(`[data-day="${sel}"]`);
        if (cur) $("d-list").scrollTop = cur.offsetTop - $("d-list").clientHeight / 2;
        $("d-list").querySelectorAll("[data-day]").forEach(b => b.addEventListener("click", () => {
            $("tc-date").value = b.dataset.day; reportId = 0; loadDay(); renderDays();
            $("tc-editor").scrollIntoView({ behavior: "smooth", block: "start" });
        }));
    }

    $("tc-save").addEventListener("click", async () => {
        const reason = $("tc-reason").value.trim();
        if (!reason) { showToast(t("Please give a reason for the correction."), "error"); $("tc-reason").focus(); return; }
        sortRows();
        const btn = $("tc-save");
        btn.disabled = true;
        const res = await apiRequest(API, "POST", {
            user_id: parseInt($("tc-agent").value, 10),
            date: $("tc-date").value,
            actions: rows.map(r => ({ action_type: r.action_type, action_time: r.action_time })),
            reason,
            report_id: reportId || null,
        });
        btn.disabled = false;
        showToast(res.message, res.success ? "success" : "error");
        if (res.success) { $("tc-reason").value = ""; reportId = 0; loadDay(); loadPeriod(); }
    });

    document.addEventListener("DOMContentLoaded", async () => {
        const res = await apiRequest(API);
        if (!res.success) { $("tc-history").innerHTML = `<div class="py-6 text-center text-xs text-red-400">${esc(res.message)}</div>`; return; }
        today = res.data.today;
        const self = parseInt(root.dataset.self, 10);
        const agents = res.data.agents.filter(a => res.data.can_edit_self || a.id !== self);
        $("tc-agent").innerHTML = `<option value="">${t("Choose an agent...")}</option>` + agents.map(a =>
            `<option value="${a.id}" data-name="${esc(a.first_name)} ${esc(a.last_name)}">${esc(a.last_name.toUpperCase())} ${esc(a.first_name)} (@${esc(a.username)})${a.role !== "employee" ? " · " + esc(t(a.role)) : ""}</option>`).join("");
        $("tc-date").max = today;
        const firstEmployee = agents.find(a => a.role === "employee") || agents[0];
        $("tc-agent").value = root.dataset.user !== "0" ? root.dataset.user : (firstEmployee ? String(firstEmployee.id) : "");
        $("tc-date").value = root.dataset.date || "";
        if (root.dataset.date) period.anchor = parse(root.dataset.date);
        renderMode();
        loadPeriod();
        renderHistory(res.data.recent, t("Latest corrections"));
        if ($("tc-agent").value && $("tc-date").value) loadDay();
    });
})();
</script>
HTML;

require_once ROOT_PATH . '/views/layouts/main.php';
