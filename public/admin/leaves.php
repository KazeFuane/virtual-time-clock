<?php
/**
 * Leave / PTO (managers and administrators)
 *
 * Open with ?user_id=N&date=YYYY-MM-DD to prefill the form, e.g. from an
 * absence on the Time Errors page.
 */
require_once __DIR__ . '/../../src/bootstrap.php';

$currentUser = AdminMiddleware::requireManager();

$pageTitle = t('Leave &amp; PTO') . ' &mdash; ' . APP_NAME;
$breadcrumbs = [
    ['label' => t('Time Clock'), 'url' => '/virtual-time-clock/public/dashboard.php'],
    ['label' => t('Administration'), 'url' => '/virtual-time-clock/public/admin/index.php'],
    ['label' => t('Leave & PTO'), 'url' => '']
];

ob_start();
?>

<div class="max-w-5xl mx-auto space-y-6" id="lv-root"
     data-user="<?= (int) ($_GET['user_id'] ?? 0) ?>"
     data-date="<?= htmlspecialchars(preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : '', ENT_QUOTES) ?>">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-[#251738] gap-4">
        <div>
            <h1 class="text-xl font-bold text-white tracking-wide"><?= t('Leave &amp; PTO') ?></h1>
            <p class="text-xs text-gray-400 mt-1"><?= t('Record paid time off or other leave for an agent. Those days show as leave in the time errors summary and exports, not as an absence.') ?></p>
        </div>
        <div class="flex gap-2">
            <a href="/virtual-time-clock/public/admin/time-errors.php" class="px-3 py-1.5 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 text-xs font-semibold whitespace-nowrap"><?= t('&larr; Time Errors') ?></a>
            <a href="/virtual-time-clock/public/admin/time-corrections.php" class="px-3 py-1.5 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 text-xs font-semibold whitespace-nowrap"><?= t('Correct times') ?></a>
        </div>
    </div>

    <!-- Record leave -->
    <div class="bg-[#140c1d] border border-[#251738] rounded-2xl p-4 sm:p-5 shadow-xl">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <h2 class="text-sm font-semibold text-white"><?= t('Record leave') ?></h2>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-end">
            <div class="lg:col-span-4">
                <label class="block text-[11px] uppercase tracking-wider text-gray-500 mb-1.5"><?= t('Agent') ?></label>
                <select id="lv-agent" class="w-full bg-[#1a1226] border border-[#2d1e44] text-sm text-gray-100 rounded-xl px-3 py-2 focus:outline-none focus:border-purple-500"></select>
            </div>
            <div class="lg:col-span-3">
                <label class="block text-[11px] uppercase tracking-wider text-gray-500 mb-1.5"><?= t('Type') ?></label>
                <select id="lv-type" class="w-full bg-[#1a1226] border border-[#2d1e44] text-sm text-gray-100 rounded-xl px-3 py-2 focus:outline-none focus:border-purple-500"></select>
            </div>
            <div class="lg:col-span-5">
                <label class="block text-[11px] uppercase tracking-wider text-gray-500 mb-1.5"><?= t('Dates') ?></label>
                <div class="flex items-center gap-2">
                    <input type="date" id="lv-from" class="flex-1 min-w-0 bg-[#1a1226] border border-[#2d1e44] text-sm font-mono text-gray-100 rounded-xl px-3 py-1.5 focus:outline-none focus:border-purple-500">
                    <span class="text-gray-500 text-xs"><?= t('to') ?></span>
                    <input type="date" id="lv-to" class="flex-1 min-w-0 bg-[#1a1226] border border-[#2d1e44] text-sm font-mono text-gray-100 rounded-xl px-3 py-1.5 focus:outline-none focus:border-purple-500">
                </div>
            </div>
            <div class="lg:col-span-4">
                <label class="block text-[11px] uppercase tracking-wider text-gray-500 mb-1.5"><?= t('Part of the day') ?></label>
                <div id="lv-part" class="grid grid-cols-3 bg-[#1a1226] border border-[#2d1e44] rounded-xl p-1 text-xs font-semibold">
                    <button type="button" data-part="FULL" class="py-1.5 rounded-lg"><?= t('Full day') ?></button>
                    <button type="button" data-part="SHIFT_1" class="py-1.5 rounded-lg"><?= t('Morning') ?></button>
                    <button type="button" data-part="SHIFT_2" class="py-1.5 rounded-lg"><?= t('Afternoon') ?></button>
                </div>
            </div>
            <div class="lg:col-span-6">
                <label class="block text-[11px] uppercase tracking-wider text-gray-500 mb-1.5"><?= t('Note (optional)') ?></label>
                <input type="text" id="lv-note" maxlength="1000" placeholder="<?= t('e.g. Approved by team lead, request of 20/11') ?>"
                       class="w-full bg-[#1a1226] border border-[#2d1e44] text-sm text-gray-100 rounded-xl px-3 py-1.5 focus:outline-none focus:border-purple-500">
            </div>
            <div class="lg:col-span-2">
                <button id="lv-save" class="w-full px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold transition"><?= t('Save leave') ?></button>
            </div>
        </div>
        <p id="lv-preview" class="text-[11px] text-gray-400 mt-3"></p>
    </div>

    <!-- Leave list -->
    <div class="bg-[#140c1d] border border-[#251738] rounded-2xl overflow-hidden shadow-xl">
        <div class="px-5 py-3 bg-[#1a1226] border-b border-[#251738] flex flex-wrap items-center justify-between gap-3">
            <h3 id="l-title" class="text-xs font-semibold text-white uppercase tracking-wider"><?= t('Recorded leave') ?></h3>
            <div class="flex items-center gap-3 text-[11px] text-gray-400">
                <label class="flex items-center gap-1.5 cursor-pointer select-none"><input type="checkbox" id="l-mine" class="accent-purple-500" checked> <?= t('Only this agent') ?></label>
                <label class="flex items-center gap-1.5 cursor-pointer select-none"><input type="checkbox" id="l-cancelled" class="accent-purple-500"> <?= t('Show cancelled') ?></label>
            </div>
        </div>
        <div id="l-list" class="divide-y divide-[#231733]">
            <div class="py-6 text-center text-xs text-gray-500"><?= t('Loading...') ?></div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$pageScripts = <<<'HTML'
<script>
(function () {
    const API = "/virtual-time-clock/api/admin/leaves.php";
    const root = document.getElementById("lv-root");
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
    const pad = n => String(n).padStart(2, "0");
    const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const dmy = s => { const [y, m, d] = s.split("-"); return `${d}/${m}/${y}`; };
    const parse = s => { const [y, m, d] = s.split("-").map(Number); return new Date(y, m - 1, d); };
    const DAYS = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"].map(d => t(d));
    // Shift lengths from the schedule (08:00-12:00 and 14:00-18:00), for the preview only
    const SHIFT_HOURS = { FULL: 8, SHIFT_1: 4, SHIFT_2: 4 };
    let types = {}, part = "FULL", today = "";

    function workingDays(from, to) {
        let n = 0;
        for (let d = parse(from); ymd(d) <= to; d.setDate(d.getDate() + 1)) if (d.getDay() % 6 !== 0) n++;
        return n;
    }

    function renderPart() {
        const single = $("lv-from").value && $("lv-from").value === ($("lv-to").value || $("lv-from").value);
        if (!single) part = "FULL";
        document.querySelectorAll("#lv-part button").forEach(b => {
            const on = b.dataset.part === part, disabled = !single && b.dataset.part !== "FULL";
            b.disabled = disabled;
            b.title = disabled ? t("A half day is for one date only") : "";
            b.className = "py-1.5 rounded-lg transition " + (on ? "bg-emerald-600 text-white shadow" : disabled ? "text-gray-600 cursor-not-allowed" : "text-gray-400 hover:text-white");
        });
        preview();
    }

    function preview() {
        const from = $("lv-from").value, to = $("lv-to").value || from;
        const lt = types[$("lv-type").value];
        if (!from || !lt) { $("lv-preview").textContent = ""; return; }
        if (to < from) { $("lv-preview").innerHTML = `<span class="text-red-400">${t("The end date is before the start date.")}</span>`; return; }
        const n = workingDays(from, to);
        if (!n) { $("lv-preview").innerHTML = `<span class="text-red-400">${t("Only weekend days. Leave counts on Monday to Friday.")}</span>`; return; }
        const hours = part === "FULL" ? n * 8 : SHIFT_HOURS[part];
        const days = part === "FULL" ? (n === 1 ? t("1 working day") : t("{n} working days", { n })) : t("half a day");
        $("lv-preview").innerHTML = `${esc(lt.label)}: ${days}, ` + t("{hours} counted as {kind}.", { hours: `<span class="text-white font-mono">${hours}h</span>`, kind: `<span class="${lt.paid ? "text-emerald-300" : "text-amber-300"}">${lt.paid ? t("paid leave") : t("unpaid leave")}</span>` }) + " " + t("Weekends are not counted.");
    }

    async function loadList() {
        const uid = $("lv-agent").value;
        const q = [];
        if ($("l-mine").checked && uid) q.push(`user_id=${uid}`);
        if ($("l-cancelled").checked) q.push("cancelled=1");
        const res = await apiRequest(`${API}?${q.join("&")}`);
        if (!res.success) { $("l-list").innerHTML = `<div class="py-6 text-center text-xs text-red-400">${esc(res.message)}</div>`; return; }
        const list = res.data.leaves;
        const name = $("lv-agent").selectedOptions[0]?.dataset.name || "";
        $("l-title").innerHTML = `${t("Recorded leave")} <span class="normal-case font-normal text-gray-400 ms-2">${$("l-mine").checked && name ? esc(name) + " · " : ""}${list.length === 1 ? t("1 entry") : t("{n} entries", { n: list.length })}</span>`;
        $("l-list").innerHTML = list.length ? list.map(l => {
            const cancelled = !!l.cancelled_at;
            const when = l.start_date === l.end_date
                ? `${DAYS[parse(l.start_date).getDay()]} ${dmy(l.start_date)}`
                : `${dmy(l.start_date)} – ${dmy(l.end_date)}`;
            const badge = `<span class="px-2 py-0.5 rounded-md text-[10px] font-semibold ${cancelled ? "bg-gray-800 text-gray-400 border border-gray-700" : l.paid ? "bg-emerald-950 text-emerald-300 border border-emerald-800" : "bg-amber-950 text-amber-300 border border-amber-800"}">${esc(l.type_label)}${cancelled ? " · " + t("cancelled") : ""}</span>`;
            const days = l.day_part === "FULL" ? (l.working_days == 1 ? t("1 working day") : t("{n} working days", { n: l.working_days })) : esc(l.part_label);
            const status = !cancelled && l.end_date < today ? t("taken") : !cancelled && l.start_date > today ? t("planned") : "";
            return `<div class="px-5 py-3 ${cancelled ? "opacity-60" : ""}">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    ${badge}
                    <span class="font-mono text-gray-200">${when}</span>
                    <span class="text-gray-400">${days}${status ? " · " + status : ""}</span>
                    <span class="text-white font-semibold ms-2">${esc(l.first_name)} ${esc(l.last_name)}</span>
                    <span class="text-gray-500 font-mono">@${esc(l.username)}</span>
                    <span class="flex-1"></span>
                    ${cancelled ? "" : `<button class="px-3 py-1 rounded-lg bg-red-950/60 hover:bg-red-900/60 border border-red-900 text-red-300 text-[11px] font-semibold" data-cancel="${l.id}">${t("Cancel leave")}</button>`}
                </div>
                ${l.note ? `<div class="text-[11px] text-gray-300 mt-1">"${esc(l.note)}"</div>` : ""}
                <div class="text-[11px] text-gray-500 mt-1">${t("Recorded by {name} on {date}", { name: esc(l.creator_first_name || "?") + " " + esc(l.creator_last_name || ""), date: esc(l.created_at) })}${cancelled ? `. ${t("Cancelled by {name} on {date}", { name: esc(l.canceller_first_name || "?") + " " + esc(l.canceller_last_name || ""), date: esc(l.cancelled_at) })}${l.cancel_reason ? ': "' + esc(l.cancel_reason) + '"' : ""}` : ""}</div>
                <div class="hidden mt-2 flex items-center gap-2" data-cancel-box="${l.id}">
                    <input type="text" maxlength="1000" placeholder="${t("Why is it cancelled? (optional)")}" class="flex-1 bg-[#120a1c] border border-[#2d1e44] text-xs text-gray-200 rounded-lg px-3 py-1">
                    <button class="px-3 py-1 rounded-lg bg-red-700 hover:bg-red-600 text-white text-[11px] font-semibold" data-confirm="${l.id}">${t("Confirm cancel")}</button>
                </div>
            </div>`;
        }).join("") : `<div class="py-6 text-center text-xs text-gray-500">${t("No leave recorded.")}</div>`;

        $("l-list").querySelectorAll("[data-cancel]").forEach(b => b.addEventListener("click", () => {
            const box = $("l-list").querySelector(`[data-cancel-box="${b.dataset.cancel}"]`);
            box.classList.toggle("hidden"); box.querySelector("input").focus();
        }));
        $("l-list").querySelectorAll("[data-confirm]").forEach(b => b.addEventListener("click", async () => {
            const box = $("l-list").querySelector(`[data-cancel-box="${b.dataset.confirm}"]`);
            const r = await apiRequest(API, "POST", { action: "cancel", id: parseInt(b.dataset.confirm, 10), reason: box.querySelector("input").value });
            showToast(r.message, r.success ? "success" : "error");
            if (r.success) loadList();
        }));
    }

    $("lv-from").addEventListener("change", () => {
        if (!$("lv-to").value || $("lv-to").value < $("lv-from").value) $("lv-to").value = $("lv-from").value;
        renderPart();
    });
    $("lv-to").addEventListener("change", renderPart);
    $("lv-type").addEventListener("change", preview);
    document.querySelectorAll("#lv-part button").forEach(b => b.addEventListener("click", () => { if (!b.disabled) { part = b.dataset.part; renderPart(); } }));
    $("lv-agent").addEventListener("change", () => {
        history.replaceState(null, "", `?user_id=${$("lv-agent").value}`);
        loadList();
    });
    $("l-mine").addEventListener("change", loadList);
    $("l-cancelled").addEventListener("change", loadList);

    $("lv-save").addEventListener("click", async () => {
        if (!$("lv-agent").value) { showToast(t("Choose an agent."), "error"); return; }
        if (!$("lv-from").value) { showToast(t("Choose the leave dates."), "error"); $("lv-from").focus(); return; }
        const btn = $("lv-save");
        btn.disabled = true;
        const res = await apiRequest(API, "POST", {
            user_id: parseInt($("lv-agent").value, 10),
            leave_type: $("lv-type").value,
            start_date: $("lv-from").value,
            end_date: $("lv-to").value || $("lv-from").value,
            day_part: part,
            note: $("lv-note").value,
        });
        btn.disabled = false;
        showToast(res.message, res.success ? "success" : "error");
        if (res.success) { $("lv-note").value = ""; loadList(); }
    });

    document.addEventListener("DOMContentLoaded", async () => {
        const res = await apiRequest(API + "?user_id=0");
        if (!res.success) { $("l-list").innerHTML = `<div class="py-6 text-center text-xs text-red-400">${esc(res.message)}</div>`; return; }
        today = res.data.today;
        types = res.data.types;
        $("lv-type").innerHTML = Object.entries(types).map(([k, lt]) => `<option value="${k}">${esc(lt.label)}${lt.paid ? "" : " " + t("(not paid)")}</option>`).join("");
        const agents = res.data.agents;
        $("lv-agent").innerHTML = `<option value="">${t("Choose an agent...")}</option>` + agents.map(a =>
            `<option value="${a.id}" data-name="${esc(a.first_name)} ${esc(a.last_name)}">${esc(a.last_name.toUpperCase())} ${esc(a.first_name)} (@${esc(a.username)})${a.role !== "employee" ? " · " + esc(t(a.role)) : ""}</option>`).join("");
        const firstEmployee = agents.find(a => a.role === "employee") || agents[0];
        $("lv-agent").value = root.dataset.user !== "0" ? root.dataset.user : (firstEmployee ? String(firstEmployee.id) : "");
        if (root.dataset.date) { $("lv-from").value = root.dataset.date; $("lv-to").value = root.dataset.date; }
        renderPart();
        loadList();
    });
})();
</script>
HTML;

require_once ROOT_PATH . '/views/layouts/main.php';
