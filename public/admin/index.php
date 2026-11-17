<?php
/**
 * Administrator Overview & Attendance Dashboard
 */
require_once __DIR__ . '/../../src/bootstrap.php';

$currentUser = AdminMiddleware::requireManager();

$pageTitle = 'Admin Console &mdash; Virtual Time Clock';
$breadcrumbs = [
    ['label' => 'Time Clock', 'url' => '/virtual-time-clock/public/dashboard.php'],
    ['label' => 'Administration', 'url' => '#'],
    ['label' => 'Console', 'url' => '']
];

ob_start();
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-[#251738] gap-4">
        <div>
            <h1 class="text-xl font-bold text-white tracking-wide">Administration Console</h1>
            <p class="text-xs text-gray-400 mt-1">Monitor organization staff status, register new employees, and export records.</p>
        </div>

        <div class="flex items-center gap-2">
            <input type="date" id="admin-date-picker" class="bg-[#1a1226] border border-[#2d1e44] text-xs font-mono text-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-purple-500 transition">
            <button id="btn-export-csv" class="px-3 py-1.5 rounded-lg bg-emerald-700/50 hover:bg-emerald-600/60 border border-emerald-600 text-emerald-200 text-xs font-semibold flex items-center gap-1.5 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export CSV
            </button>
            <?php if ($currentUser['role'] === 'administrator'): ?>
                <button onclick="document.getElementById('modal-add-employee').classList.remove('hidden')" class="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold flex items-center gap-1.5 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Employee
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Attendance table -->
    <div class="bg-[#140c1d] border border-[#251738] rounded-xl overflow-hidden shadow-xl">
        <div class="px-4 py-3 bg-[#1a1226] border-b border-[#251738] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <h3 class="text-xs font-semibold text-white uppercase tracking-wider">Attendance Register</h3>
            <input type="text" id="admin-search-input" placeholder="Search staff name or username..." class="bg-[#120a1c] border border-[#2d1e44] text-xs text-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-purple-500 w-64">
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#160e22] text-gray-400 font-semibold border-b border-[#251738]">
                    <tr>
                        <th class="py-2.5 px-4">Employee</th>
                        <th class="py-2.5 px-4">Role</th>
                        <th class="py-2.5 px-4">Status</th>
                        <th class="py-2.5 px-4">Clock In</th>
                        <th class="py-2.5 px-4">Clock Out</th>
                        <th class="py-2.5 px-4">Work Hours</th>
                        <th class="py-2.5 px-4">Break Hours</th>
                    </tr>
                </thead>
                <tbody id="admin-attendance-tbody" class="divide-y divide-[#231733]">
                    <tr>
                        <td colspan="7" class="py-8 text-center text-gray-500">Loading team attendance...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Employee (Admins only) -->
<div id="modal-add-employee" class="hidden fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
    <div class="bg-[#140c1d] border border-[#2e1d44] rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-[#281b3d]">
            <h3 class="text-sm font-bold text-white">Create New Employee Account</h3>
            <button onclick="document.getElementById('modal-add-employee').classList.add('hidden')" class="text-gray-400 hover:text-white">&times;</button>
        </div>
        <form id="form-create-employee" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] text-gray-400 mb-1">First Name</label>
                    <input type="text" name="first_name" required class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
                </div>
                <div>
                    <label class="block text-[11px] text-gray-400 mb-1">Last Name</label>
                    <input type="text" name="last_name" required class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
                </div>
            </div>
            <div>
                <label class="block text-[11px] text-gray-400 mb-1">Username</label>
                <input type="text" name="username" required class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
            </div>
            <div>
                <label class="block text-[11px] text-gray-400 mb-1">Email</label>
                <input type="email" name="email" required class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
            </div>
            <div>
                <label class="block text-[11px] text-gray-400 mb-1">Password</label>
                <input type="password" name="password" required minlength="8" class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500" placeholder="Minimum 8 characters">
            </div>
            <div>
                <label class="block text-[11px] text-gray-400 mb-1">Role</label>
                <select name="role_id" class="w-full bg-[#1b1226] border border-[#312048] rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
                    <option value="1">Employee</option>
                    <option value="2">Manager</option>
                    <option value="3">Administrator</option>
                </select>
            </div>
            <div class="pt-2 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-add-employee').classList.add('hidden')" class="px-3 py-1.5 rounded-lg bg-[#221532] text-xs text-gray-300 hover:bg-[#2c1c3f]">Cancel</button>
                <button type="submit" class="px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-xs font-semibold text-white shadow">Register Employee</button>
            </div>
        </form>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$pageScripts = '
