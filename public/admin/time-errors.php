<?php
/**
 * Time Errors by Agent (managers and administrators)
 */
require_once __DIR__ . '/../../src/bootstrap.php';

$currentUser = AdminMiddleware::requireManager();

$pageTitle = t('Time Errors') . ' &mdash; ' . APP_NAME;
$breadcrumbs = [
    ['label' => t('Time Clock'), 'url' => '/virtual-time-clock/public/dashboard.php'],
    ['label' => t('Administration'), 'url' => '/virtual-time-clock/public/admin/index.php'],
    ['label' => t('Time Errors'), 'url' => '']
];

ob_start();
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-[#251738] gap-4">
        <div>
            <h1 class="text-xl font-bold text-white tracking-wide"><?= t('Time Errors by Agent') ?></h1>
            <p class="text-xs text-gray-400 mt-1"><?= t('Clock-in and clock-out problems to review before pay is calculated. Today is not checked until the agent clocks out.') ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <input type="date" id="te-from" class="bg-[#1a1226] border border-[#2d1e44] text-xs font-mono text-gray-200 rounded-lg px-3 py-1.5">
            <span class="text-gray-500 text-xs"><?= t('to') ?></span>
            <input type="date" id="te-to" class="bg-[#1a1226] border border-[#2d1e44] text-xs font-mono text-gray-200 rounded-lg px-3 py-1.5">
            <a href="/virtual-time-clock/public/admin/time-corrections.php" class="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold"><?= t('Correct times') ?></a>
            <a href="/virtual-time-clock/public/admin/leaves.php" class="px-3 py-1.5 rounded-lg bg-emerald-950/60 hover:bg-emerald-900/60 border border-emerald-800 text-emerald-200 text-xs font-semibold"><?= t('Leave &amp; PTO') ?></a>
            <button id="te-csv" class="px-3 py-1.5 rounded-lg bg-emerald-700/50 hover:bg-emerald-600/60 border border-emerald-600 text-emerald-200 text-xs font-semibold"><?= t('Summary CSV') ?></button>
            <button id="te-csv-detail" class="px-3 py-1.5 rounded-lg bg-[#221532] hover:bg-[#2c1c3f] border border-[#312048] text-gray-200 text-xs font-semibold"><?= t('Details CSV') ?></button>
        </div>
    </div>

    <div class="bg-[#140c1d] border border-amber-900/60 rounded-xl overflow-hidden shadow-xl">
        <div class="px-4 py-3 bg-[#1a1226] border-b border-[#251738] flex items-center justify-between gap-3">
            <h3 class="text-xs font-semibold text-white uppercase tracking-wider"><?= t('Reports from agents') ?> <span id="tr-count" class="ms-1 text-amber-300"></span></h3>
            <select id="tr-status" class="bg-[#120a1c] border border-[#2d1e44] text-xs text-gray-200 rounded-lg px-2 py-1">
                <option value="OPEN"><?= t('Waiting') ?></option>
                <option value="RESOLVED"><?= t('Resolved') ?></option>
                <option value="ALL"><?= t('All') ?></option>
            </select>
        </div>
        <div id="tr-list" class="divide-y divide-[#231733]">
            <div class="py-6 text-center text-xs text-gray-500"><?= t('Loading...') ?></div>
        </div>
    </div>

    <div class="bg-[#140c1d] border border-[#251738] rounded-xl overflow-hidden shadow-xl">
        <div class="px-4 py-3 bg-[#1a1226] border-b border-[#251738] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <h3 class="text-xs font-semibold text-white uppercase tracking-wider"><?= t('Summary per agent') ?></h3>
            <input type="text" id="te-search" placeholder="<?= t('Search name or username...') ?>" class="bg-[#120a1c] border border-[#2d1e44] text-xs text-gray-200 rounded-lg px-3 py-1.5 w-64">
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-start text-xs">
                <thead class="bg-[#160e22] text-gray-400 font-semibold border-b border-[#251738]">
                    <tr>
                        <th class="py-2.5 px-4"><?= t('Agent') ?></th>
                        <th class="py-2.5 px-4"><?= t('Days checked') ?></th>
                        <th class="py-2.5 px-4"><?= t('Days with errors') ?></th>
                        <th class="py-2.5 px-4"><?= t('Errors') ?></th>
                        <th class="py-2.5 px-4"><?= t('Cannot verify') ?></th>
                        <th class="py-2.5 px-4" title="<?= t('Recorded work minus real work from the clock actions. Positive means the app counted extra time.') ?>"><?= t('Time difference') ?></th>
                        <th class="py-2.5 px-4" title="<?= t('Recorded leave in the period (not errors). Days, then hours.') ?>"><?= t('Leave') ?></th>
                        <th class="py-2.5 px-4"><?= t('Main problems') ?></th>
                    </tr>
                </thead>
                <tbody id="te-tbody" class="divide-y divide-[#231733]">
                    <tr><td colspan="8" class="py-8 text-center text-gray-500"><?= t('Loading...') ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$pageScripts = <<<'HTML'
<script>
(function () {
    const API = "/virtual-time-clock/api/admin/time-errors.php";
    const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
    const fmt = sec => {
        const neg = sec < 0; sec = Math.abs(sec);
        const h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60);
        return (neg ? "-" : sec ? "+" : "") + h + "h " + String(m).padStart(2, "0") + "m";
    };
    const iso = d => d.toISOString().split("T")[0];

    const from = document.getElementById("te-from");
    const to = document.getElementById("te-to");
    const search = document.getElementById("te-search");
    const now = new Date();
    to.value = iso(now);
    from.value = iso(new Date(now.getTime() - 29 * 86400000));
    // Opened from the header search: show that agent
    const preset = new URLSearchParams(location.search).get("search");
    if (preset) search.value = preset;

    const qs = extra => `from=${from.value}&to=${to.value}&search=${encodeURIComponent(search.value.trim())}${extra || ""}`;

    async function load() {
        const tbody = document.getElementById("te-tbody");
        const res = await apiRequest(`${API}?${qs()}`);
        if (!res.success) {
            tbody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-red-400">${esc(res.message)}</td></tr>`;
            return;
        }
        const { agents, labels } = res.data;
        if (!agents.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-gray-500">${t("No agents found.")}</td></tr>`;
            return;
        }
        tbody.innerHTML = agents.map((a, i) => {
            const top = Object.entries(a.counts).filter(([, n]) => n > 0).sort((x, y) => y[1] - x[1])
                .map(([code, n]) => `<span class="inline-block me-1 mb-1 px-2 py-0.5 rounded bg-red-950 text-red-300 border border-red-900 text-[10px]">${esc(labels[code])} &times;${n}</span>`).join("")
                || `<span class="text-emerald-400">${t("No errors")}</span>`;
            const diffClass = a.difference_seconds > 0 ? "text-amber-400" : a.difference_seconds < 0 ? "text-sky-400" : "text-gray-400";
            const rows = a.incidents.map(x => `
                <tr><td class="py-1 pe-3 font-mono text-gray-400">${esc(x.work_date)}</td>
                    <td class="py-1 pe-3 text-red-300">${esc(x.label)}</td>
                    <td class="py-1 text-gray-300">${esc(x.detail)}</td>
                    <td class="py-1 ps-3 text-end whitespace-nowrap">${x.code === "ABSENCE" ? `<a href="/virtual-time-clock/public/admin/leaves.php?user_id=${a.user_id}&date=${esc(x.work_date)}" class="text-emerald-300 hover:underline me-3">${t("Record leave")}</a>` : ""}<a href="/virtual-time-clock/public/admin/time-corrections.php?user_id=${a.user_id}&date=${esc(x.work_date)}" class="text-purple-300 hover:underline">${t("Correct times")}</a></td></tr>`).join("")
                + (a.leave_days || []).map(l => `
                <tr><td class="py-1 pe-3 font-mono text-gray-400">${esc(l.work_date)}</td>
                    <td class="py-1 pe-3 text-emerald-300">${esc(l.label)}${l.paid ? "" : " " + t("(unpaid)")}</td>
                    <td class="py-1 text-gray-300">${esc(l.part_label)}${l.planned ? " " + t("(planned)") : ""}${l.note ? " · " + esc(l.note) : ""}</td>
                    <td class="py-1 ps-3 text-end whitespace-nowrap"><a href="/virtual-time-clock/public/admin/leaves.php?user_id=${a.user_id}" class="text-emerald-300 hover:underline">${t("Leave &amp; PTO")}</a></td></tr>`).join("");
            const leaveN = Object.values(a.leave_counts || {}).reduce((x, y) => x + y, 0);
            const leaveSec = (a.paid_leave_seconds || 0) + (a.unpaid_leave_seconds || 0);
            return `
                <tr class="hover:bg-[#1a1226]/50 ${a.incidents.length || (a.leave_days || []).length ? "cursor-pointer" : ""}" data-toggle="${i}">
                    <td class="py-2.5 px-4"><div class="font-semibold text-white">${esc(a.name)}</div><div class="text-[11px] text-gray-500 font-mono">@${esc(a.username)}</div></td>
                    <td class="py-2.5 px-4 font-mono text-gray-300">${a.days_checked}</td>
                    <td class="py-2.5 px-4 font-mono text-gray-300">${a.days_with_errors}</td>
                    <td class="py-2.5 px-4 font-mono font-semibold ${a.error_count ? "text-red-400" : "text-emerald-400"}">${a.error_count}</td>
                    <td class="py-2.5 px-4 font-mono text-gray-300">${a.unverifiable_days}</td>
                    <td class="py-2.5 px-4 font-mono ${diffClass}">${fmt(a.difference_seconds)}</td>
                    <td class="py-2.5 px-4 font-mono ${leaveN ? "text-emerald-300" : "text-gray-500"}">${leaveN ? `${t("{n} d", { n: leaveN })} · ${fmt(leaveSec).replace("+", "")}` : "-"}</td>
                    <td class="py-2.5 px-4">${top}</td>
                </tr>
                <tr class="hidden bg-[#110a19]" data-detail="${i}"><td colspan="8" class="px-6 py-3"><table class="w-full text-[11px]">${rows}</table></td></tr>`;
        }).join("");
        tbody.querySelectorAll("[data-toggle]").forEach(tr => tr.addEventListener("click", () => {
            tbody.querySelector(`[data-detail="${tr.dataset.toggle}"]`).classList.toggle("hidden");
        }));
        // A single agent found from the header search: open their details
        if (preset && agents.length === 1 && search.value.trim() === preset) {
            tbody.querySelector('[data-detail="0"]').classList.remove("hidden");
        }
    }

    let searchTimer;
    search.addEventListener("input", () => { clearTimeout(searchTimer); searchTimer = setTimeout(load, 300); });
    from.addEventListener("change", load);
    to.addEventListener("change", load);
    document.getElementById("te-csv").addEventListener("click", () => location.href = `${API}?${qs("&format=csv")}`);
    document.getElementById("te-csv-detail").addEventListener("click", () => location.href = `${API}?${qs("&format=csv&detail=1")}`);
    // ---- Reports from agents
    const RAPI = "/virtual-time-clock/api/admin/time-error-reports.php";
    const trStatus = document.getElementById("tr-status");
    async function loadReports() {
        const list = document.getElementById("tr-list");
        const res = await apiRequest(`${RAPI}?status=${trStatus.value}`);
        if (!res.success) {
            list.innerHTML = `<div class="py-6 text-center text-xs text-red-400">${esc(res.message)}</div>`;
            return;
        }
        const reports = res.data.reports;
        const open = reports.filter(r => r.status === "OPEN").length;
        document.getElementById("tr-count").textContent = trStatus.value === "OPEN" && open ? `(${open})` : "";
        list.innerHTML = reports.length ? reports.map(r => {
            const action = r.status === "OPEN" ? `
                <div class="flex items-center gap-2 mt-2">
                    <input type="text" maxlength="1000" placeholder="${t("What did you fix? (optional)")}" class="flex-1 bg-[#120a1c] border border-[#2d1e44] text-xs text-gray-200 rounded-lg px-3 py-1" data-note="${r.id}">
                    <a href="/virtual-time-clock/public/admin/time-corrections.php?user_id=${r.user_id}&date=${esc(r.work_date)}&report_id=${r.id}" class="px-3 py-1 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-[11px] font-semibold">${t("Correct times")}</a>
                    <button class="px-3 py-1 rounded-lg bg-emerald-700/60 hover:bg-emerald-600/70 border border-emerald-600 text-emerald-100 text-[11px] font-semibold" data-resolve="${r.id}">${t("Mark resolved")}</button>
                </div>` : `<div class="text-[11px] text-emerald-300 mt-1">${t("Resolved by {name}", { name: esc(r.resolver_first_name || "") + " " + esc(r.resolver_last_name || "") })}${r.resolution_note ? ": " + esc(r.resolution_note) : ""}</div>`;
            return `
                <div class="px-4 py-3">
                    <div class="text-xs"><span class="font-semibold text-white">${esc(r.first_name)} ${esc(r.last_name)}</span>
                        <span class="text-gray-500 font-mono">@${esc(r.username)}</span>
                        <span class="ms-2 font-mono text-gray-400">${esc(r.work_date)}</span>
                        <span class="ms-2 text-red-300">${esc(r.error_label)}</span></div>
                    <div class="text-[11px] text-gray-300 mt-1">"${esc(r.message)}"</div>${action}
                </div>`;
        }).join("") : `<div class="py-6 text-center text-xs text-gray-500">${t("No reports.")}</div>`;

        list.querySelectorAll("[data-resolve]").forEach(b => b.addEventListener("click", async () => {
            const note = list.querySelector(`[data-note="${b.dataset.resolve}"]`).value;
            const r = await apiRequest(RAPI, "POST", { id: parseInt(b.dataset.resolve, 10), note });
            showToast(r.message, r.success ? "success" : "error");
            if (r.success) loadReports();
        }));
    }
    trStatus.addEventListener("change", loadReports);

    document.addEventListener("DOMContentLoaded", () => { load(); loadReports(); });
})();
</script>
HTML;

require_once ROOT_PATH . '/views/layouts/main.php';
