<?php
/**
 * My History Page Controller
 */
require_once __DIR__ . '/../src/bootstrap.php';

$currentUser = AuthMiddleware::requireAuth();

$pageTitle = t('My History') . ' &mdash; ' . APP_NAME;
$breadcrumbs = [
    ['label' => t('Time Clock'), 'url' => '/virtual-time-clock/public/dashboard.php'],
    ['label' => t('Time Management'), 'url' => '#'],
    ['label' => t('My History'), 'url' => '']
];

ob_start();
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-[#251738] gap-4">
        <div>
            <h1 class="text-xl font-bold text-white tracking-wide"><?= t('Attendance History') ?></h1>
            <p class="text-xs text-gray-400 mt-1"><?= t('Review your past workdays, hours logged, and break intervals.') ?></p>
        </div>

        <!-- Date selector bar -->
        <div class="flex items-center gap-2">
            <button id="btn-prev-day" type="button" class="p-2 rounded-lg bg-[#1a1226] border border-[#2d1e44] text-gray-300 hover:text-white hover:bg-[#251936] transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <input type="date" id="history-date-picker" class="bg-[#1a1226] border border-[#2d1e44] text-xs font-mono text-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-purple-500 transition">

            <button id="btn-next-day" type="button" class="p-2 rounded-lg bg-[#1a1226] border border-[#2d1e44] text-gray-300 hover:text-white hover:bg-[#251936] transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            <button id="btn-today" type="button" class="px-3 py-1.5 rounded-lg bg-purple-900/40 border border-purple-700/60 text-purple-300 hover:bg-purple-900/60 text-xs font-semibold transition">
                <?= t('Today') ?>
            </button>
        </div>
    </div>

    <!-- Day Overview Card -->
    <div id="history-summary-card" class="bg-[#140c1d] border border-[#251738] rounded-xl p-5 shadow-lg">
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center">
            <div class="p-3 bg-[#1a1226] rounded-lg border border-[#26183a]">
                <div class="text-[11px] text-gray-400 uppercase font-medium"><?= t('Status') ?></div>
                <div id="hist-status" class="font-bold text-xs mt-1 text-gray-300">--</div>
            </div>
            <div class="p-3 bg-[#1a1226] rounded-lg border border-[#26183a]">
                <div class="text-[11px] text-gray-400 uppercase font-medium"><?= t('Work Shift') ?></div>
                <div id="hist-shift" class="font-mono font-bold text-xs mt-1 text-purple-300">-- &rarr; --</div>
            </div>
            <div class="p-3 bg-[#1a1226] rounded-lg border border-[#26183a]">
                <div class="text-[11px] text-gray-400 uppercase font-medium"><?= t('Punctuality') ?></div>
                <div id="hist-punctuality" class="font-bold text-xs mt-1 text-gray-300">--</div>
            </div>
            <div class="p-3 bg-[#1a1226] rounded-lg border border-[#26183a]">
                <div class="text-[11px] text-gray-400 uppercase font-medium"><?= t('Total Worked') ?></div>
                <div id="hist-worked" class="font-mono font-bold text-xs mt-1 text-emerald-400">00:00:00</div>
            </div>
            <div class="p-3 bg-[#1a1226] rounded-lg border border-[#26183a]">
                <div class="text-[11px] text-gray-400 uppercase font-medium"><?= t('Total Break') ?></div>
                <div id="hist-break" class="font-mono font-bold text-xs mt-1 text-amber-400">00:00:00</div>
            </div>
        </div>
    </div>

    <!-- Detailed Actions Log -->
    <div class="bg-[#140c1d] border border-[#251738] rounded-xl overflow-hidden shadow-lg">
        <div class="px-4 py-3 bg-[#1a1226] border-b border-[#251738] flex items-center justify-between">
            <h3 class="text-xs font-semibold text-white uppercase tracking-wider"><?= t('Punch Log &amp; Timeline') ?></h3>
            <span id="hist-date-label" class="text-xs font-mono text-gray-400"></span>
        </div>
        <table class="w-full text-start text-xs">
            <thead class="bg-[#160e22] text-gray-400 font-semibold border-b border-[#251738]">
                <tr>
                    <th class="py-2.5 px-4"><?= t('Action Time') ?></th>
                    <th class="py-2.5 px-4"><?= t('Event Type') ?></th>
                    <th class="py-2.5 px-4"><?= t('Details') ?></th>
                </tr>
            </thead>
            <tbody id="hist-actions-tbody" class="divide-y divide-[#231733]">
                <tr>
                    <td colspan="3" class="py-6 text-center text-gray-500"><?= t('Select a date to inspect history.') ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$pageScripts = '
<script>
let selectedDate = new Date().toISOString().split("T")[0];

document.addEventListener("DOMContentLoaded", () => {
    const picker = document.getElementById("history-date-picker");
    picker.value = selectedDate;

    picker.addEventListener("change", (e) => {
        selectedDate = e.target.value;
        loadHistory(selectedDate);
    });

    document.getElementById("btn-prev-day").addEventListener("click", () => {
        shiftDate(-1);
    });

    document.getElementById("btn-next-day").addEventListener("click", () => {
        shiftDate(1);
    });

    document.getElementById("btn-today").addEventListener("click", () => {
        selectedDate = new Date().toISOString().split("T")[0];
        picker.value = selectedDate;
        loadHistory(selectedDate);
    });

    loadHistory(selectedDate);
});

function shiftDate(deltaDays) {
    const d = new Date(selectedDate);
    d.setDate(d.getDate() + deltaDays);
    selectedDate = d.toISOString().split("T")[0];
    document.getElementById("history-date-picker").value = selectedDate;
    loadHistory(selectedDate);
}

function formatDurationSec(sec) {
    if (!sec) return "00:00:00";
    const h = String(Math.floor(sec / 3600)).padStart(2, "0");
    const m = String(Math.floor((sec % 3600) / 60)).padStart(2, "0");
    const s = String(sec % 60).padStart(2, "0");
    return `${h}:${m}:${s}`;
}

async function loadHistory(dateStr) {
    document.getElementById("hist-date-label").textContent = dateStr;
    const res = await apiRequest(`/virtual-time-clock/api/attendance/history.php?date=${dateStr}`);
    
    const statusEl = document.getElementById("hist-status");
    const shiftEl = document.getElementById("hist-shift");
    const workedEl = document.getElementById("hist-worked");
    const breakEl = document.getElementById("hist-break");
    const tbody = document.getElementById("hist-actions-tbody");

    if (!res.success || !res.data || !res.data.record) {
        statusEl.textContent = t("No Record");
        statusEl.className = "font-bold text-xs mt-1 text-gray-500";
        shiftEl.textContent = "-- → --";
        workedEl.textContent = "00:00:00";
        breakEl.textContent = "00:00:00";
        tbody.innerHTML = `<tr><td colspan="3" class="py-8 text-center text-xs text-gray-500">${t("No attendance registered for this date.")}</td></tr>`;
        return;
    }

    const rec = res.data.record;
    statusEl.textContent = t(String(rec.status).replace("_", " "));
    statusEl.className = rec.status === "FINISHED" ? "font-bold text-xs mt-1 text-purple-400" : "font-bold text-xs mt-1 text-emerald-400";
    shiftEl.textContent = `${rec.start_time || "--:--"} → ${rec.end_time || "--:--"}`;
    workedEl.textContent = formatDurationSec(rec.total_work_seconds);
    breakEl.textContent = formatDurationSec(rec.total_break_seconds);

    const labels = {
        "START_WORKDAY": { text: t("Start Workday"), color: "text-emerald-400" },
        "END_WORKDAY":   { text: t("End Workday"), color: "text-rose-400" },
        "START_BREAK":   { text: t("Start Break"), color: "text-purple-400" },
        "END_BREAK":     { text: t("End Break"), color: "text-amber-400" }
    };

    if (!rec.actions || rec.actions.length === 0) {
        tbody.innerHTML = `<tr><td colspan="3" class="py-6 text-center text-xs text-gray-500">${t("No actions recorded.")}</td></tr>`;
        return;
    }

    let rows = "";
    rec.actions.forEach(a => {
        const meta = labels[a.action_type] || { text: a.action_type, color: "text-gray-300" };
        rows += `
            <tr class="border-b border-[#231733] hover:bg-[#1a1226]/50">
                <td class="py-2.5 px-4 font-mono text-gray-300">${a.action_time}</td>
                <td class="py-2.5 px-4 font-semibold ${meta.color}">${meta.text}</td>
                <td class="py-2.5 px-4 text-gray-400 text-[11px]">${a.action_type.includes("BREAK") ? t("Break Period") : t("Shift Event")}</td>
            </tr>
        `;
    });
    tbody.innerHTML = rows;
}
</script>
';

require_once ROOT_PATH . '/views/layouts/main.php';
