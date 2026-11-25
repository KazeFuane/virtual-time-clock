<?php
/**
 * Schedule
 * Agents see their own planned shifts. Managers and administrators plan the
 * team: assign a schedule to each agent from a date, change single days, and
 * create the schedules (named weekly shift patterns).
 */
require_once __DIR__ . '/../src/bootstrap.php';

$currentUser = AuthMiddleware::requireAuth();
$isManager = in_array($currentUser['role'], ['administrator', 'manager'], true);

$pageTitle = ($isManager ? t('Schedules') : t('My Schedule')) . ' &mdash; ' . APP_NAME;
$breadcrumbs = [
    ['label' => t('Time Clock'), 'url' => '/virtual-time-clock/public/dashboard.php'],
    ['label' => t('Time Management'), 'url' => '#'],
    ['label' => $isManager ? t('Schedules') : t('My Schedule'), 'url' => '']
];

$card  = 'bg-[#140c1d] border border-[#251738] rounded-2xl shadow-xl';
$btn   = 'px-3 py-1.5 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 text-xs font-semibold transition';
$btnP  = 'px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold transition';
$input = 'bg-[#1a1226] border border-[#2d1e44] text-xs text-gray-100 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-purple-500';

ob_start();
?>

<div class="max-w-7xl mx-auto space-y-5" id="sc-root" data-manager="<?= $isManager ? '1' : '0' ?>">

    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-white tracking-wide"><?= $isManager ? t('Schedules') : t('My Schedule') ?></h1>
            <p class="text-xs text-gray-400 mt-1">
                <?= $isManager
                    ? t('Plan the shifts of each agent. The time errors report, Correct Times and the attendance report follow these plans. A day with no shift planned is not counted as an absence.')
                    : t('Your planned shifts. Lateness, early departures and absences are checked against these times.') ?>
            </p>
        </div>
        <?php if ($isManager): ?>
            <div id="sc-tabs" class="flex bg-[#1a1226] border border-[#2d1e44] rounded-xl p-1 text-xs font-semibold">
                <button data-tab="team" class="whitespace-nowrap px-4 py-1.5 rounded-lg text-gray-400 hover:text-white transition"><?= t('Team plan') ?></button>
                <button data-tab="tpl" class="whitespace-nowrap px-4 py-1.5 rounded-lg text-gray-400 hover:text-white transition"><?= t('Schedules') ?></button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Period -->
    <div id="sc-period" class="<?= $card ?> p-4 flex flex-col lg:flex-row lg:items-center gap-3">
        <div id="f-mode" class="grid grid-cols-2 bg-[#1a1226] border border-[#2d1e44] rounded-xl p-1 text-xs font-semibold w-full lg:w-56">
            <button data-mode="week" class="py-1.5 rounded-lg text-gray-400 hover:text-white transition"><?= t('Weekly') ?></button>
            <button data-mode="month" class="py-1.5 rounded-lg text-gray-400 hover:text-white transition"><?= t('Monthly') ?></button>
        </div>
        <div class="flex items-center gap-2 flex-1">
            <button id="f-prev" class="shrink-0 w-9 h-9 grid place-items-center rounded-xl bg-[#1a1226] border border-[#2d1e44] text-gray-300 hover:text-white hover:border-purple-500 transition" aria-label="<?= t('Previous period') ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <div id="f-label" class="flex-1 text-center text-sm font-semibold text-white bg-[#1a1226] border border-[#2d1e44] rounded-xl py-2"></div>
            <button id="f-next" class="shrink-0 w-9 h-9 grid place-items-center rounded-xl bg-[#1a1226] border border-[#2d1e44] text-gray-300 hover:text-white hover:border-purple-500 transition" aria-label="<?= t('Next period') ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
            <button id="f-today" class="<?= $btn ?> shrink-0"><?= t('Today') ?></button>
        </div>
        <?php if ($isManager): ?>
            <div class="flex items-center gap-2" id="team-tools">
                <input type="text" id="t-search" placeholder="<?= t('Search agent...') ?>" class="<?= $input ?> w-full lg:w-48">
                <a id="t-csv" href="#" class="<?= $btn ?> whitespace-nowrap"><?= t('Download CSV') ?></a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Agent: own schedule -->
    <div id="view-my" class="<?= $card ?> overflow-hidden <?= $isManager ? 'hidden' : '' ?>">
        <div class="px-5 py-3 bg-[#1a1226] border-b border-[#251738] flex items-center justify-between">
            <h3 class="text-xs font-semibold text-white uppercase tracking-wider"><?= t('Planned shifts') ?></h3>
            <span id="my-total" class="text-[11px] text-gray-400"></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="text-[11px] uppercase tracking-wider text-gray-500 border-b border-[#251738]">
                    <tr><th class="text-start px-5 py-2"><?= t('Date') ?></th><th class="text-start px-3 py-2"><?= t('Shifts') ?></th><th class="text-end px-3 py-2"><?= t('Time') ?></th><th class="text-start px-5 py-2"><?= t('Schedule / note') ?></th></tr>
                </thead>
                <tbody id="my-body" class="divide-y divide-[#231733]"><tr><td colspan="4" class="py-6 text-center text-gray-500"><?= t('Loading...') ?></td></tr></tbody>
            </table>
        </div>
    </div>

    <?php if ($isManager): ?>
    <!-- Manager: team plan -->
    <div id="view-team" class="<?= $card ?> overflow-hidden hidden">
        <div class="px-5 py-3 bg-[#1a1226] border-b border-[#251738] flex flex-wrap items-center justify-between gap-2">
            <div class="text-[11px] text-gray-400 flex flex-wrap items-center gap-3">
                <span><?= t('Click a day to change it.') ?></span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-amber-500/70"></span><?= t('Day changed') ?></span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-500/70"></span><?= t('Leave') ?></span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-gray-600"></span><?= t('No shift') ?></span>
            </div>
            <div class="flex items-center gap-2">
                <span id="t-selected" class="text-[11px] text-gray-400"></span>
                <button id="t-plan-sel" class="<?= $btn ?>" disabled><?= t('Plan days for selected') ?></button>
                <button id="t-assign-sel" class="<?= $btn ?>" disabled><?= t('Assign schedule to selected') ?></button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead id="t-head" class="text-[11px] uppercase tracking-wider text-gray-500 border-b border-[#251738]"></thead>
                <tbody id="t-body" class="divide-y divide-[#231733]"><tr><td class="py-6 text-center text-gray-500"><?= t('Loading...') ?></td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- Manager: schedules (weekly patterns) -->
    <div id="view-tpl" class="space-y-4 hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <p class="text-[11px] text-gray-400 max-w-3xl"><?= t('A schedule is a weekly pattern of shifts that you assign to agents. Agents with no schedule assigned follow the {default} one. Editing a schedule changes it on every day it is used, past days included; to change hours from a date on, create a new schedule and assign it from that date.', ['default' => '<b class="text-gray-200">' . t('default') . '</b>']) ?></p>
            <button id="tpl-new" class="<?= $btnP ?> whitespace-nowrap"><?= t('+ New schedule') ?></button>
        </div>
        <div id="tpl-list" class="grid grid-cols-1 lg:grid-cols-2 gap-4"></div>
    </div>
    <?php endif; ?>
</div>

<?php if ($isManager): ?>
<!-- Modal -->
<div id="sc-modal" class="hidden fixed inset-0 z-40 bg-black/60 flex items-start sm:items-center justify-center p-4 overflow-y-auto">
    <div class="<?= $card ?> w-full max-w-xl">
        <div class="px-5 py-3 bg-[#1a1226] border-b border-[#251738] rounded-t-2xl flex items-center justify-between">
            <h3 id="m-title" class="text-sm font-semibold text-white"></h3>
            <button id="m-close" class="text-gray-400 hover:text-white text-lg leading-none" aria-label="<?= t('Close') ?>">&times;</button>
        </div>
        <div id="m-body" class="p-5 space-y-4 text-xs text-gray-300"></div>
        <div id="m-foot" class="px-5 py-3 border-t border-[#251738] flex flex-wrap justify-end gap-2"></div>
    </div>
</div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();

$pageScripts = <<<'HTML'
<script>
(function () {
    const root = document.getElementById("sc-root");
    const isManager = root.dataset.manager === "1";
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
    const hm = t => t ? String(t).slice(0, 5) : "";
    const pad = n => String(n).padStart(2, "0");
    const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const parse = s => { const [y, m, d] = s.split("-").map(Number); return new Date(y, m - 1, d); };
    const dur = s => { s = Math.max(0, s | 0); const h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60); return m ? `${h}h${pad(m)}` : `${h}h`; };
    const MONTHS = ["January","February","March","April","May","June","July","August","September","October","November","December"].map(m => t(m));
    const DAYS = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"].map(d => t(d));
    const WEEKDAYS = {1:t("Monday"),2:t("Tuesday"),3:t("Wednesday"),4:t("Thursday"),5:t("Friday"),6:t("Saturday"),7:t("Sunday")};
    const BTN = "px-3 py-1.5 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 text-xs font-semibold transition";
    const BTNP = "px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold transition";
    const BTND = "px-3 py-1.5 rounded-lg bg-red-950/60 hover:bg-red-900/60 border border-red-900 text-red-300 text-xs font-semibold transition";
    const INPUT = "bg-[#1a1226] border border-[#2d1e44] text-xs text-gray-100 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-purple-500";
    const ADMIN_API = "/virtual-time-clock/api/admin/schedules.php";
    const MY_API = "/virtual-time-clock/api/attendance/schedule.php";

    // ---------------- Period (weekly / monthly) ----------------
    const period = { mode: "week", anchor: new Date() };
    try { period.mode = localStorage.getItem("sc-mode") || "week"; } catch (e) {}
    function range() {
        const a = new Date(period.anchor);
        if (period.mode === "month") {
            return [new Date(a.getFullYear(), a.getMonth(), 1), new Date(a.getFullYear(), a.getMonth() + 1, 0)];
        }
        const mon = new Date(a); mon.setDate(a.getDate() - ((a.getDay() + 6) % 7));
        const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
        return [mon, sun];
    }
    function renderPeriod() {
        const [f, t] = range();
        $("f-label").textContent = period.mode === "month"
            ? `${MONTHS[f.getMonth()]} ${f.getFullYear()}`
            : `${pad(f.getDate())}/${pad(f.getMonth() + 1)} - ${pad(t.getDate())}/${pad(t.getMonth() + 1)}/${t.getFullYear()}`;
        document.querySelectorAll("#f-mode button").forEach(b => {
            const on = b.dataset.mode === period.mode;
            b.classList.toggle("bg-purple-600", on); b.classList.toggle("text-white", on); b.classList.toggle("text-gray-400", !on);
        });
    }
    function shift(step) {
        const a = period.anchor;
        period.anchor = period.mode === "month" ? new Date(a.getFullYear(), a.getMonth() + step, 1) : new Date(a.getFullYear(), a.getMonth(), a.getDate() + 7 * step);
        reload();
    }
    $("f-prev").onclick = () => shift(-1);
    $("f-next").onclick = () => shift(1);
    $("f-today").onclick = () => { period.anchor = new Date(); reload(); };
    document.querySelectorAll("#f-mode button").forEach(b => b.onclick = () => {
        period.mode = b.dataset.mode; try { localStorage.setItem("sc-mode", period.mode); } catch (e) {} reload();
    });

    const shiftsHtml = (shifts, cls = "") => shifts.length
        ? shifts.map(s => `<div class="font-mono ${cls}">${hm(s.start)}-${hm(s.end)}</div>`).join("")
        : `<div class="text-gray-500">${t("No shift")}</div>`;
    const leaveHtml = list => list.map(l => `<span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-950/60 text-emerald-300 border border-emerald-800">${esc(l.label)}${l.part !== "FULL" ? " · " + esc(l.part_label.toLowerCase()) : ""}</span>`).join(" ");

    // ---------------- Agent view ----------------
    async function loadMine() {
        const [f, t] = range();
        const res = await apiRequest(`${MY_API}?from=${ymd(f)}&to=${ymd(t)}`);
        if (!res.success) { showToast(res.message, "error"); return; }
        const d = res.data;
        $("my-total").textContent = t("Planned: {time}", { time: dur(d.planned_seconds) });
        $("my-body").innerHTML = d.days.map(x => {
            const dt = parse(x.date);
            const today = x.date === d.today;
            const src = x.source === "day" ? `<span class="text-amber-300">${t("Changed for this day")}</span>` : esc(x.template_name || "");
            return `<tr class="${today ? "bg-purple-950/30" : ""} ${x.shifts.length ? "" : "text-gray-500"}">
                <td class="px-5 py-2 whitespace-nowrap"><span class="font-semibold ${today ? "text-purple-300" : "text-gray-200"}">${DAYS[dt.getDay()]}</span> <span class="font-mono text-gray-400">${pad(dt.getDate())}/${pad(dt.getMonth() + 1)}</span>${today ? ` <span class="text-[10px] text-purple-300">${t("today")}</span>` : ""}</td>
                <td class="px-3 py-2">${x.shifts.length ? x.shifts.map(s => `<span class="inline-block font-mono me-3 text-gray-100">${hm(s.start)}-${hm(s.end)}</span>`).join("") : t("Day off")} ${leaveHtml(x.leave)}</td>
                <td class="px-3 py-2 text-end font-mono">${x.seconds ? dur(x.seconds) : ""}</td>
                <td class="px-5 py-2 text-gray-400">${src}${x.note ? " · " + esc(x.note) : ""}</td>
            </tr>`;
        }).join("");
    }

    if (!isManager) {
        var reload = () => { renderPeriod(); loadMine(); };
        reload();
        return;
    }

    // ---------------- Manager ----------------
    let data = null;
    let tab = "team";
    try { tab = localStorage.getItem("sc-tab") || "team"; } catch (e) {}
    const selected = new Set();

    function setTab(t) {
        tab = t; try { localStorage.setItem("sc-tab", t); } catch (e) {}
        document.querySelectorAll("#sc-tabs button").forEach(b => {
            const on = b.dataset.tab === t;
            b.classList.toggle("bg-purple-600", on); b.classList.toggle("text-white", on); b.classList.toggle("text-gray-400", !on);
        });
        $("view-team").classList.toggle("hidden", t !== "team");
        $("view-tpl").classList.toggle("hidden", t !== "tpl");
        $("sc-period").classList.toggle("hidden", t !== "team");
    }
    document.querySelectorAll("#sc-tabs button").forEach(b => b.onclick = () => setTab(b.dataset.tab));

    let searchTimer = null;
    $("t-search").oninput = () => { clearTimeout(searchTimer); searchTimer = setTimeout(reload, 300); };

    var reload = async function () {
        renderPeriod();
        const [f, t] = range();
        const q = `from=${ymd(f)}&to=${ymd(t)}&search=${encodeURIComponent($("t-search").value.trim())}`;
        $("t-csv").href = `${ADMIN_API}?${q}&format=csv`;
        const res = await apiRequest(`${ADMIN_API}?${q}`);
        if (!res.success) { showToast(res.message, "error"); return; }
        data = res.data;
        [...selected].forEach(id => { if (!data.agents.some(a => a.id === id)) selected.delete(id); });
        renderTeam();
        renderTemplates();
    };

    function renderTeam() {
        const dates = data.dates;
        $("t-head").innerHTML = `<tr>
            <th class="text-start px-4 py-2 sticky start-0 bg-[#140c1d] min-w-[190px]"><label class="flex items-center gap-2 normal-case"><input type="checkbox" id="t-all" class="accent-purple-500"> ${t("Agent")}</label></th>
            ${dates.map(ds => { const d = parse(ds); const today = ds === data.today; const we = d.getDay() === 0 || d.getDay() === 6;
                return `<th class="px-2 py-2 text-center min-w-[92px] ${today ? "text-purple-300" : we ? "text-gray-600" : ""}">${DAYS[d.getDay()]}<div class="font-mono normal-case">${pad(d.getDate())}/${pad(d.getMonth() + 1)}</div></th>`; }).join("")}
            <th class="px-3 py-2 text-end">${t("Total")}</th></tr>`;
        if (!data.agents.length) {
            $("t-body").innerHTML = `<tr><td colspan="${dates.length + 2}" class="py-6 text-center text-gray-500">${t("No agents to plan.")}</td></tr>`;
        } else {
            $("t-body").innerHTML = data.agents.map(a => `<tr>
                <td class="px-4 py-2 sticky start-0 bg-[#140c1d] align-top">
                    <label class="flex items-start gap-2">
                        <input type="checkbox" data-sel="${a.id}" class="mt-0.5 accent-purple-500" ${selected.has(a.id) ? "checked" : ""}>
                        <span><span class="font-semibold text-gray-100">${esc(a.name)}</span> <span class="text-gray-500 font-mono">@${esc(a.username)}</span>
                        <button data-assign="${a.id}" class="block text-start text-[11px] text-purple-300 hover:text-purple-200 mt-0.5">${esc(a.schedule_now || t("Default schedule"))} &#9662;</button></span>
                    </label>
                </td>
                ${dates.map(ds => {
                    const x = a.days[ds];
                    const changed = x.source === "day";
                    const cls = x.leave.length ? "border-emerald-700 bg-emerald-950/30" : changed ? "border-amber-600 bg-amber-950/30" : x.shifts.length ? "border-[#2d1e44] bg-[#1a1226]" : "border-[#231733] bg-transparent";
                    return `<td class="px-1.5 py-1.5 align-top"><button data-cell="${a.id}|${ds}" title="${esc((changed ? t("Changed for this day") : (x.template_name || "")) + (x.note ? ": " + x.note : ""))}"
                        class="w-full min-h-[44px] text-start rounded-lg border ${cls} hover:border-purple-500 px-2 py-1 text-[11px] ${ds === data.today ? "ring-1 ring-purple-700" : ""}">
                        ${shiftsHtml(x.shifts, "text-gray-100")}${leaveHtml(x.leave)}</button></td>`;
                }).join("")}
                <td class="px-3 py-2 text-end font-mono align-top text-gray-300">${dur(a.planned_seconds)}</td>
            </tr>`).join("");
        }
        $("t-all").checked = data.agents.length > 0 && data.agents.every(a => selected.has(a.id));
        $("t-all").onchange = e => { data.agents.forEach(a => e.target.checked ? selected.add(a.id) : selected.delete(a.id)); renderTeam(); };
        document.querySelectorAll("[data-sel]").forEach(c => c.onchange = () => { const id = +c.dataset.sel; c.checked ? selected.add(id) : selected.delete(id); updateSel(); });
        document.querySelectorAll("[data-cell]").forEach(b => b.onclick = () => { const [id, ds] = b.dataset.cell.split("|"); openDay([+id], ds); });
        document.querySelectorAll("[data-assign]").forEach(b => b.onclick = e => { e.preventDefault(); openAssign([+b.dataset.assign]); });
        updateSel();
    }
    function updateSel() {
        const n = selected.size;
        $("t-selected").textContent = n ? t("{n} selected", { n }) : "";
        $("t-plan-sel").disabled = !n; $("t-assign-sel").disabled = !n;
        $("t-plan-sel").classList.toggle("opacity-50", !n); $("t-assign-sel").classList.toggle("opacity-50", !n);
    }
    $("t-plan-sel").onclick = () => openDay([...selected], data.dates.includes(data.today) ? data.today : data.dates[0]);
    $("t-assign-sel").onclick = () => openAssign([...selected]);

    // ---------------- Modal helpers ----------------
    function modal(title, body, buttons) {
        $("m-title").textContent = title;
        $("m-body").innerHTML = body;
        $("m-foot").innerHTML = "";
        buttons.forEach(([label, cls, fn]) => { const b = document.createElement("button"); b.className = cls; b.textContent = label; b.onclick = fn; $("m-foot").appendChild(b); });
        $("sc-modal").classList.remove("hidden");
    }
    const closeModal = () => $("sc-modal").classList.add("hidden");
    $("m-close").onclick = closeModal;
    $("sc-modal").onclick = e => { if (e.target === $("sc-modal")) closeModal(); };
    document.addEventListener("keydown", e => { if (e.key === "Escape") closeModal(); });
    const names = ids => ids.map(id => data.agents.find(a => a.id === id)?.name || ("#" + id));
    const who = ids => ids.length === 1 ? esc(names(ids)[0]) : `${t("{n} agents", { n: ids.length })} <span class="text-gray-500">(${esc(names(ids).slice(0, 4).join(", "))}${ids.length > 4 ? ", ..." : ""})</span>`;

    // A list of shift lines (start/end inputs) inside a container
    function shiftEditor(container, shifts, max) {
        container.innerHTML = "";
        const list = document.createElement("div"); list.className = "space-y-2";
        const add = document.createElement("button"); add.className = "text-[11px] text-purple-300 hover:text-purple-200"; add.textContent = t("+ Add shift");
        const line = s => {
            const row = document.createElement("div"); row.className = "flex items-center gap-2";
            row.innerHTML = `<input type="time" class="${INPUT} font-mono" value="${hm(s.start)}"><span class="text-gray-500">${t("to")}</span><input type="time" class="${INPUT} font-mono" value="${hm(s.end)}"><button class="text-gray-500 hover:text-red-300 px-1" title="${t("Remove")}">&times;</button>`;
            row.querySelector("button").onclick = () => { row.remove(); add.classList.toggle("hidden", list.children.length >= max); };
            list.appendChild(row);
        };
        shifts.forEach(line);
        add.onclick = () => {
            const last = [...list.children].pop();
            const end = last ? last.querySelectorAll("input")[1].value : "";
            line({ start: end || "08:00", end: "" });
            add.classList.toggle("hidden", list.children.length >= max);
        };
        add.classList.toggle("hidden", shifts.length >= max);
        container.appendChild(list); container.appendChild(add);
        return () => [...list.children].map(r => { const [a, b] = r.querySelectorAll("input"); return [a.value, b.value]; }).filter(([a, b]) => a || b);
    }

    // ---------------- Plan days ----------------
    function openDay(ids, date) {
        const a = data.agents.find(x => x.id === ids[0]);
        const x = a?.days[date];
        const shifts = ids.length === 1 && x ? x.shifts : (x ? x.shifts : []);
        const info = ids.length === 1 && x
            ? `<div class="text-[11px] text-gray-400">${t("Now:")} <span class="text-gray-200">${esc(x.label)}</span> (${x.source === "day" ? t("changed for this day") : esc(x.template_name || t("default schedule"))})${x.leave.length ? " · " + t("on leave:") + " " + esc(x.leave.map(l => l.label).join(", ")) : ""}</div>` : "";
        modal(t("Plan days"), `
            <div>${who(ids)}</div>
            <div class="flex flex-wrap items-center gap-2">
                <label>${t("From")} <input type="date" id="d-from" class="${INPUT} font-mono" value="${date}"></label>
                <label>${t("to")} <input type="date" id="d-to" class="${INPUT} font-mono" value="${date}"></label>
                <span class="text-[11px] text-gray-500">${t("Same hours on every day in between.")}</span>
            </div>
            ${info}
            <div><div class="text-[11px] uppercase tracking-wider text-gray-500 mb-1.5">${t("Shifts")}</div><div id="d-shifts"></div>
            <div class="text-[11px] text-gray-500 mt-1">${t("No shift = day off: no absence is counted.")}</div></div>
            <input type="text" id="d-note" maxlength="255" placeholder="${t("Note (optional), e.g. covering the evening team")}" class="${INPUT} w-full" value="${ids.length === 1 && x && x.note ? esc(x.note) : ""}">
        `, [
            [t("Use the schedule's hours"), BTN, () => saveDay(ids, "clear_day")],
            [t("Day off"), BTN, () => saveDay(ids, "set_day", [])],
            [t("Save"), BTNP, () => saveDay(ids, "set_day")],
        ]);
        readShifts = shiftEditor($("d-shifts"), shifts.length ? shifts : [{ start: "08:00", end: "12:00" }], data.max_shifts);
    }
    let readShifts = () => [];
    async function saveDay(ids, action, forced) {
        const body = { action, user_ids: ids, from: $("d-from").value, to: $("d-to").value, note: $("d-note").value };
        if (action === "set_day") body.shifts = forced ?? readShifts();
        const res = await apiRequest(ADMIN_API, "POST", body);
        showToast(res.message, res.success ? "success" : "error");
        if (res.success) { closeModal(); reload(); }
    }

    // ---------------- Assign a schedule ----------------
    function openAssign(ids) {
        const one = ids.length === 1 ? data.agents.find(a => a.id === ids[0]) : null;
        const hist = one && one.history.length ? `<div><div class="text-[11px] uppercase tracking-wider text-gray-500 mb-1.5">${t("History")}</div>
            <div class="divide-y divide-[#231733] border border-[#231733] rounded-lg">${one.history.map(h => `<div class="flex items-center justify-between px-3 py-1.5">
                <span><span class="font-mono text-gray-400">${t("from {date}", { date: esc(h.start_date) })}</span> · <span class="text-gray-100">${esc(h.template_name)}</span>
                <span class="text-gray-500">${h.by_first_name ? t("by {name}", { name: esc(h.by_first_name + " " + h.by_last_name) }) : ""}</span></span>
                <button data-unassign="${h.id}" class="text-[11px] text-red-300 hover:text-red-200">${t("Remove")}</button></div>`).join("")}</div>
            <div class="text-[11px] text-gray-500 mt-1">${t("Before the first date, the agent follows the default schedule.")}</div></div>` : "";
        modal(t("Assign a schedule"), `
            <div>${who(ids)}</div>
            <div class="flex flex-wrap items-center gap-2">
                <select id="a-tpl" class="${INPUT}">${data.templates.map(tp => `<option value="${tp.id}" ${one && one.schedule_now_id === tp.id ? "selected" : ""}>${esc(tp.name)}${tp.is_default ? " " + t("(default)") : ""}</option>`).join("")}</select>
                <label>${t("from")} <input type="date" id="a-from" class="${INPUT} font-mono" value="${data.today}"></label>
            </div>
            <div id="a-preview" class="text-[11px] text-gray-400"></div>
            <div class="text-[11px] text-gray-500">${t("Days you changed one by one keep their hours.")}</div>
            ${hist}
        `, [[t("Assign"), BTNP, async () => {
            const res = await apiRequest(ADMIN_API, "POST", { action: "assign", user_ids: ids, template_id: +$("a-tpl").value, start_date: $("a-from").value });
            showToast(res.message, res.success ? "success" : "error");
            if (res.success) { closeModal(); reload(); }
        }]]);
        const preview = () => { const t = data.templates.find(t => t.id === +$("a-tpl").value); $("a-preview").innerHTML = t ? weekSummary(t.week) : ""; };
        $("a-tpl").onchange = preview; preview();
        document.querySelectorAll("[data-unassign]").forEach(b => b.onclick = async () => {
            const res = await apiRequest(ADMIN_API, "POST", { action: "remove_assignment", id: +b.dataset.unassign });
            showToast(res.message, res.success ? "success" : "error");
            if (res.success) { await reload(); openAssign(ids); }
        });
    }

    // ---------------- Schedules ----------------
    function weekSummary(week) {
        return `<div class="grid grid-cols-7 gap-1 text-center">${Object.keys(WEEKDAYS).map(n => {
            const s = week[n] || [];
            return `<div class="rounded-md border ${s.length ? "border-[#2d1e44] bg-[#1a1226]" : "border-[#231733]"} px-1 py-1">
                <div class="text-[10px] uppercase text-gray-500">${DAYS[n % 7]}</div>
                <div class="text-[10px] leading-tight">${s.length ? s.map(x => `<div class="font-mono text-gray-200">${hm(x.start)}-${hm(x.end)}</div>`).join("") : `<div class="text-gray-600">${t("off")}</div>`}</div></div>`;
        }).join("")}</div>`;
    }
    function renderTemplates() {
        $("tpl-list").innerHTML = data.templates.map(tp => `
            <div class="bg-[#140c1d] border ${tp.is_default ? "border-purple-700" : "border-[#251738]"} rounded-2xl p-4 space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div><div class="text-sm font-semibold text-white">${esc(tp.name)} ${tp.is_default ? `<span class="ms-1 text-[10px] bg-purple-900 text-purple-200 px-1.5 py-0.5 rounded">${t("Default")}</span>` : ""}</div>
                    <div class="text-[11px] text-gray-400">${t("{time} a week", { time: dur(tp.weekly_seconds) })} · ${tp.agents_now === 1 ? t("1 agent assigned now") : t("{n} agents assigned now", { n: tp.agents_now })}${tp.is_default ? " " + t("+ everyone without a schedule") : ""}</div></div>
                    <div class="flex gap-1.5 shrink-0">
                        <button data-edit="${tp.id}" class="${BTN}">${t("Edit")}</button>
                        ${tp.is_default ? "" : `<button data-default="${tp.id}" class="${BTN}">${t("Make default")}</button>`}
                    </div>
                </div>
                ${weekSummary(tp.week)}
            </div>`).join("") || `<div class="text-xs text-gray-500">${t("No schedules yet.")}</div>`;
        document.querySelectorAll("[data-edit]").forEach(b => b.onclick = () => openTemplate(data.templates.find(tp => tp.id === +b.dataset.edit)));
        document.querySelectorAll("[data-default]").forEach(b => b.onclick = async () => {
            const tp = data.templates.find(tp => tp.id === +b.dataset.default);
            if (!confirm(t("Make \"{name}\" the default schedule? Every agent with no schedule assigned will follow it, past days included.", { name: tp.name }))) return;
            const res = await apiRequest(ADMIN_API, "POST", { action: "set_default", id: tp.id });
            showToast(res.message, res.success ? "success" : "error");
            if (res.success) reload();
        });
    }
    $("tpl-new").onclick = () => openTemplate(null);

    function openTemplate(tp) {
        const week = tp ? tp.week : { 1: [{start:"08:00",end:"12:00"},{start:"14:00",end:"18:00"}], 2: [{start:"08:00",end:"12:00"},{start:"14:00",end:"18:00"}], 3: [{start:"08:00",end:"12:00"},{start:"14:00",end:"18:00"}], 4: [{start:"08:00",end:"12:00"},{start:"14:00",end:"18:00"}], 5: [{start:"08:00",end:"12:00"},{start:"14:00",end:"18:00"}], 6: [], 7: [] };
        const buttons = [];
        if (tp && !tp.is_default && !tp.in_use) buttons.push([t("Delete"), BTND, async () => {
            if (!confirm(t("Delete \"{name}\"?", { name: tp.name }))) return;
            const res = await apiRequest(ADMIN_API, "POST", { action: "delete_template", id: tp.id });
            showToast(res.message, res.success ? "success" : "error");
            if (res.success) { closeModal(); reload(); }
        }]);
        buttons.push([t("Save"), BTNP, async () => {
            const w = {}; Object.keys(readers).forEach(n => w[n] = readers[n]());
            const res = await apiRequest(ADMIN_API, "POST", { action: "save_template", id: tp ? tp.id : null, name: $("s-name").value, week: w });
            showToast(res.message, res.success ? "success" : "error");
            if (res.success) { closeModal(); reload(); }
        }]);
        modal(tp ? t("Edit schedule") : t("New schedule"), `
            <input type="text" id="s-name" maxlength="100" placeholder="${t("Name, e.g. Evening team")}" class="${INPUT} w-full text-sm" value="${tp ? esc(tp.name) : ""}">
            ${tp && (tp.agents_now || tp.is_default) ? `<div class="text-[11px] text-amber-300">${tp.agents_now === 1 ? t("Used by 1 agent") : t("Used by {n} agents", { n: tp.agents_now })}${tp.is_default ? " " + t("and everyone without a schedule") : ""}. ${t("Changes apply to all their days, past days included.")}</div>` : ""}
            <div class="space-y-2" id="s-days">${Object.keys(WEEKDAYS).map(n => `
                <div class="flex items-start gap-3 border-b border-[#231733] pb-2"><div class="w-20 pt-1.5 font-semibold text-gray-200">${WEEKDAYS[n]}</div><div class="flex-1" id="s-day-${n}"></div></div>`).join("")}
            </div>
            <button id="s-copy" class="text-[11px] text-purple-300 hover:text-purple-200">${t("Copy Monday to Tuesday-Friday")}</button>
            <div class="text-[11px] text-gray-500">${t("A day with no shift is a day off. Shifts start and end on the same day.")}</div>
        `, buttons);
        const readers = {};
        Object.keys(WEEKDAYS).forEach(n => readers[n] = shiftEditor($("s-day-" + n), week[n] || [], data.max_shifts));
        $("s-copy").onclick = () => {
            const mon = readers[1]().map(([a, b]) => ({ start: a, end: b }));
            [2, 3, 4, 5].forEach(n => readers[n] = shiftEditor($("s-day-" + n), mon, data.max_shifts));
        };
    }

    // Opened from the header search: show that agent's plan
    const preset = new URLSearchParams(location.search).get("search");
    if (preset) { $("t-search").value = preset; tab = "team"; }
    setTab(tab);
    reload();
})();
</script>
HTML;

require_once ROOT_PATH . '/views/layouts/main.php';
