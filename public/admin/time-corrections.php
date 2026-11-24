<?php
/**
 * Correct Times (managers and administrators)
 *
 * Open with ?user_id=N&date=YYYY-MM-DD[&report_id=N] to jump to a day,
 * e.g. from the Time Errors page.
 */
require_once __DIR__ . '/../../src/bootstrap.php';

$currentUser = AdminMiddleware::requireManager();

$pageTitle = 'Correct Times &mdash; ClockWise';
$breadcrumbs = [
    ['label' => 'Time Clock', 'url' => '/virtual-time-clock/public/dashboard.php'],
    ['label' => 'Administration', 'url' => '/virtual-time-clock/public/admin/index.php'],
    ['label' => 'Correct Times', 'url' => '']
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
            <h1 class="text-xl font-bold text-white tracking-wide">Correct Times</h1>
            <p class="text-xs text-gray-400 mt-1">Fix an agent's clock-in, break and clock-out times for a day, for example when they were on time but forgot to clock in. Every change is saved with your name and the reason.</p>
        </div>
        <a href="/virtual-time-clock/public/admin/time-errors.php" class="px-3 py-1.5 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 text-xs font-semibold whitespace-nowrap">&larr; Time Errors</a>
    </div>

    <div class="bg-[#140c1d] border border-[#251738] rounded-xl shadow-xl p-4 flex flex-wrap items-end gap-3">
        <label class="text-[11px] text-gray-400 flex flex-col gap-1">Agent
            <select id="tc-agent" class="bg-[#1a1226] border border-[#2d1e44] text-xs text-gray-200 rounded-lg px-3 py-1.5 min-w-[220px]"></select>
        </label>
        <label class="text-[11px] text-gray-400 flex flex-col gap-1">Day
            <input type="date" id="tc-date" class="bg-[#1a1226] border border-[#2d1e44] text-xs font-mono text-gray-200 rounded-lg px-3 py-1.5">
        </label>
        <div id="tc-report-note" class="hidden text-[11px] text-amber-300 pb-1.5">Saving will also resolve the agent's report.</div>
    </div>

    <div id="tc-editor" class="hidden bg-[#140c1d] border border-[#251738] rounded-xl overflow-hidden shadow-xl">
        <div class="px-4 py-3 bg-[#1a1226] border-b border-[#251738] flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-xs font-semibold text-white uppercase tracking-wider">Clock actions <span id="tc-current" class="ml-2 normal-case font-normal text-gray-400"></span></h3>
            <div class="flex flex-wrap gap-2">
                <button data-preset="full" class="px-2.5 py-1 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 text-[11px]">Fill normal day</button>
                <button id="tc-add" class="px-2.5 py-1 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 text-[11px]">+ Add line</button>
                <button id="tc-clear" class="px-2.5 py-1 rounded-lg bg-red-950/60 hover:bg-red-900/60 border border-red-900 text-red-300 text-[11px]">Remove all (did not work)</button>
            </div>
        </div>
        <div class="p-4 space-y-2" id="tc-rows"></div>
        <div class="px-4 pb-2 text-[11px] text-gray-400" id="tc-preview"></div>
        <div class="px-4 py-3 border-t border-[#251738] flex flex-col sm:flex-row gap-2">
            <input type="text" id="tc-reason" maxlength="1000" placeholder="Reason (required), e.g. Agent was on time, forgot to clock in. Confirmed by team lead."
                   class="flex-1 bg-[#120a1c] border border-[#2d1e44] text-xs text-gray-200 rounded-lg px-3 py-1.5">
            <button id="tc-save" class="px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold">Save correction</button>
        </div>
    </div>

    <div class="bg-[#140c1d] border border-[#251738] rounded-xl overflow-hidden shadow-xl">
        <div class="px-4 py-3 bg-[#1a1226] border-b border-[#251738]">
            <h3 class="text-xs font-semibold text-white uppercase tracking-wider" id="tc-history-title">Latest corrections</h3>
        </div>
        <div id="tc-history" class="divide-y divide-[#231733]">
            <div class="py-6 text-center text-xs text-gray-500">Loading...</div>
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
    const LABEL = { START_WORKDAY: "Clock in", START_BREAK: "Start break", END_BREAK: "End break", END_WORKDAY: "Clock out" };
    const NEXT = { START_WORKDAY: "START_BREAK", START_BREAK: "END_BREAK", END_BREAK: "END_WORKDAY", END_WORKDAY: "START_WORKDAY" };
    // Normal day from the shift settings: 08:00-12:00 and 14:00-18:00
    const FULL = [["START_WORKDAY","08:00"],["START_BREAK","12:00"],["END_BREAK","14:00"],["END_WORKDAY","18:00"]];

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
            <span data-num class="w-6 text-right text-[11px] text-gray-500 font-mono"></span>
            <select class="bg-[#1a1226] border border-[#2d1e44] text-xs text-gray-200 rounded-lg px-2 py-1.5 w-40">
                ${Object.keys(LABEL).map(k => `<option value="${k}" ${k === r.action_type ? "selected" : ""}>${LABEL[k]}</option>`).join("")}
            </select>
            <input type="time" value="${esc(r.action_time)}" class="bg-[#1a1226] border border-[#2d1e44] text-xs font-mono text-gray-200 rounded-lg px-2 py-1.5">
            <button type="button" class="px-2 py-1 rounded text-red-400 hover:bg-red-950 text-xs" title="Remove line">&times;</button>
            <span data-changed class="text-[10px] text-amber-300 ${r.changed ? "" : "hidden"}">changed</span>`;
        const sel = div.querySelector("select"), inp = div.querySelector("input");
        const update = () => {
            r.action_type = sel.value;
            r.action_time = inp.value;
            r.changed = true;
            div.querySelector("[data-changed]").classList.remove("hidden");
            preview();
        };
        ["input", "change"].forEach(ev => { sel.addEventListener(ev, update); inp.addEventListener(ev, update); });
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
            box.innerHTML = `<div data-empty class="text-xs text-gray-500">No clock times for this day. Use "Fill normal day" or "+ Add line" to enter them. Saving with no lines means the agent did not work this day.</div>`;
        }
        rows.forEach((r, i) => { r.el.querySelector("[data-num]").textContent = i + 1; });
        preview();
    }

    function appendRow(r) {
        rows.push(r);
        $("tc-rows").querySelector("[data-empty]")?.remove();
        $("tc-rows").appendChild(rowElement(r));
        afterRowsChanged();
        return r;
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
            const t = sec(r.action_time);
            if (!r.action_time) { problem = `Line ${i + 1}: enter a time.`; return; }
            if (t < prev) { problem = `Line ${i + 1}: earlier than the line before it.`; return; }
            if (!ok[state].includes(r.action_type)) { problem = `Line ${i + 1}: ${LABEL[r.action_type]} is not possible here.`; return; }
            prev = t;
            if (r.action_type === "START_WORKDAY") { seg = t; state = "WORKING"; }
            if (r.action_type === "START_BREAK") { work += t - seg; bs = t; state = "ON_BREAK"; }
            if (r.action_type === "END_BREAK") { brk += t - bs; seg = t; state = "WORKING"; }
            if (r.action_type === "END_WORKDAY") { work += t - seg; state = "FINISHED"; }
        });
        if (!problem && rows.length && $("tc-date").value < today && state !== "FINISHED") problem = "A past day must end with a clock-out.";
        $("tc-preview").innerHTML = problem
            ? `<span class="text-red-400">${esc(problem)}</span>`
            : rows.length ? `After saving: worked <span class="text-white font-mono">${dur(work)}</span>, break <span class="text-white font-mono">${dur(brk)}</span>${state !== "FINISHED" ? " (still open, the live clock adds the rest)" : ""}.` : "";
    }

    function renderHistory(list, title) {
        $("tc-history-title").textContent = title;
        const line = s => s && s.actions && s.actions.length
            ? s.actions.map(a => `${LABEL[a.action_type] || a.action_type} ${hm(a.action_time)}`).join(", ")
            : "no clock actions";
        $("tc-history").innerHTML = list.length ? list.map(c => `
            <div class="px-4 py-3 text-xs">
                <div><span class="font-mono text-gray-400">${esc(c.created_at)}</span>
                    <span class="ml-2 text-white font-semibold">${esc(c.editor_first_name || "?")} ${esc(c.editor_last_name || "")}</span>
                    <span class="text-gray-400">corrected</span>
                    <a href="?user_id=${c.user_id}&date=${esc(c.work_date)}" class="text-purple-300 hover:underline">${esc(c.first_name)} ${esc(c.last_name)}, ${esc(c.work_date)}</a></div>
                <div class="text-[11px] text-gray-300 mt-1">Reason: "${esc(c.reason)}"</div>
                <div class="text-[11px] text-gray-500 mt-1">Before: ${esc(line(c.before))}</div>
                <div class="text-[11px] text-emerald-300">After: ${esc(line(c.after))}</div>
            </div>`).join("") : `<div class="py-6 text-center text-xs text-gray-500">No corrections yet.</div>`;
    }

    async function loadDay() {
        const uid = $("tc-agent").value, date = $("tc-date").value;
        if (!uid || !date) { $("tc-editor").classList.add("hidden"); return; }
        history.replaceState(null, "", `?user_id=${uid}&date=${date}${reportId ? "&report_id=" + reportId : ""}`);
        const res = await apiRequest(`${API}?user_id=${uid}&date=${date}`);
        if (!res.success) { showToast(res.message, "error"); return; }
        const d = res.data;
        setRows(d.actions.map(a => ({ action_type: a.action_type, action_time: a.action_time })));
        $("tc-current").textContent = d.day
            ? `Now: ${d.day.status}, in ${hm(d.day.start_time)}, out ${hm(d.day.end_time)}`
              + (d.derived ? ". No clock actions were saved, so these lines come from the day's start and end times. Save to store them." : "")
            : "Now: no attendance record for this day";
        $("tc-editor").classList.remove("hidden");
        $("tc-report-note").classList.toggle("hidden", !reportId);
        renderHistory(d.history, "History for this day");
    }

    $("tc-add").addEventListener("click", () => {
        const last = rows[rows.length - 1];
        const r = appendRow({ action_type: last ? NEXT[last.action_type] : "START_WORKDAY", action_time: "", changed: true });
        r.el.querySelector("input").focus();
    });
    document.querySelector('[data-preset="full"]').addEventListener("click", () => {
        setRows(FULL.map(([t, h]) => ({ action_type: t, action_time: h + ":00", changed: true })));
    });
    $("tc-clear").addEventListener("click", () => setRows([]));
    $("tc-agent").addEventListener("change", () => { reportId = 0; loadDay(); });
    $("tc-date").addEventListener("change", () => { reportId = 0; loadDay(); });

    $("tc-save").addEventListener("click", async () => {
        const reason = $("tc-reason").value.trim();
        if (!reason) { showToast("Please give a reason for the correction.", "error"); $("tc-reason").focus(); return; }
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
        if (res.success) { $("tc-reason").value = ""; reportId = 0; loadDay(); }
    });

    document.addEventListener("DOMContentLoaded", async () => {
        const res = await apiRequest(API);
        if (!res.success) { $("tc-history").innerHTML = `<div class="py-6 text-center text-xs text-red-400">${esc(res.message)}</div>`; return; }
        today = res.data.today;
        const self = parseInt(root.dataset.self, 10);
        const agents = res.data.agents.filter(a => res.data.can_edit_self || a.id !== self);
        $("tc-agent").innerHTML = `<option value="">Choose an agent...</option>` + agents.map(a =>
            `<option value="${a.id}">${esc(a.first_name)} ${esc(a.last_name)} (@${esc(a.username)})</option>`).join("");
        $("tc-date").max = today;
        $("tc-agent").value = root.dataset.user !== "0" ? root.dataset.user : "";
        $("tc-date").value = root.dataset.date || today;
        renderHistory(res.data.recent, "Latest corrections");
        if ($("tc-agent").value) loadDay();
    });
})();
</script>
HTML;

require_once ROOT_PATH . '/views/layouts/main.php';