<script>
let adminSelectedDate = new Date().toISOString().split("T")[0];

document.addEventListener("DOMContentLoaded", () => {
    const picker = document.getElementById("admin-date-picker");
    picker.value = adminSelectedDate;

    picker.addEventListener("change", (e) => {
        adminSelectedDate = e.target.value;
        loadAdminAttendance();
    });

    const searchInput = document.getElementById("admin-search-input");
    let debounceTimer;
    searchInput.addEventListener("input", () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadAdminAttendance, 300);
    });

    document.getElementById("btn-export-csv").addEventListener("click", () => {
        const q = encodeURIComponent(searchInput.value.trim());
        window.location.href = `/virtual-time-clock/api/admin/attendance.php?date=${adminSelectedDate}&search=${q}&format=csv`;
    });

    const form = document.getElementById("form-create-employee");
    if (form) {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            data.role_id = parseInt(data.role_id, 10);

            const res = await apiRequest("/virtual-time-clock/api/admin/users.php", "POST", data);
            if (res.success) {
                showToast("Employee created successfully", "success");
                document.getElementById("modal-add-employee").classList.add("hidden");
                form.reset();
                loadAdminAttendance();
            } else {
                showToast(res.message, "error");
            }
        });
    }

    loadAdminAttendance();
});

async function loadAdminAttendance() {
    const q = encodeURIComponent(document.getElementById("admin-search-input").value.trim());
    const res = await apiRequest(`/virtual-time-clock/api/admin/attendance.php?date=${adminSelectedDate}&search=${q}`);
    const tbody = document.getElementById("admin-attendance-tbody");

    if (!res.success || !res.data || !res.data.records || res.data.records.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-xs text-gray-500">No staff records found for this date.</td></tr>`;
        return;
    }

    let rows = "";
    res.data.records.forEach(r => {
        const name = `${r.first_name} ${r.last_name}`;
        const workHrs = (r.total_work_seconds ? (r.total_work_seconds / 3600).toFixed(2) : "0.00") + " h";
        const breakHrs = (r.total_break_seconds ? (r.total_break_seconds / 3600).toFixed(2) : "0.00") + " h";

        let statusBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-800 text-gray-400">NOT STARTED</span>`;
        if (r.status === "WORKING") {
            statusBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-950 text-emerald-300 border border-emerald-800">WORKING</span>`;
        } else if (r.status === "ON_BREAK") {
            statusBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-950 text-amber-300 border border-amber-800">ON BREAK</span>`;
        } else if (r.status === "FINISHED") {
            statusBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-950 text-purple-300 border border-purple-800">FINISHED</span>`;
        }

        rows += `
            <tr class="border-b border-[#231733] hover:bg-[#1a1226]/50">
                <td class="py-2.5 px-4">
                    <div class="font-semibold text-white">${name}</div>
                    <div class="text-[11px] text-gray-500 font-mono">@${r.username}</div>
                </td>
                <td class="py-2.5 px-4 text-gray-400 capitalize">${r.role}</td>
                <td class="py-2.5 px-4">${statusBadge}</td>
                <td class="py-2.5 px-4 font-mono text-gray-300">${r.start_time || "--:--"}</td>
                <td class="py-2.5 px-4 font-mono text-gray-300">${r.end_time || "--:--"}</td>
                <td class="py-2.5 px-4 font-mono font-semibold text-emerald-400">${workHrs}</td>
                <td class="py-2.5 px-4 font-mono text-amber-400">${breakHrs}</td>
            </tr>
        `;
    });
    tbody.innerHTML = rows;
}
</script>
';

require_once ROOT_PATH . '/views/layouts/main.php';
